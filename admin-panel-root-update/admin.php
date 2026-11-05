<?php
declare(strict_types=1);

$commonPath = __DIR__ . '/../app/common.php';
if (!is_file($commonPath)) {
    $commonPath = __DIR__ . '/app/common.php';
}
require $commonPath;

$https = request_is_https();
$panelRoot = is_dir(__DIR__ . '/../private') ? dirname(__DIR__) : __DIR__;
$allowLocalHttp = getenv('AGRI_ALLOW_HTTP_ADMIN') === '1' ||
    is_file($panelRoot . '/private/allow-http-admin.flag');
if (!$https && !$allowLocalHttp) {
    http_response_code(403);
    exit('Admin panel requires HTTPS. For local development only, set AGRI_ALLOW_HTTP_ADMIN=1.');
}

session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

$settings = panel_settings();
$adminUser = getenv('AGRI_ADMIN_USER') ?: (string)($settings['admin_user'] ?? '');
$adminPasswordHash = (string)($settings['admin_password_hash'] ?? '');
$adminPasswordOverride = getenv('AGRI_ADMIN_PASSWORD') ?: '';
$error = '';
$notice = '';
$rateKey = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$rateFile = STORAGE_DIR . '/.login-attempts.json';
$loginWindowSeconds = 15 * 60;
$lockoutSeconds = 15 * 60;

if ($adminUser === '' || ($adminPasswordHash === '' && $adminPasswordOverride === '')) {
    http_response_code(503);
    exit('Run the CLI installer (php-control-panel/install.php) before using the admin panel.');
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

function login_rate_state(string $path, string $key, int $window, int $lockout, bool $failed = false, bool $clear = false): array
{
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Login protection storage is unavailable.');
    }
    @chmod($path, 0600);
    try {
        $contents = stream_get_contents($handle);
        $states = is_string($contents) && $contents !== '' ? json_decode($contents, true) : [];
        if (!is_array($states)) $states = [];
        $now = time();
        foreach ($states as $existingKey => $existingState) {
            if (!is_array($existingState) || (int)($existingState['locked_until'] ?? 0) <= $now &&
                !array_filter($existingState['attempts'] ?? [], static fn($time) => is_int($time) && $time > $now - $window)) {
                unset($states[$existingKey]);
            }
        }
        $state = is_array($states[$key] ?? null) ? $states[$key] : ['attempts' => [], 'locked_until' => 0];
        $attempts = array_values(array_filter($state['attempts'] ?? [], static fn($time) => is_int($time) && $time > $now - $window));
        $lockedUntil = max(0, (int)($state['locked_until'] ?? 0));
        if ($clear) {
            $attempts = [];
            $lockedUntil = 0;
        } elseif ($failed) {
            $attempts[] = $now;
            if (count($attempts) >= 5) { $lockedUntil = $now + $lockout; $attempts = []; }
        }
        if ($lockedUntil <= $now) $lockedUntil = 0;
        if ($attempts || $lockedUntil) $states[$key] = ['attempts' => $attempts, 'locked_until' => $lockedUntil];
        else unset($states[$key]);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($states, JSON_THROW_ON_ERROR));
        fflush($handle);
        return ['locked_until' => $lockedUntil, 'attempts' => count($attempts)];
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function splash_upload_input(string $field, ?int $index = null): array
{
    $upload = $_FILES[$field] ?? [];
    if ($index === null || !is_array($upload['error'] ?? null)) {
        return is_array($upload) ? $upload : [];
    }
    $entry = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
        $entry[$key] = $upload[$key][$index] ?? ($key === 'error' ? UPLOAD_ERR_NO_FILE : '');
    }
    return $entry;
}

function splash_url_from_form(string $url, bool $delete, array $upload, array &$created): string
{
    if ($delete) $url = '';
    $stored = store_splash_upload($upload);
    if ($stored !== null) {
        $created[] = $stored['filename'];
        return $stored['url'];
    }
    if ($url !== '') {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string)$scheme), ['http', 'https'], true) || !parse_url($url, PHP_URL_HOST)) {
            throw new RuntimeException('Splash image URL অবশ্যই সম্পূর্ণ http:// অথবা https:// ঠিকানা হতে হবে।');
        }
    }
    return $url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    try {
        $rateState = login_rate_state($rateFile, $rateKey, $loginWindowSeconds, $lockoutSeconds);
        if ($rateState['locked_until'] > time()) {
            $error = 'বারবার ভুল লগইনের কারণে সাময়িকভাবে বন্ধ। ১৫ মিনিট পরে আবার চেষ্টা করুন।';
        } else {
            $submittedPassword = (string)($_POST['password'] ?? '');
            $passwordMatches = $adminPasswordHash !== ''
                ? password_verify($submittedPassword, $adminPasswordHash)
                : hash_equals($adminPasswordOverride, $submittedPassword);
            if (hash_equals($adminUser, (string)($_POST['username'] ?? '')) && $passwordMatches) {
                login_rate_state($rateFile, $rateKey, $loginWindowSeconds, $lockoutSeconds, false, true);
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            }
            $rateState = login_rate_state($rateFile, $rateKey, $loginWindowSeconds, $lockoutSeconds, true);
            $error = $rateState['locked_until'] > time()
                ? 'বারবার ভুল লগইনের কারণে সাময়িকভাবে বন্ধ। ১৫ মিনিট পরে আবার চেষ্টা করুন।'
                : 'ব্যবহারকারীর নাম বা পাসওয়ার্ড সঠিক নয়।';
        }
    } catch (Throwable $exception) {
        error_log('Portal admin login protection: ' . $exception->getMessage());
        http_response_code(503);
        $error = 'লগইন সুরক্ষা সাময়িকভাবে কাজ করছে না। পরে আবার চেষ্টা করুন।';
    }
}

