<?php
declare(strict_types=1);

$panelRoot = is_dir(__DIR__ . '/../private') ? dirname(__DIR__) : __DIR__;
$commonPath = $panelRoot . '/app/common.php';
require $commonPath;

define('INSTALL_SETTINGS', $panelRoot . '/private/config.php');
define('INSTALL_LOCK', $panelRoot . '/private/.install.lock');
define('INSTALL_TOKEN_FILE', $panelRoot . '/private/.install-token');
define('DEFAULT_SIGNING_KEY', $panelRoot . '/private/config-signing-private.pem');

function ensure_install_token(): string
{
    $privateDir = dirname(INSTALL_SETTINGS);
    if (!is_dir($privateDir) && !mkdir($privateDir, 0700, true) && !is_dir($privateDir)) {
        throw new RuntimeException('private/ folder তৈরি করা যায়নি। Panel root-এর permissions পরীক্ষা করুন।');
    }
    @chmod($privateDir, 0700);
    if (!is_file(INSTALL_TOKEN_FILE)) {
        $candidate = bin2hex(random_bytes(32));
        $handle = @fopen(INSTALL_TOKEN_FILE, 'x');
        if ($handle !== false) {
            fwrite($handle, $candidate . "\n");
            fflush($handle);
            fclose($handle);
            @chmod(INSTALL_TOKEN_FILE, 0600);
        }
    }
    $token = @file_get_contents(INSTALL_TOKEN_FILE);
    if ($token === false || strlen(trim($token)) < 24) {
        throw new RuntimeException('Auto-generated install token পড়া যাচ্ছে না। cPanel-এ private/ folder-এর permissions ঠিক করুন।');
    }
    return trim($token);
}

function installer_locked(): bool
{
    return is_file(INSTALL_SETTINGS);
}

