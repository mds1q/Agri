<?php
declare(strict_types=1);

const STORAGE_DIR = __DIR__ . '/../storage';
const JSON_PATH = STORAGE_DIR . '/portal-config.json';
const SIGNATURE_PATH = STORAGE_DIR . '/portal-config.json.sig';
const PUBLIC_KEY_PATH = STORAGE_DIR . '/config-signing-public.pem';
const LOCK_PATH = STORAGE_DIR . '/.lock';
const SPLASH_IMAGES_DIR = STORAGE_DIR . '/splash-images';
const PUSH_CREDENTIALS_PATH = __DIR__ . '/../private/push-credentials.php';

function read_push_credentials(): array
{
    if (!is_file(PUSH_CREDENTIALS_PATH)) return [];
    $credentials = require PUSH_CREDENTIALS_PATH;
    return is_array($credentials) ? $credentials : [];
}

function save_push_credentials(array $credentials): void
{
    $directory = dirname(PUSH_CREDENTIALS_PATH);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Push credentials folder তৈরি করা যায়নি।');
    }
    if (!is_writable($directory)) throw new RuntimeException('Push credentials folder writable নয়।');
    $contents = "<?php\nreturn " . var_export($credentials, true) . ";\n";
    $temporary = tempnam($directory, '.push-credentials-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Push credentials সংরক্ষণ করা যায়নি।');
    }
    chmod($temporary, 0600);
    if (!rename($temporary, PUSH_CREDENTIALS_PATH)) {
        @unlink($temporary);
        throw new RuntimeException('Push credentials file replace করা যায়নি।');
    }
    chmod(PUSH_CREDENTIALS_PATH, 0600);
}

function push_http_request(string $url, array $headers, string $body, string $method = 'POST'): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension প্রয়োজন।');
    $handle = curl_init($url);
    if ($handle === false) throw new RuntimeException('Push API connection তৈরি করা যায়নি।');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false) throw new RuntimeException('Push provider connection failed: ' . $error);
    return [$status, (string)$response];
}