$authenticated = ($_SESSION['authenticated'] ?? false) === true;
if (!$authenticated) {
    render_login($error);
    exit;
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        $error = 'সেশন যাচাই হয়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $createdSplashImages = [];
        try {
            [$rawConfig] = read_signed_config();
            $config = json_decode($rawConfig, true, 64, JSON_THROW_ON_ERROR);
            $previousConfig = $config;
            $config['app_name'] = trim((string)($_POST['app_name'] ?? ''));
            $config['start_url'] = trim((string)($_POST['start_url'] ?? ''));
            $domainInput = trim((string)($_POST['allowed_domains'] ?? ''));
            $config['allowed_domains'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $domainInput) ?: [])));
            if ($config['start_url'] !== '') {
                $scheme = parse_url($config['start_url'], PHP_URL_SCHEME);
                $host = parse_url($config['start_url'], PHP_URL_HOST);
                if (!in_array(strtolower((string)$scheme), ['http', 'https'], true) || !$host) {
                    throw new RuntimeException('start_url-এ পূর্ণ http:// অথবা https:// ঠিকানা দিন।');
                }
            }

            $config['splash_screen'] = is_array($config['splash_screen'] ?? null) ? $config['splash_screen'] : [];
            $splash = &$config['splash_screen'];
            $splash['enabled'] = isset($_POST['splash_enabled']);
            $splash['fullscreen'] = isset($_POST['splash_fullscreen']);
            $splash['background_color'] = trim((string)($_POST['splash_background_color'] ?? '#10233f'));
            $splash['duration_ms'] = max(500, min(10000, (int)($_POST['splash_duration_ms'] ?? 1200)));
            $splash['image_url'] = splash_url_from_form(
                trim((string)($_POST['splash_image_url'] ?? '')),
                isset($_POST['delete_splash_image']),
                splash_upload_input('splash_image_upload'),
                $createdSplashImages
            );
            if (!preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $splash['background_color'])) {
                throw new RuntimeException('Splash background color must be a valid hex color.');
            }
            $pages = [];
            for ($index = 0; $index < 3; $index++) {
                $imageUrl = splash_url_from_form(
                    trim((string)($_POST['splash_page_image'][$index] ?? '')),
                    isset($_POST['delete_splash_page_image'][$index]),
                    splash_upload_input('splash_page_upload', $index),
                    $createdSplashImages
                );
                $background = trim((string)($_POST['splash_page_background'][$index] ?? $splash['background_color']));
                $duration = max(500, min(10000, (int)($_POST['splash_page_duration'][$index] ?? $splash['duration_ms'])));
                if (!preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $background)) {
                    throw new RuntimeException('Splash page colors must be valid hex colors.');
                }
                $pages[] = ['image_url' => $imageUrl, 'background_color' => $background, 'duration_ms' => $duration];
            }
            $splash['pages'] = $pages;
            // Empty rows are placeholders in the panel. Do not publish them
            // as real slides, otherwise the app shows blank pages between
            // configured splash images.
            $splash['pages'] = array_values(array_filter(
                $pages,
                static fn(array $page): bool => $page['image_url'] !== ''
            ));
            unset($splash);

            $config['onboarding'] = is_array($config['onboarding'] ?? null) ? $config['onboarding'] : [];
            $onboarding = &$config['onboarding'];
            $onboarding['enabled'] = isset($_POST['onboarding_enabled']);
            $onboarding['fullscreen'] = isset($_POST['onboarding_fullscreen']);
            $onboarding['skip_text'] = trim((string)($_POST['onboarding_skip_text'] ?? 'Skip'));
            $onboarding['next_text'] = trim((string)($_POST['onboarding_next_text'] ?? 'Next'));
            $onboarding['started_text'] = trim((string)($_POST['onboarding_started_text'] ?? 'Get Started'));
            $onboardingPages = [];
            for ($index = 0; $index < 4; $index++) {
                $background = trim((string)($_POST['onboarding_background'][$index] ?? '#F7F8FC'));
                $accent = trim((string)($_POST['onboarding_accent'][$index] ?? '#6757D9'));
                foreach ([$background, $accent] as $onboardingColor) {
                    if (!preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $onboardingColor)) {
                        throw new RuntimeException('Onboarding colors must be valid hex colors.');
                    }
                }
                $imageUrl = splash_url_from_form(
                    trim((string)($_POST['onboarding_image'][$index] ?? '')),
                    isset($_POST['delete_onboarding_image'][$index]),
                    splash_upload_input('onboarding_image_upload', $index),
                    $createdSplashImages
                );
                $onboardingPages[] = [
                    'title' => trim((string)($_POST['onboarding_title'][$index] ?? '')),
                    'message' => trim((string)($_POST['onboarding_message'][$index] ?? '')),
                    'image_url' => $imageUrl,
                    'background_color' => $background,
                    'accent_color' => $accent,
                ];
            }
            // Empty slide rows are removed so clearing one slide in the panel
            // really removes it from the app instead of leaving a blank page.
            $onboarding['pages'] = array_values(array_filter(
                $onboardingPages,
                static fn(array $page): bool => $page['title'] !== '' ||
                    $page['message'] !== '' || $page['image_url'] !== ''
            ));
            unset($onboarding);

            $config['ui_colors'] = is_array($config['ui_colors'] ?? null) ? $config['ui_colors'] : [];
            foreach (['status_bar', 'navigation_bar', 'progress', 'refresh', 'accent'] as $colorName) {
                $colorValue = trim((string)($_POST['color_' . $colorName] ?? ($config['ui_colors'][$colorName] ?? '#10233f')));
                if (!preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $colorValue)) {
                    throw new RuntimeException('UI colors must be valid hex colors.');
                }
                $config['ui_colors'][$colorName] = $colorValue;
            }

            $config['maintenance_mode'] = is_array($config['maintenance_mode'] ?? null) ? $config['maintenance_mode'] : [];
            $config['maintenance_mode']['enabled'] = isset($_POST['maintenance_enabled']);
            $config['maintenance_mode']['title'] = trim((string)($_POST['maintenance_title'] ?? ''));
            $config['maintenance_mode']['message'] = trim((string)($_POST['maintenance_message'] ?? ''));
            $config['maintenance_mode']['icon'] = trim((string)($_POST['maintenance_icon'] ?? '🛠️'));
            $config['maintenance_mode']['retry_enabled'] = isset($_POST['maintenance_retry_enabled']);

            $config['security_policies'] = is_array($config['security_policies'] ?? null) ? $config['security_policies'] : [];
            foreach (['https_only', 'block_screenshots', 'disable_selection', 'allow_external_links', 'inspect_links'] as $flag) {
                $config['security_policies'][$flag] = isset($_POST['security_' . $flag]);
            }
            foreach (['root_detection' => ['off', 'warn'], 'emulator_detection' => ['off', 'warn'], 'debugger_detection' => ['off', 'block']] as $field => $allowedValues) {
                $value = (string)($_POST[$field] ?? $allowedValues[0]);
                if (!in_array($value, $allowedValues, true)) {
                    throw new RuntimeException('Invalid security policy value.');
                }
                $config['security_policies'][$field] = $value;
            }
            $config['popups_and_notifications'] = is_array($config['popups_and_notifications'] ?? null) ? $config['popups_and_notifications'] : [];
            $config['popups_and_notifications']['welcome_popup'] = is_array($config['popups_and_notifications']['welcome_popup'] ?? null)
                ? $config['popups_and_notifications']['welcome_popup'] : [];
            $config['popups_and_notifications']['welcome_popup']['enabled'] = isset($_POST['welcome_enabled']);
            $config['popups_and_notifications']['welcome_popup']['title'] = trim((string)($_POST['welcome_title'] ?? ''));
            $config['popups_and_notifications']['welcome_popup']['message'] = trim((string)($_POST['welcome_message'] ?? ''));
            $config['popups_and_notifications']['welcome_popup']['button_text'] = trim((string)($_POST['welcome_button_text'] ?? 'ঠিক আছে'));
            $config['popups_and_notifications']['welcome_popup']['image_url'] = splash_url_from_form(
                trim((string)($_POST['welcome_image_url'] ?? '')),
                isset($_POST['delete_welcome_image']),
                splash_upload_input('welcome_image_upload'),
                $createdSplashImages
            );

            $config['popups_and_notifications']['push_notifications'] = is_array($config['popups_and_notifications']['push_notifications'] ?? null)
                ? $config['popups_and_notifications']['push_notifications'] : [];
            $push = &$config['popups_and_notifications']['push_notifications'];
            $provider = strtolower(trim((string)($_POST['push_provider'] ?? 'none')));
            if (!in_array($provider, ['none', 'firebase', 'onesignal', 'both'], true)) {
                throw new RuntimeException('Push provider সঠিক নয়।');
            }
            $push['enabled'] = isset($_POST['push_enabled']);
            $push['provider'] = $provider;
            $push['onesignal_app_id'] = trim((string)($_POST['onesignal_app_id'] ?? ''));
            $push['firebase_project_id'] = trim((string)($_POST['firebase_project_id'] ?? ''));
            $push['firebase_application_id'] = trim((string)($_POST['firebase_application_id'] ?? ''));
            $push['firebase_api_key'] = trim((string)($_POST['firebase_api_key'] ?? ''));
            $push['firebase_sender_id'] = trim((string)($_POST['firebase_sender_id'] ?? ''));
            $push['channel_id'] = preg_replace('/[^A-Za-z0-9_.-]/', '_', trim((string)($_POST['push_channel_id'] ?? 'portal_updates'))) ?: 'portal_updates';
            $push['channel_name'] = trim((string)($_POST['push_channel_name'] ?? 'Portal updates')) ?: 'Portal updates';
            $push['request_permission'] = isset($_POST['push_request_permission']);
            unset($push);

            $pushCredentials = read_push_credentials();
            $oneSignalKey = trim((string)($_POST['onesignal_api_key'] ?? ''));
            if ($oneSignalKey !== '') $pushCredentials['onesignal_api_key'] = $oneSignalKey;
            if (isset($_POST['clear_onesignal_api_key'])) unset($pushCredentials['onesignal_api_key']);
            $firebaseServiceAccount = trim((string)($_POST['firebase_service_account_json'] ?? ''));
            if ($firebaseServiceAccount !== '') {
                $serviceAccount = json_decode($firebaseServiceAccount, true);
                if (!is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
                    throw new RuntimeException('Firebase service account JSON অসম্পূর্ণ।');
                }
                $pushCredentials['firebase_service_account_json'] = $firebaseServiceAccount;
            }
            if (isset($_POST['clear_firebase_service_account'])) unset($pushCredentials['firebase_service_account_json']);

            $updatedJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            save_signed_config($updatedJson);
            save_push_credentials($pushCredentials);
            remove_unreferenced_splash_images($previousConfig, $config);
            $notice = 'সেটিং ও push provider config সংরক্ষিত হয়েছে। অ্যাপ কয়েক সেকেন্ডের মধ্যে public config নেবে।';
        } catch (Throwable $exception) {
            foreach ($createdSplashImages as $filename) @unlink(SPLASH_IMAGES_DIR . '/' . $filename);
            $error = 'সংরক্ষণ করা যায়নি: ' . $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_notification') {
    if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        $error = 'সেশন যাচাই হয়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        try {
            [$rawConfig] = read_signed_config();
            $config = json_decode($rawConfig, true, 64, JSON_THROW_ON_ERROR);
            $push = $config['popups_and_notifications']['push_notifications'] ?? [];
            if (!is_array($push) || empty($push['enabled'])) throw new RuntimeException('আগে Push notification চালু করুন।');
            $title = trim((string)($_POST['notification_title'] ?? ''));
            $message = trim((string)($_POST['notification_message'] ?? ''));
            $imageUrl = trim((string)($_POST['notification_image_url'] ?? ''));
            $clickUrl = trim((string)($_POST['notification_click_url'] ?? ''));
            if ($title === '' || $message === '') throw new RuntimeException('Notification title ও message আবশ্যক।');
            foreach (['image URL' => $imageUrl, 'click URL' => $clickUrl] as $label => $url) {
                if ($url !== '' && (!in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https'], true) || !parse_url($url, PHP_URL_HOST))) {
                    throw new RuntimeException($label . ' অবশ্যই HTTPS URL হতে হবে।');
                }
            }
            $provider = strtolower((string)($push['provider'] ?? 'none'));
            $credentials = read_push_credentials();
            $results = [];
            $channelId = (string)($push['channel_id'] ?? 'portal_updates');
            if ($provider === 'onesignal' || $provider === 'both') {
                $results[] = send_onesignal_push($credentials, (string)($push['onesignal_app_id'] ?? ''), $title, $message, $imageUrl, $clickUrl, $channelId);
            }
            if ($provider === 'firebase' || $provider === 'both') {
                $results[] = send_firebase_push($credentials, (string)($push['firebase_project_id'] ?? ''), $title, $message, $imageUrl, $clickUrl, $channelId);
            }
            if (!$results) throw new RuntimeException('একটি active provider নির্বাচন করুন।');
            $notice = implode(' | ', $results);
        } catch (Throwable $exception) {
            $error = 'Notification পাঠানো যায়নি: ' . $exception->getMessage();
        }
    }
}

