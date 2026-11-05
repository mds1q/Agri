<?php
declare(strict_types=1);

$commonPath = __DIR__ . '/../app/common.php';
if (!is_file($commonPath)) {
    $commonPath = __DIR__ . '/app/common.php';
}
require $commonPath;

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$resource = $_GET['file'] ?? '';
$imageTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
if (is_string($resource) && preg_match('/^splash-image-[a-f0-9]{32}\.(jpg|png|webp)$/', $resource, $matches)) {
    $imagePath = SPLASH_IMAGES_DIR . '/' . $resource;
    if (!is_file($imagePath) || !is_readable($imagePath)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $imageTypes[$matches[1]]);
    header('Content-Length: ' . (string)filesize($imagePath));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($imagePath);
    exit;
}
if ($resource !== 'portal-config.json' && $resource !== 'portal-config.json.sig' && $resource !== 'portal-config.version') {
    http_response_code(404);
    exit;
}

try {
    [$json, $signature] = read_signed_config();
    if ($resource === 'portal-config.version') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo hash('sha256', $json);
        exit;
    }
    $etag = '"' . hash('sha256', $json) . '"';
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    if ($resource === 'portal-config.json') {
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $signature;
    }
} catch (Throwable $error) {
    error_log('Portal config API: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Config is temporarily unavailable.';
}