function push_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function send_onesignal_push(array $credentials, string $appId, string $title, string $message, string $imageUrl, string $clickUrl, string $channelId): string
{
    $apiKey = trim((string)($credentials['onesignal_api_key'] ?? ''));
    if ($appId === '' || $apiKey === '') throw new RuntimeException('OneSignal App ID বা API key দেওয়া হয়নি।');
    $payload = [
        'app_id' => $appId,
        'target_channel' => 'push',
        'included_segments' => ['Subscribed Users'],
        'headings' => ['en' => $title],
        'contents' => ['en' => $message],
        'name' => 'Portal Admin notification',
        'android_channel_id' => $channelId,
    ];
    if ($imageUrl !== '') $payload['big_picture'] = $imageUrl;
    if ($clickUrl !== '') $payload['url'] = $clickUrl;
    [$status, $response] = push_http_request(
        'https://api.onesignal.com/notifications',
        ['Content-Type: application/json; charset=utf-8', 'Authorization: Key ' . $apiKey],
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    if ($status < 200 || $status >= 300) throw new RuntimeException('OneSignal API error (' . $status . ').');
    $decoded = json_decode($response, true);
    return 'OneSignal sent: ' . (string)($decoded['id'] ?? 'accepted');
}

function firebase_access_token(array $serviceAccount): string
{
    $email = trim((string)($serviceAccount['client_email'] ?? ''));
    $privateKey = (string)($serviceAccount['private_key'] ?? '');
    if ($email === '' || $privateKey === '') throw new RuntimeException('Firebase service account অসম্পূর্ণ।');
    $now = time();
    $header = push_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $claims = push_base64url(json_encode([
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_THROW_ON_ERROR));
    $unsigned = $header . '.' . $claims;
    $signature = '';
    $key = openssl_pkey_get_private($privateKey);
    if ($key === false || !openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Firebase service account private key invalid।');
    }
    $assertion = $unsigned . '.' . push_base64url($signature);
    $body = http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
    [$status, $response] = push_http_request(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        $body
    );
    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('Firebase OAuth token পাওয়া যায়নি।');
    }
    return (string)$decoded['access_token'];
}

function send_firebase_push(array $credentials, string $projectId, string $title, string $message, string $imageUrl, string $clickUrl, string $channelId): string
{
    $serviceAccountJson = trim((string)($credentials['firebase_service_account_json'] ?? ''));
    $serviceAccount = json_decode($serviceAccountJson, true);
    if (!is_array($serviceAccount)) throw new RuntimeException('Firebase service account JSON সঠিক নয়।');
    $token = firebase_access_token($serviceAccount);
    $project = trim($projectId !== '' ? $projectId : (string)($serviceAccount['project_id'] ?? ''));
    if ($project === '') throw new RuntimeException('Firebase project ID দেওয়া হয়নি।');
    $notification = ['title' => $title, 'body' => $message];
    if ($imageUrl !== '') $notification['image'] = $imageUrl;
    $messagePayload = [
        'topic' => 'all_users',
        'notification' => $notification,
        'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => $channelId]],
        'data' => ['title' => $title, 'body' => $message, 'image_url' => $imageUrl, 'click_url' => $clickUrl],
    ];
    [$status, $response] = push_http_request(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($project) . '/messages:send',
        ['Content-Type: application/json; charset=utf-8', 'Authorization: Bearer ' . $token],
        json_encode(['message' => $messagePayload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    if ($status < 200 || $status >= 300) throw new RuntimeException('Firebase API error (' . $status . ').');
    $decoded = json_decode($response, true);
    return 'Firebase sent: ' . (string)($decoded['name'] ?? 'accepted');
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
        return true;
    }
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0]));
    if ($forwardedProto === 'https' || strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $cloudflareVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return $cloudflareVisitor !== '' && stripos($cloudflareVisitor, '"scheme":"https"') !== false;
}

function store_splash_upload(array $upload): ?array
{
    $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ছবি upload হয়নি। ফাইলের আকার ও PHP upload limit পরীক্ষা করুন।');
    }
    $temporaryPath = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('ছবির আকার ১ বাইট থেকে ৫ MB-এর মধ্যে হতে হবে।');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryPath);
    $image = @getimagesize($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime]) || $image === false || ($image['mime'] ?? '') !== $mime) {
        throw new RuntimeException('শুধু JPEG, PNG অথবা WebP ছবি upload করা যাবে।');
    }
    if (($image[0] ?? 0) < 1 || ($image[1] ?? 0) < 1 || $image[0] * $image[1] > 50_000_000) {
        throw new RuntimeException('ছবির মাপ ৫০ মেগাপিক্সেলের মধ্যে হতে হবে।');
    }
    if (!is_dir(SPLASH_IMAGES_DIR) && !mkdir(SPLASH_IMAGES_DIR, 0750, true) && !is_dir(SPLASH_IMAGES_DIR)) {
        throw new RuntimeException('ছবি রাখার storage folder তৈরি করা যায়নি।');
    }
    if (!is_writable(SPLASH_IMAGES_DIR)) {
        throw new RuntimeException('PHP service account-এর splash image storage-এ write permission নেই।');
    }

    $filename = 'splash-image-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $url = splash_image_url($filename);
    $destination = SPLASH_IMAGES_DIR . '/' . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('ছবিটি storage-এ সংরক্ষণ করা যায়নি।');
    }
    chmod($destination, 0640);
    return ['filename' => $filename, 'url' => $url];
}

function splash_image_url(string $filename): string
{
    if (!preg_match('/^splash-image-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename)) {
        throw new RuntimeException('Splash image filename is invalid.');
    }
    $configuredBase = trim((string)(getenv('AGRI_PUBLIC_BASE_URL') ?: ''));
    if ($configuredBase !== '') {
        $parts = parse_url($configuredBase);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('AGRI_PUBLIC_BASE_URL must be a public http(s) URL without query or fragment.');
        }
        $base = rtrim($configuredBase, '/');
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || preg_match('/[\r\n\s]/', $host) || !preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
            throw new RuntimeException('ছবির public URL তৈরি করা যায়নি; AGRI_PUBLIC_BASE_URL সেট করুন।');
        }
        $path = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin.php')));
        $path = $path === '/' || $path === '.' ? '' : '/' . trim($path, '/');
        $base = $scheme . '://' . $host . $path;
    }
    return $base . '/api.php?file=' . rawurlencode($filename);
}