try {
    [$currentJson] = read_signed_config();
    $current = json_decode($currentJson, true, 64, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $current = [];
    $error = 'বর্তমান config পড়া যাচ্ছে না: ' . $exception->getMessage();
}

$maintenance = $current['maintenance_mode'] ?? [];
$security = $current['security_policies'] ?? [];
$splash = $current['splash_screen'] ?? [];
$colors = $current['ui_colors'] ?? [];
$welcome = $current['popups_and_notifications']['welcome_popup'] ?? [];
$push = $current['popups_and_notifications']['push_notifications'] ?? [];
$push = is_array($push) ? $push : [];
$pushCredentials = read_push_credentials();
$notifications = $current['popups_and_notifications'] ?? [];
$domains = $current['allowed_domains'] ?? [];
$splashPages = $splash['pages'] ?? [];
for ($index = 0; $index < 3; $index++) {
    $splashPages[$index] = $splashPages[$index] ?? [
        'image_url' => '', 'background_color' => $splash['background_color'] ?? '#10233f',
        'duration_ms' => $splash['duration_ms'] ?? 1200,
    ];
}
$onboarding = $current['onboarding'] ?? [];
$onboardingPages = $onboarding['pages'] ?? [];
for ($index = 0; $index < 4; $index++) {
    $onboardingPages[$index] = $onboardingPages[$index] ?? [
        'title' => '', 'message' => '', 'image_url' => '',
        'background_color' => '#F7F8FC', 'accent_color' => '#6757D9',
    ];
}
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Portal Admin</title>
  <style>
    *{box-sizing:border-box}body{margin:0;padding:24px;background:#f3f7fa;color:#10233f;font:16px system-ui,"Noto Sans Bengali",sans-serif}
    main{max-width:920px;margin:24px auto;padding:clamp(20px,5vw,36px);background:#fff;border:1px solid #e2eaf0;border-radius:18px;box-shadow:0 18px 55px #10233f12}
    h1{margin:0 0 8px}h2{margin:28px 0 12px;font-size:19px}p{color:#526276;line-height:1.6}.row{margin:16px 0}
    label{display:block;margin-bottom:7px;font-weight:700}input[type=text],input[type=url],input[type=number],textarea,select{width:100%;padding:12px;border:1px solid #cbd8e1;border-radius:9px;font:inherit;color:#10233f}
    textarea{min-height:96px;resize:vertical}.check{display:flex;align-items:center;gap:9px;margin:14px 0}.check label{margin:0}
    button{padding:13px 20px;border:0;border-radius:9px;background:#36c6b4;color:#062322;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer}
    .message{padding:12px 14px;border-radius:9px;background:#e8f8f0;color:#176344}.error{background:#fff0ef;color:#9b2720}.top{display:flex;justify-content:space-between;gap:14px;align-items:center}.top a{color:#075e57}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.card{padding:14px;border:1px solid #e2eaf0;border-radius:12px;margin:12px 0}.hint{font-size:13px;color:#66778a}.check input{width:18px;height:18px}
    .preview{display:block;max-width:240px;max-height:150px;object-fit:contain;margin:10px 0;border:1px solid #e2eaf0;border-radius:8px}.upload{padding:10px;background:#f8fafc;border:1px dashed #cbd8e1;border-radius:9px}
  </style>
</head>
<body><main>
  <div class="top"><h1>Portal Admin</h1><a href="?logout=1">লগ আউট</a></div>
  <p>এখানে পরিবর্তন সংরক্ষণ করলে JSON signature সার্ভার নিজে তৈরি করবে।</p>
  <?php if ($notice !== ''): ?><p class="message"><?= h($notice) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="message error"><?= h($error) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h((string)$_SESSION['csrf']) ?>">
    <h2>পোর্টাল</h2>
    <div class="row"><label for="app_name">অ্যাপের নাম</label><input id="app_name" name="app_name" type="text" maxlength="120" value="<?= h((string)($current['app_name'] ?? '')) ?>"></div>
    <div class="row"><label for="start_url">Portal URL</label><input id="start_url" name="start_url" type="url" value="<?= h((string)($current['start_url'] ?? '')) ?>" placeholder="https://example.com/"></div>
    <div class="row"><label for="allowed_domains">Allowed domains (প্রতি লাইনে একটি)</label><textarea id="allowed_domains" name="allowed_domains"><?= h(implode("\n", is_array($domains) ? $domains : [])) ?></textarea></div>
    <h2>Splash screen</h2>
    <div class="grid">
      <div class="check"><input id="splash_enabled" name="splash_enabled" type="checkbox" value="1"<?= !empty($splash['enabled']) ? ' checked' : '' ?>><label for="splash_enabled">Splash screen চালু</label></div>
      <div class="check"><input id="splash_fullscreen" name="splash_fullscreen" type="checkbox" value="1"<?= !empty($splash['fullscreen']) ? ' checked' : '' ?>><label for="splash_fullscreen">Fullscreen splash</label></div>
      <div class="row"><label for="splash_background_color">Splash background</label><input id="splash_background_color" name="splash_background_color" type="text" value="<?= h((string)($splash['background_color'] ?? '#10233f')) ?>"></div>
      <div class="row"><label for="splash_duration_ms">Default duration (ms)</label><input id="splash_duration_ms" name="splash_duration_ms" type="number" min="500" max="10000" value="<?= h((string)($splash['duration_ms'] ?? 1200)) ?>"></div>
      <div class="row"><label for="splash_image_url">Fallback splash image</label><input id="splash_image_url" name="splash_image_url" type="url" value="<?= h((string)($splash['image_url'] ?? '')) ?>" placeholder="বাইরের image URL (ঐচ্ছিক)">
        <div class="upload"><label for="splash_image_upload">ছবি upload করুন</label><input id="splash_image_upload" name="splash_image_upload" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPEG, PNG বা WebP; সর্বোচ্চ ৫ MB। Upload করলে URL-এর ছবিটি বদলে যাবে।</div></div>
        <?php if (!empty($splash['image_url'])): ?><img class="preview" src="<?= h((string)$splash['image_url']) ?>" alt="Fallback splash image"><div class="check"><input id="delete_splash_image" name="delete_splash_image" type="checkbox" value="1"><label for="delete_splash_image">Fallback ছবি মুছে ফেলুন</label></div><?php endif; ?>
      </div>
    </div>
    <?php for ($index = 0; $index < 3; $index++): $page = $splashPages[$index]; ?>
      <div class="card"><strong>Splash page <?= $index + 1 ?></strong>
        <div class="grid">
          <div class="row"><label>ছবির URL (ঐচ্ছিক)</label><input name="splash_page_image[<?= $index ?>]" type="url" value="<?= h((string)($page['image_url'] ?? '')) ?>">
            <div class="upload"><label for="splash_page_upload_<?= $index ?>">Splash page <?= $index + 1 ?>-এর ছবি upload</label><input id="splash_page_upload_<?= $index ?>" name="splash_page_upload[<?= $index ?>]" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPEG, PNG বা WebP; সর্বোচ্চ ৫ MB।</div></div>
            <?php if (!empty($page['image_url'])): ?><img class="preview" src="<?= h((string)$page['image_url']) ?>" alt="Splash page <?= $index + 1 ?> image"><div class="check"><input id="delete_splash_page_image_<?= $index ?>" name="delete_splash_page_image[<?= $index ?>]" type="checkbox" value="1"><label for="delete_splash_page_image_<?= $index ?>">এই পেজের ছবি মুছুন</label></div><?php endif; ?>
          </div>
          <div class="row"><label>Background color</label><input name="splash_page_background[<?= $index ?>]" type="text" value="<?= h((string)($page['background_color'] ?? '#10233f')) ?>"></div>
          <div class="row"><label>Duration (ms)</label><input name="splash_page_duration[<?= $index ?>]" type="number" min="500" max="10000" value="<?= h((string)($page['duration_ms'] ?? 1200)) ?>"></div>
        </div>
      </div>
    <?php endfor; ?>
    <h2>Onboarding slider</h2>
    <p class="hint">App launch-এর সময় দেখানো ৪টি পর্যন্ত slide। URL বা সরাসরি JPEG, PNG অথবা WebP ছবি ব্যবহার করতে পারবেন। ছবি না দিলে native illustration দেখাবে।</p>
    <div class="grid">
      <div class="check"><input id="onboarding_enabled" name="onboarding_enabled" type="checkbox" value="1"<?= !empty($onboarding['enabled']) ? ' checked' : '' ?>><label for="onboarding_enabled">Onboarding চালু</label></div>
      <div class="check"><input id="onboarding_fullscreen" name="onboarding_fullscreen" type="checkbox" value="1"<?= !empty($onboarding['fullscreen']) ? ' checked' : '' ?>><label for="onboarding_fullscreen">Fullscreen onboarding</label></div>
      <div class="row"><label for="onboarding_skip_text">Skip button</label><input id="onboarding_skip_text" name="onboarding_skip_text" type="text" maxlength="30" value="<?= h((string)($onboarding['skip_text'] ?? 'Skip')) ?>"></div>
      <div class="row"><label for="onboarding_next_text">Next button</label><input id="onboarding_next_text" name="onboarding_next_text" type="text" maxlength="30" value="<?= h((string)($onboarding['next_text'] ?? 'Next')) ?>"></div>
      <div class="row"><label for="onboarding_started_text">শেষ slide button</label><input id="onboarding_started_text" name="onboarding_started_text" type="text" maxlength="40" value="<?= h((string)($onboarding['started_text'] ?? 'Get Started')) ?>"></div>
    </div>
    <?php for ($index = 0; $index < 4; $index++): $page = $onboardingPages[$index]; ?>
      <div class="card"><strong>Onboarding slide <?= $index + 1 ?></strong>
        <div class="grid">
          <div class="row"><label>Title</label><input name="onboarding_title[<?= $index ?>]" type="text" maxlength="120" value="<?= h((string)($page['title'] ?? '')) ?>"></div>
          <div class="row"><label>Message</label><textarea name="onboarding_message[<?= $index ?>]" maxlength="300"><?= h((string)($page['message'] ?? '')) ?></textarea></div>
          <div class="row"><label>Illustration image (optional)</label><input name="onboarding_image[<?= $index ?>]" type="url" value="<?= h((string)($page['image_url'] ?? '')) ?>" placeholder="বাইরের image URL (ঐচ্ছিক)">
            <div class="upload"><label for="onboarding_image_upload_<?= $index ?>">Onboarding slide <?= $index + 1 ?>-এর ছবি upload</label><input id="onboarding_image_upload_<?= $index ?>" name="onboarding_image_upload[<?= $index ?>]" type="file" accept="image/jpeg,image/png,image/webp" data-preview-target="onboarding_image_preview_<?= $index ?>" data-preview-name="onboarding_image_name_<?= $index ?>" maxlength="5242880"><div class="hint">JPEG, PNG বা WebP; সর্বোচ্চ ৫ MB। Upload করলে URL-এর ছবিটি বদলে যাবে।</div><div id="onboarding_image_name_<?= $index ?>" class="hint" aria-live="polite"></div></div>
            <?php if (!empty($page['image_url'])): ?><img id="onboarding_image_preview_<?= $index ?>" class="preview" src="<?= h((string)$page['image_url']) ?>" alt="Onboarding slide <?= $index + 1 ?> image"><div class="check"><input id="delete_onboarding_image_<?= $index ?>" name="delete_onboarding_image[<?= $index ?>]" type="checkbox" value="1"><label for="delete_onboarding_image_<?= $index ?>">এই slide-এর ছবি মুছুন</label></div><?php else: ?><img id="onboarding_image_preview_<?= $index ?>" class="preview" alt="Onboarding slide <?= $index + 1 ?> preview" hidden><?php endif; ?>
          </div>
          <div class="row"><label>Background color</label><input name="onboarding_background[<?= $index ?>]" type="text" value="<?= h((string)($page['background_color'] ?? '#F7F8FC')) ?>"></div>
          <div class="row"><label>Accent color</label><input name="onboarding_accent[<?= $index ?>]" type="text" value="<?= h((string)($page['accent_color'] ?? '#6757D9')) ?>"></div>
        </div>
      </div>
    <?php endfor; ?>
    <h2>UI colors</h2>
    <div class="grid">
      <?php foreach (['status_bar' => 'Status bar', 'navigation_bar' => 'Navigation bar', 'progress' => 'Progress bar', 'refresh' => 'Retry / refresh buttons', 'accent' => 'Accent'] as $colorName => $colorLabel): ?>
      <div class="row"><label for="color_<?= h($colorName) ?>"><?= h($colorLabel) ?> color</label><input id="color_<?= h($colorName) ?>" name="color_<?= h($colorName) ?>" type="text" value="<?= h((string)($colors[$colorName] ?? '#10233f')) ?>"></div>
      <?php endforeach; ?>
    </div>
    <h2>Maintenance mode</h2>
    <div class="check"><input id="maintenance_enabled" name="maintenance_enabled" type="checkbox" value="1"<?= !empty($maintenance['enabled']) ? ' checked' : '' ?>><label for="maintenance_enabled">Maintenance mode চালু</label></div>
    <div class="row"><label for="maintenance_title">শিরোনাম</label><input id="maintenance_title" name="maintenance_title" type="text" value="<?= h((string)($maintenance['title'] ?? 'We’ll be back soon')) ?>"></div>
    <div class="row"><label for="maintenance_message">বার্তা</label><textarea id="maintenance_message" name="maintenance_message"><?= h((string)($maintenance['message'] ?? 'Please try again shortly.')) ?></textarea></div>
    <div class="grid"><div class="row"><label for="maintenance_icon">Icon / emoji</label><input id="maintenance_icon" name="maintenance_icon" type="text" value="<?= h((string)($maintenance['icon'] ?? '🛠️')) ?>"></div>
      <div class="check"><input id="maintenance_retry_enabled" name="maintenance_retry_enabled" type="checkbox" value="1"<?= !empty($maintenance['retry_enabled']) ? ' checked' : '' ?>><label for="maintenance_retry_enabled">Retry button দেখান</label></div></div>
    <h2>Security policies</h2>
    <div class="grid">
      <?php foreach (['https_only' => 'শুধু HTTPS', 'block_screenshots' => 'Screenshot block', 'disable_selection' => 'Text selection বন্ধ', 'allow_external_links' => 'External links অনুমোদন', 'inspect_links' => 'Link inspection'] as $flag => $label): ?>
      <div class="check"><input id="security_<?= h($flag) ?>" name="security_<?= h($flag) ?>" type="checkbox" value="1"<?= !empty($security[$flag]) ? ' checked' : '' ?>><label for="security_<?= h($flag) ?>"><?= h($label) ?></label></div>
      <?php endforeach; ?>
      <?php foreach (['root_detection' => ['Root detection', ['off', 'warn']], 'emulator_detection' => ['Emulator detection', ['off', 'warn']], 'debugger_detection' => ['Debugger detection', ['off', 'block']]] as $field => [$label, $options]): ?>
      <div class="row"><label for="<?= h($field) ?>"><?= h($label) ?></label><select id="<?= h($field) ?>" name="<?= h($field) ?>"><?php foreach ($options as $option): ?><option value="<?= h($option) ?>"<?= ($security[$field] ?? $options[0]) === $option ? ' selected' : '' ?>><?= h(strtoupper($option)) ?></option><?php endforeach; ?></select></div>
      <?php endforeach; ?>
    </div>
    <p class="hint">SSL pinning / certificate pins এই WebView build-এ অপরিবর্তিত রাখা হয়েছে। Push provider-এর secret এই JSON বা APK-তে রাখা হয় না; private server config-এ থাকে।</p>
    <h2>Welcome popup</h2>
    <div class="check"><input id="welcome_enabled" name="welcome_enabled" type="checkbox" value="1"<?= !empty($welcome['enabled']) ? ' checked' : '' ?>><label for="welcome_enabled">Welcome popup চালু</label></div>
    <div class="row"><label for="welcome_title">শিরোনাম</label><input id="welcome_title" name="welcome_title" type="text" value="<?= h((string)($welcome['title'] ?? 'Welcome')) ?>"></div>
    <div class="row"><label for="welcome_message">বার্তা</label><textarea id="welcome_message" name="welcome_message"><?= h((string)($welcome['message'] ?? 'Welcome back.')) ?></textarea></div>
    <div class="row"><label for="welcome_button_text">বাটনের লেখা</label><input id="welcome_button_text" name="welcome_button_text" type="text" value="<?= h((string)($welcome['button_text'] ?? 'ঠিক আছে')) ?>"></div>
    <div class="row"><label for="welcome_image_url">Popup ছবি (ঐচ্ছিক)</label><input id="welcome_image_url" name="welcome_image_url" type="url" value="<?= h((string)($welcome['image_url'] ?? '')) ?>" placeholder="বাইরের image URL (ঐচ্ছিক)">
      <div class="upload"><label for="welcome_image_upload">Popup ছবি upload করুন</label><input id="welcome_image_upload" name="welcome_image_upload" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPEG, PNG বা WebP; সর্বোচ্চ ৫ MB। ছবির অনুপাত বজায় রেখে app-এ সর্বোচ্চ 220dp উচ্চতায় দেখানো হবে।</div></div>
      <?php if (!empty($welcome['image_url'])): ?><img class="preview" src="<?= h((string)$welcome['image_url']) ?>" alt="Welcome popup image"><div class="check"><input id="delete_welcome_image" name="delete_welcome_image" type="checkbox" value="1"><label for="delete_welcome_image">Popup ছবি মুছে ফেলুন</label></div><?php endif; ?>
    </div>
    <h2>Push notifications</h2>
    <div class="check"><input id="push_enabled" name="push_enabled" type="checkbox" value="1"<?= !empty($push['enabled']) ? ' checked' : '' ?>><label for="push_enabled">Push notification চালু</label></div>
    <div class="grid">
      <div class="row"><label for="push_provider">Active provider</label><select id="push_provider" name="push_provider"><option value="none"<?= ($push['provider'] ?? 'none') === 'none' ? ' selected' : '' ?>>NONE</option><option value="firebase"<?= ($push['provider'] ?? '') === 'firebase' ? ' selected' : '' ?>>FIREBASE</option><option value="onesignal"<?= ($push['provider'] ?? '') === 'onesignal' ? ' selected' : '' ?>>ONESIGNAL</option><option value="both"<?= ($push['provider'] ?? '') === 'both' ? ' selected' : '' ?>>BOTH</option></select></div>
      <div class="row"><label for="push_channel_id">Android channel ID</label><input id="push_channel_id" name="push_channel_id" maxlength="80" value="<?= h((string)($push['channel_id'] ?? 'portal_updates')) ?>"></div>
      <div class="row"><label for="push_channel_name">Android channel name</label><input id="push_channel_name" name="push_channel_name" maxlength="80" value="<?= h((string)($push['channel_name'] ?? 'Portal updates')) ?>"></div>
      <div class="check"><input id="push_request_permission" name="push_request_permission" type="checkbox" value="1"<?= !isset($push['request_permission']) || !empty($push['request_permission']) ? ' checked' : '' ?>><label for="push_request_permission">Notification permission চাইবে</label></div>
    </div>
    <div class="card"><strong>OneSignal public settings</strong>
      <div class="grid"><div class="row"><label>App ID</label><input name="onesignal_app_id" maxlength="80" value="<?= h((string)($push['onesignal_app_id'] ?? '')) ?>"></div>
      <div class="row"><label>Server API key</label><input name="onesignal_api_key" type="password" autocomplete="new-password" placeholder="<?= !empty($pushCredentials['onesignal_api_key']) ? 'সংরক্ষিত আছে; বদলাতে নতুন key দিন' : 'শুধু server-এ রাখুন' ?>"></div></div>
      <?php if (!empty($pushCredentials['onesignal_api_key'])): ?><div class="check"><input name="clear_onesignal_api_key" type="checkbox" value="1"><label>সংরক্ষিত OneSignal API key মুছে ফেলুন</label></div><?php endif; ?>
    </div>
    <div class="card"><strong>Firebase public settings</strong>
      <div class="grid"><div class="row"><label>Project ID</label><input name="firebase_project_id" maxlength="120" value="<?= h((string)($push['firebase_project_id'] ?? '')) ?>"></div>
      <div class="row"><label>Application ID</label><input name="firebase_application_id" maxlength="180" value="<?= h((string)($push['firebase_application_id'] ?? '')) ?>"></div>
      <div class="row"><label>API key</label><input name="firebase_api_key" maxlength="240" value="<?= h((string)($push['firebase_api_key'] ?? '')) ?>"></div>
      <div class="row"><label>Sender ID</label><input name="firebase_sender_id" maxlength="80" value="<?= h((string)($push['firebase_sender_id'] ?? '')) ?>"></div></div>
      <div class="row"><label>Firebase service account JSON</label><textarea name="firebase_service_account_json" rows="5" placeholder="<?= !empty($pushCredentials['firebase_service_account_json']) ? 'সংরক্ষিত আছে; বদলাতে নতুন JSON দিন' : 'Private service account JSON এখানে দিন' ?>"></textarea></div>
      <?php if (!empty($pushCredentials['firebase_service_account_json'])): ?><div class="check"><input name="clear_firebase_service_account" type="checkbox" value="1"><label>সংরক্ষিত Firebase service account মুছে ফেলুন</label></div><?php endif; ?>
    </div>
    <div class="card"><strong>Send notification</strong>
      <div class="grid"><div class="row"><label>Title</label><input name="notification_title" maxlength="120"></div>
      <div class="row"><label>Message</label><textarea name="notification_message" maxlength="1000"></textarea></div>
      <div class="row"><label>Image URL (HTTPS)</label><input name="notification_image_url" type="url"></div>
      <div class="row"><label>Click URL (HTTPS)</label><input name="notification_click_url" type="url"></div></div>
      <button type="submit" name="action" value="send_notification">Send notification</button>
    </div>
    <button type="submit" name="action" value="save">Save changes</button>
  </form>
</main><script>
document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    var preview = document.getElementById(input.dataset.previewTarget);
    var name = document.getElementById(input.dataset.previewName);
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      input.value = '';
      if (name) name.textContent = 'ছবির আকার ৫ MB-এর বেশি হতে পারবে না।';
      if (preview) { preview.removeAttribute('src'); preview.hidden = true; }
      return;
    }
    if (name) name.textContent = 'নির্বাচিত: ' + file.name;
    if (preview && window.URL) {
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
    }
  });
});
</script></body></html>

<?php
function render_login(string $error): void
{
    ?>
    <!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Portal Admin Login</title>
    <style>*{box-sizing:border-box}body{margin:0;padding:24px;background:#f3f7fa;color:#10233f;font:16px system-ui,"Noto Sans Bengali",sans-serif}main{max-width:440px;margin:10vh auto;padding:30px;background:#fff;border:1px solid #e2eaf0;border-radius:16px}label{display:block;margin:16px 0 6px;font-weight:700}input{width:100%;padding:12px;border:1px solid #cbd8e1;border-radius:8px;font:inherit}button{margin-top:20px;padding:12px 18px;border:0;border-radius:8px;background:#36c6b4;font-size:16px;font-weight:700;font-family:inherit}.error{color:#9b2720}</style></head><body><main>
    <h1>Portal Admin</h1><p>সাইন ইন করুন। ১৫ মিনিটে ৫ বার ভুল হলে ১৫ মিনিটের জন্য লগইন বন্ধ থাকবে।</p><?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <form method="post"><input type="hidden" name="action" value="login"><label for="username">ব্যবহারকারীর নাম</label><input id="username" name="username" autocomplete="username" required>
    <label for="password">পাসওয়ার্ড</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">সাইন ইন</button></form>
    </main></body></html>
    <?php
}
