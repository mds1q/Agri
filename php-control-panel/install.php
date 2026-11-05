<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/app/common.php';

function fail_install(string $message): never
{
    fwrite(STDERR, "Install error: {$message}\n");
    exit(1);
}

if (PHP_VERSION_ID < 80100) {
    fail_install('PHP 8.1 or newer is required.');
}
if (!extension_loaded('openssl')) {
    fail_install('The PHP OpenSSL extension is required.');
}
if (!extension_loaded('fileinfo')) {
    fail_install('The PHP fileinfo extension is required for safe splash image uploads.');
}

$publicDir = realpath(__DIR__ . '/public');
$appDir = realpath(__DIR__ . '/app');
if ($publicDir === false || $appDir === false || !is_file($publicDir . '/admin.php') || !is_file($publicDir . '/api.php')) {
    fail_install('Panel files are incomplete. Extract the full ZIP and run install.php from its root folder.');
}
foreach ([$publicDir, $appDir] as $directory) {
    if (!is_readable($directory)) {
        fail_install('Panel folder is not readable by the PHP service account: ' . $directory);
    }
}

if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0750, true) && !is_dir(STORAGE_DIR)) {
    fail_install('Could not create the storage directory.');
}
if (!chmod(STORAGE_DIR, 0750) || !is_readable(STORAGE_DIR) || !is_writable(STORAGE_DIR)) {
    fail_install('Could not set storage/ to mode 0750 or the PHP service account cannot write to it. Run the installer as the PHP service account or fix ownership.');
}
if (!chmod($publicDir, 0755) || !chmod($appDir, 0755)) {
    fail_install('Could not set public/ and app/ directory permissions to 0755.');
}
foreach ([$publicDir . '/admin.php', $publicDir . '/api.php', $appDir . '/common.php'] as $codeFile) {
    if (!is_file($codeFile) || !chmod($codeFile, 0644)) {
        fail_install('Could not set readable code-file permissions: ' . $codeFile);
    }
}
foreach ([$publicDir . '/.htaccess', STORAGE_DIR . '/.htaccess'] as $accessFile) {
    if (is_file($accessFile) && !chmod($accessFile, 0644)) {
        fail_install('Could not set access-file permissions: ' . $accessFile);
    }
}

$adminUser = trim((string)(getenv('AGRI_ADMIN_USER') ?: ''));
$password = (string)(getenv('AGRI_ADMIN_PASSWORD') ?: '');
$keyPath = (string)(getenv('AGRI_CONFIG_PRIVATE_KEY') ?: (__DIR__ . '/private/config-signing-private.pem'));
if ($adminUser === '' || $password === '') {
    fail_install('Set AGRI_ADMIN_USER and AGRI_ADMIN_PASSWORD in the environment, then rerun. The signing key path is detected automatically.');
}
if ((function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password)) < 14) {
    fail_install('Use an admin password at least 14 characters long. A long, unique passphrase is recommended.');
}
if (str_contains($adminUser, "\n") || str_contains($adminUser, "\r")) {
    fail_install('Admin username must be a single line.');
}

