<?php
/**
 * SURTEADOS — File Upload API
 * POST multipart/form-data: file + type (logo|slide)
 * Returns { ok: true, data: { url, filename } }
 */
require __DIR__ . '/config.php';
auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$type    = preg_replace('/[^a-z]/', '', $_POST['type'] ?? 'logo'); // logo|slide
$allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
$maxSize = 5 * 1024 * 1024; // 5 MB

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_error('No se recibió archivo o hubo un error de carga');
}

$file = $_FILES['file'];

// Validate MIME via content sniffing (not just extension)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    json_error('Tipo de archivo no permitido. Solo imágenes JPG, PNG, GIF, WebP o SVG.');
}

if ($file['size'] > $maxSize) {
    json_error('El archivo supera el tamaño máximo de 5MB.');
}

// Ensure upload directory exists and is protected
$uploadDir = realpath(__DIR__ . '/../assets') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
// Prevent PHP execution in uploads folder
$htaccess = $uploadDir . '.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
}

// Generate secure filename
$ext = match($mime) {
    'image/jpeg'   => 'jpg',
    'image/png'    => 'png',
    'image/gif'    => 'gif',
    'image/webp'   => 'webp',
    'image/svg+xml'=> 'svg',
    default        => 'jpg',
};
$filename = $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_error('Error al guardar el archivo en el servidor.');
}

$url = rtrim(BASE_URL, '/') . '/assets/uploads/' . $filename;

// Auto-save logo URL to settings
if ($type === 'logo') {
    db()->prepare(
        "INSERT INTO settings (`key`,`value`) VALUES ('site_logo',?)
         ON DUPLICATE KEY UPDATE `value`=?"
    )->execute([$url, $url]);
}

json_ok(['url' => $url, 'filename' => $filename]);
