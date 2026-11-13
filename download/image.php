<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$img = q_one("SELECT * FROM record_images WHERE id = ?", [(int)get('id')]);
if (!$img) { http_response_code(404); exit; }
$tbl = $img['record_type'] === 'layer' ? 'layer_records' : 'broiler_records';
$p = [$img['record_id']];
if (!q_one("SELECT id FROM $tbl WHERE id = ?", $p)) { http_response_code(404); exit; }
$path = UPLOAD_DIR . $img['file_name'];
if (!is_file($path) || strpos(realpath($path), realpath(UPLOAD_DIR)) !== 0) { http_response_code(404); exit; }
$mime = image_type_to_mime_type(getimagesize($path)[2]);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($path);