$privateKeyText = @file_get_contents($keyPath);
$privateKey = $privateKeyText === false ? false : openssl_pkey_get_private($privateKeyText);
if ($privateKey === false) {
    fail_install('Cannot read or parse the private signing key at AGRI_CONFIG_PRIVATE_KEY.');
}
$realKeyPath = realpath($keyPath);
if ($realKeyPath === false) {
    fail_install('The private key path must point to an existing file.');
}
if (str_starts_with($realKeyPath, $publicDir . DIRECTORY_SEPARATOR) &&
    $realKeyPath !== realpath(__DIR__ . '/private/config-signing-private.pem')) {
    fail_install('The signing key is inside public/. Move it outside the web document root.');
}
if (!chmod($realKeyPath, 0400) || !is_readable($realKeyPath)) {
    fail_install('Could not set the signing key to owner-read-only mode 0400.');
}
$keyDetails = openssl_pkey_get_details($privateKey);
if (!is_array($keyDetails) || ($keyDetails['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($keyDetails['bits'] ?? 0) < 2048) {
    fail_install('The signing key must be an RSA key of at least 2048 bits.');
}
$publicKeyText = @file_get_contents(PUBLIC_KEY_PATH);
$publicKey = $publicKeyText === false ? false : openssl_pkey_get_public($publicKeyText);
if ($publicKey === false) {
    fail_install('Cannot read the app matching public key from storage/config-signing-public.pem.');
}
$probe = 'secure-web-portal-installer-key-check';
$probeSignature = '';
if (!openssl_sign($probe, $probeSignature, $privateKey, OPENSSL_ALGO_SHA256) ||
    openssl_verify($probe, $probeSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
    fail_install('The private key does not match the public key embedded in the app.');
}
if (!is_file(JSON_PATH) || !is_file(SIGNATURE_PATH)) {
    fail_install('Initial portal-config.json and portal-config.json.sig are missing from storage/.');
}
if (!chmod(PUBLIC_KEY_PATH, 0644) || !chmod(JSON_PATH, 0640) || !chmod(SIGNATURE_PATH, 0640)) {
    fail_install('Could not set storage file permissions (public key 0644, config and signature 0640).');
}
if (!is_readable(PUBLIC_KEY_PATH) || !is_readable(JSON_PATH) || !is_readable(SIGNATURE_PATH)) {
    fail_install('Could not set readable permissions on the initial config files in storage/.');
}
$permissionProbe = STORAGE_DIR . '/.permission-check-' . bin2hex(random_bytes(6));
if (file_put_contents($permissionProbe, 'ok', LOCK_EX) === false) {
    fail_install('PHP cannot write to storage/. Fix its owner/permissions, then rerun the installer.');
}
@chmod($permissionProbe, 0600);
@unlink($permissionProbe);
$initialJson = @file_get_contents(JSON_PATH);
$initialSignatureText = @file_get_contents(SIGNATURE_PATH);
$initialSignature = $initialSignatureText === false ? false : base64_decode(trim($initialSignatureText), true);
if ($initialJson === false || $initialSignature === false ||
    openssl_verify($initialJson, $initialSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
    fail_install('The initial JSON and signature in storage/ do not match the app public key.');
}

$privateDir = __DIR__ . '/private';
if (!is_dir($privateDir) && !mkdir($privateDir, 0700, true) && !is_dir($privateDir)) {
    fail_install('Could not create the private config directory.');
}
if (!chmod($privateDir, 0700) || !is_readable($privateDir) || !is_writable($privateDir)) {
    fail_install('Could not set private/ to mode 0700. Run the installer as the PHP service account or fix its ownership.');
}
$configPath = $privateDir . '/config.php';
if (is_file($configPath)) {
    fail_install('Admin is already installed. Remove private/config.php manually before reinstalling.');
}

$settings = [
    'admin_user' => $adminUser,
    'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'signing_key_path' => realpath($keyPath) ?: $keyPath,
];
$contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($settings, true) . ";\n";
$temporaryPath = $configPath . '.' . bin2hex(random_bytes(8)) . '.tmp';
if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || !rename($temporaryPath, $configPath)) {
    @unlink($temporaryPath);
    fail_install('Could not write private/config.php.');
}
if (!chmod($configPath, 0600) || !is_readable($configPath)) {
    @unlink($configPath);
    fail_install('Could not protect private/config.php with mode 0600.');
}

fwrite(STDOUT, "Admin panel installed. Open /admin.php over HTTPS.\n");
fwrite(STDOUT, "Private settings saved outside public/: php-control-panel/private/config.php\n");
fwrite(STDOUT, "Permissions applied: public/app 0755, code 0644, storage 0750, config/signature 0640, private key 0400, private settings 0700/0600.\n");
fwrite(STDOUT, "Set the hosting document root to: {$publicDir}\n");
