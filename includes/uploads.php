<?php
/* Post-mortem image storage.
   Files are renamed:  FarmName_FarmID_Location_RecordNo_01.jpg  and saved in uploads/postmortem/<broiler|layer>/ */
function save_postmortem(string $type, int $recordId, string $recordNo, array $farm, array $loc, string $field): int {
    if (empty($_FILES[$field]['name'][0])) return 0;
    $dir = UPLOAD_DIR . $type . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
    $base = slug($farm['farm_name']) . '_' . slug($farm['farm_code']) . '_' . slug($loc['location_name']) . '_' . slug($recordNo);
    $base = substr($base, 0, 150);
    $saved = 0;
    $n = (int)q_val("SELECT COUNT(*) FROM record_images WHERE record_type = ? AND record_id = ?", [$type, $recordId]);
    foreach ($_FILES[$field]['name'] as $i => $orig) {
        if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES[$field]['size'][$i] > MAX_UPLOAD_MB * 1048576) continue;
        $tmp = $_FILES[$field]['tmp_name'][$i];
        $info = @getimagesize($tmp);
        if (!$info || !isset($allowed[$info[2]])) continue;
        do { $name = sprintf('%s_%02d.%s', $base, ++$n, $allowed[$info[2]]); } while (is_file($dir . $name));
        if (move_uploaded_file($tmp, $dir . $name)) {
            q("INSERT INTO record_images (record_type, record_id, file_name, original_name) VALUES (?,?,?,?)", [$type, $recordId, $type . '/' . $name, mb_substr($orig, 0, 250)]);
            $saved++;
        }
    }
    return $saved;
}
function record_images(string $type, int $id): array {
    return q_all("SELECT * FROM record_images WHERE record_type = ? AND record_id = ? ORDER BY id", [$type, $id]);
}

/* Remove image files + rows for the given record ids (used before deleting records). */
function purge_record_images(string $type, array $ids): void {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return;
    $in = implode(',', $ids);
    foreach (q_all("SELECT file_name FROM record_images WHERE record_type = ? AND record_id IN ($in)", [$type]) as $im) {
        $path = UPLOAD_DIR . $im['file_name'];
        if (is_file($path)) @unlink($path);
    }
    q("DELETE FROM record_images WHERE record_type = ? AND record_id IN ($in)", [$type]);
}