function splash_image_filename_from_url(string $url): ?string
{
    $query = parse_url($url, PHP_URL_QUERY);
    if (!is_string($query)) return null;
    parse_str($query, $parameters);
    $filename = (string)($parameters['file'] ?? '');
    return preg_match('/^splash-image-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename) ? $filename : null;
}

function remove_unreferenced_splash_images(array $previous, array $updated): void
{
    $imageUrls = static function (array $config): array {
        $splash = is_array($config['splash_screen'] ?? null) ? $config['splash_screen'] : [];
        $urls = [(string)($splash['image_url'] ?? '')];
        foreach (($splash['pages'] ?? []) as $page) {
            if (is_array($page)) $urls[] = (string)($page['image_url'] ?? '');
        }
        $onboarding = is_array($config['onboarding'] ?? null) ? $config['onboarding'] : [];
        foreach (($onboarding['pages'] ?? []) as $page) {
            if (is_array($page)) $urls[] = (string)($page['image_url'] ?? '');
        }
        $welcome = is_array($config['popups_and_notifications']['welcome_popup'] ?? null)
            ? $config['popups_and_notifications']['welcome_popup'] : [];
        $urls[] = (string)($welcome['image_url'] ?? '');
        return $urls;
    };
    $stillUsed = [];
    foreach ($imageUrls($updated) as $url) {
        $filename = splash_image_filename_from_url($url);
        if ($filename !== null) $stillUsed[$filename] = true;
    }
    foreach ($imageUrls($previous) as $url) {
        $filename = splash_image_filename_from_url($url);
        if ($filename !== null && !isset($stillUsed[$filename])) {
            @unlink(SPLASH_IMAGES_DIR . '/' . $filename);
        }
    }
}

function signing_key_path(): string
{
    $configured = getenv('AGRI_CONFIG_PRIVATE_KEY');
    if ($configured !== false && $configured !== '') {
        return $configured;
    }
    $settings = panel_settings();
    $configuredPath = trim((string)($settings['signing_key_path'] ?? ''));
    if ($configuredPath !== '' && is_file($configuredPath)) {
        return $configuredPath;
    }

    // A config copied from another server may contain its old absolute path.
    // Keep the panel portable by resolving the bundled key relative to the
    // panel root when that path is no longer available.
    return dirname(__DIR__) . '/private/config-signing-private.pem';
}

function panel_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $settingsPath = dirname(__DIR__) . '/private/config.php';
    $settings = is_file($settingsPath) ? (require $settingsPath) : [];
    return is_array($settings) ? $settings : [];
}

function storage_lock(int $operation)
{
    $handle = fopen(LOCK_PATH, 'c');
    if ($handle === false || !flock($handle, $operation)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Config storage is unavailable.');
    }
    return $handle;
}

function read_signed_config(): array
{
    $lock = storage_lock(LOCK_SH);
    try {
        $json = @file_get_contents(JSON_PATH);
        $signature = @file_get_contents(SIGNATURE_PATH);
        if ($json === false || $signature === false) {
            throw new RuntimeException('Config has not been initialized.');
        }
        return [$json, trim($signature)];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function save_signed_config(string $json): void
{
    if (!is_dir(STORAGE_DIR) || !is_writable(STORAGE_DIR)) {
        throw new RuntimeException('Config storage is not writable by PHP.');
    }
    $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Config must be a JSON object.');
    }
    if (strlen($json) > 1024 * 1024) {
        throw new RuntimeException('Config is larger than 1 MB.');
    }

    $privateKeyText = @file_get_contents(signing_key_path());
    $privateKey = $privateKeyText === false ? false : openssl_pkey_get_private($privateKeyText);
    if ($privateKey === false) {
        throw new RuntimeException('Signing key is unavailable or invalid.');
    }
    $signature = '';
    if (!openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Could not sign config.');
    }
    $encodedSignature = base64_encode($signature);
    $publicKeyText = @file_get_contents(PUBLIC_KEY_PATH);
    $publicKey = $publicKeyText === false ? false : openssl_pkey_get_public($publicKeyText);
    if ($publicKey === false || openssl_verify($json, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('Signing key does not match the public key embedded in the app.');
    }

    $lock = storage_lock(LOCK_EX);
    $suffix = bin2hex(random_bytes(8));
    $jsonTemp = STORAGE_DIR . '/.portal-config.' . $suffix . '.tmp';
    $signatureTemp = STORAGE_DIR . '/.portal-config-sig.' . $suffix . '.tmp';
    try {
        if (file_put_contents($jsonTemp, $json, LOCK_EX) === false ||
            file_put_contents($signatureTemp, $encodedSignature, LOCK_EX) === false) {
            throw new RuntimeException('Could not write updated config.');
        }
        chmod($jsonTemp, 0640);
        chmod($signatureTemp, 0640);
        if (!rename($jsonTemp, JSON_PATH) || !rename($signatureTemp, SIGNATURE_PATH)) {
            throw new RuntimeException('Could not publish updated config.');
        }
    } finally {
        @unlink($jsonTemp);
        @unlink($signatureTemp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
