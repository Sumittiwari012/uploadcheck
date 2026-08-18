<?php
// upload.php — receives the picture, saves it to /uploads, returns its path as JSON.

header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Basic validation
if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file received, or upload error.']);
    exit;
}

$file = $_FILES['picture'];

// Only allow common image types
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/heic', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported file type: ' . $mime]);
    exit;
}

// Limit file size (e.g. 10 MB)
$maxBytes = 10 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large (max 10 MB).']);
    exit;
}

// Build a safe, unique filename — never trust the original name directly
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$ext = preg_replace('/[^a-z0-9]/', '', $ext);
if ($ext === '') {
    $ext = 'jpg';
}
$safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
$destination = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save file on server.']);
    exit;
}

// Path relative to the site root — this is what you fetch the image back with
$relativePath = 'uploads/' . $safeName;
$fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
    . $_SERVER['HTTP_HOST']
    . dirname($_SERVER['SCRIPT_NAME'])
    . '/' . $relativePath;

echo json_encode([
    'ok'   => true,
    'path' => $relativePath,
    'url'  => $fullUrl,
]);