function installer_page(string $message = '', string $username = '', string $keyPath = ''): never
{
    http_response_code($message === '' ? 200 : 400);
    ?>
    <!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Portal Admin Setup</title>
    <style>*{box-sizing:border-box}body{margin:0;padding:24px;background:#f3f7fa;color:#10233f;font:16px system-ui,"Noto Sans Bengali",sans-serif}main{max-width:540px;margin:6vh auto;padding:30px;background:#fff;border:1px solid #e2eaf0;border-radius:16px}label{display:block;margin:16px 0 6px;font-weight:700}input{width:100%;padding:12px;border:1px solid #cbd8e1;border-radius:8px;font:inherit}button{margin-top:20px;padding:12px 18px;border:0;border-radius:8px;background:#36c6b4;font-size:16px;font-weight:700;font-family:inherit}.error{padding:12px;background:#fff0ef;color:#9b2720;border-radius:8px}.hint{color:#526276;font-size:14px;line-height:1.5}</style></head><body><main>
    <h1>Portal Admin Setup</h1><p>একবারের সেটআপ। ইনস্টল হলে এই পেজ স্থায়ীভাবে lock হবে।</p>
    <?php if ($message !== ''): ?><p class="error"><?= h($message) ?></p><?php endif; ?>
    <p class="hint">Installer স্বয়ংক্রিয়ভাবে token তৈরি করে <?= h(INSTALL_TOKEN_FILE) ?>-এ রেখেছে। cPanel File Manager দিয়ে ফাইলটি খুলে token কপি করুন। Signing key-এর path স্বয়ংক্রিয়ভাবে নির্ধারিত হয়।</p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h((string)($_SESSION['install_csrf'] ?? '')) ?>">
      <label for="token">একবারের install token</label><input id="token" name="token" type="password" required>
      <label for="username">Admin username</label><input id="username" name="username" value="<?= h($username) ?>" maxlength="120" required>
      <label for="password">Admin password (কমপক্ষে ১৪ অক্ষর)</label><input id="password" name="password" type="password" minlength="14" autocomplete="new-password" required>
      <label for="password_confirm">Password আবার লিখুন</label><input id="password_confirm" name="password_confirm" type="password" minlength="14" autocomplete="new-password" required>
      <button type="submit">ইনস্টল করুন</button>
    </form></main></body></html>
    <?php
    exit;
}

$https = request_is_https();
if (!$https && getenv('AGRI_ALLOW_HTTP_ADMIN') !== '1' && !is_file($panelRoot . '/private/allow-http-admin.flag')) {
    http_response_code(403);
    exit('Installer requires HTTPS. Local development only: set AGRI_ALLOW_HTTP_ADMIN=1.');
}
if (installer_locked()) {
    http_response_code(404);
    exit('Not found.');
}
try {
    $installToken = ensure_install_token();
} catch (Throwable $error) {
    http_response_code(503);
    exit(h($error->getMessage()));
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Strict', 'path' => '/']);
    session_start();
}
if (!isset($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') installer_page();
if (!hash_equals((string)$_SESSION['install_csrf'], (string)($_POST['csrf'] ?? ''))) installer_page('সেশন যাচাই হয়নি, পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
if (!hash_equals($installToken, (string)($_POST['token'] ?? ''))) installer_page('Install token সঠিক নয়।');

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$passwordConfirm = (string)($_POST['password_confirm'] ?? '');
$keyInput = DEFAULT_SIGNING_KEY;
if ($username === '' || strlen($username) > 120 || preg_match('/[\r\n]/', $username)) installer_page('একটি বৈধ admin username দিন।', $username, $keyInput);
if ((function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password)) < 14) installer_page('Admin password কমপক্ষে ১৪ অক্ষরের হতে হবে।', $username, $keyInput);
if (!hash_equals($password, $passwordConfirm)) installer_page('দুটি password মেলেনি।', $username, $keyInput);
if (PHP_VERSION_ID < 80100 || !extension_loaded('openssl') || !extension_loaded('fileinfo')) installer_page('PHP 8.1+, OpenSSL এবং fileinfo extensions প্রয়োজন।', $username, $keyInput);

$keyPath = realpath($keyInput);
$publicDir = realpath(__DIR__);
$privateKeyInProtectedPrivateDir = $keyPath !== false &&
    $keyPath === realpath($panelRoot . '/private/config-signing-private.pem') &&
    is_file($panelRoot . '/private/.htaccess');
$keyText = $keyPath !== false ? @file_get_contents($keyPath) : false;
$privateKey = $keyText === false ? false : openssl_pkey_get_private($keyText);
if ($keyPath === false || $privateKey === false || $publicDir === false ||
    (str_starts_with($keyPath, $publicDir . DIRECTORY_SEPARATOR) && !$privateKeyInProtectedPrivateDir)) {
    installer_page('Signing key পড়া যাচ্ছে না, invalid, অথবা public web directory-এর ভেতরে আছে।', $username, $keyInput);
}
$details = openssl_pkey_get_details($privateKey);
$publicKeyText = @file_get_contents(PUBLIC_KEY_PATH);
$publicKey = $publicKeyText === false ? false : openssl_pkey_get_public($publicKeyText);
$initialJson = @file_get_contents(JSON_PATH);
$initialSigText = @file_get_contents(SIGNATURE_PATH);
$initialSig = $initialSigText === false ? false : base64_decode(trim($initialSigText), true);
if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($details['bits'] ?? 0) < 2048 ||
    $publicKey === false || $initialJson === false || $initialSig === false ||
    openssl_verify($initialJson, $initialSig, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
    installer_page('Initial config/signature/public key যাচাই হয়নি।', $username, $keyInput);
}
$probe = 'secure-web-portal-browser-installer-check';
$probeSignature = '';
if (!openssl_sign($probe, $probeSignature, $privateKey, OPENSSL_ALGO_SHA256) ||
    openssl_verify($probe, $probeSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
    installer_page('Signing private key অ্যাপের public key-এর সঙ্গে মেলে না।', $username, $keyInput);
}
if (!chmod($keyPath, 0400) || !is_readable($keyPath)) {
    installer_page('Signing key-এর permission 0400 করা যায়নি। cPanel-এ ফাইলের owner ও permission পরীক্ষা করুন।', $username, $keyInput);
}

$privateDir = dirname(INSTALL_SETTINGS);
if (!is_dir($privateDir) && !mkdir($privateDir, 0700, true) && !is_dir($privateDir)) installer_page('Private settings directory তৈরি করা যায়নি।', $username, $keyInput);
if (!is_writable($privateDir)) installer_page('PHP service account-এর private/ directory-তে write permission নেই।', $username, $keyInput);
$lock = fopen(INSTALL_LOCK, 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    if (is_resource($lock)) fclose($lock);
    installer_page('Installer lock তৈরি করা যায়নি।', $username, $keyInput);
}
try {
    if (installer_locked()) {
        http_response_code(404);
        exit('Not found.');
    }
    $settings = [
        'admin_user' => $username,
        'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'signing_key_path' => $keyPath,
    ];
    $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($settings, true) . ";\n";
    $temporary = INSTALL_SETTINGS . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, INSTALL_SETTINGS)) {
        @unlink($temporary);
        installer_page('Installer settings নিরাপদে সংরক্ষণ করা যায়নি।', $username, $keyInput);
    }
    @chmod($privateDir, 0700);
    @chmod(INSTALL_LOCK, 0600);
    @unlink(INSTALL_TOKEN_FILE);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
$_SESSION = [];
session_destroy();
http_response_code(200);
?>
<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup complete</title></head><body><h1>ইনস্টলেশন সম্পন্ন</h1><p>Admin প্যানেল চালু হয়েছে। Installer এখন lock করা হয়েছে।</p><p><a href="admin.php">Admin login খুলুন</a></p></body></html>
