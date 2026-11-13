<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/dashboard.php');
csrf_check();

$type = post('type'); $id = (int)post('id');
$ret = post('return');
if (BASE_URL !== '' && strpos($ret, BASE_URL . '/') === 0) $ret = substr($ret, strlen(BASE_URL) + 1);
$ret = ltrim($ret, '/');
if (!preg_match('#^(admin|user)/[A-Za-z0-9_./?=&%\[\]-]*$#', $ret)) $ret = 'admin/dashboard.php';

$ids = fn(string $sql, array $p = []) => array_column(q_all($sql, $p), 'id');

switch ($type) {
    case 'farm':
        $f = find_farm($id); if (!$f) break;
        purge_record_images('broiler', $ids("SELECT id FROM broiler_records WHERE farm_id = ?", [$id]));
        purge_record_images('layer', $ids("SELECT id FROM layer_records WHERE farm_id = ?", [$id]));
        q("DELETE FROM farms WHERE id = ?", [$id]);
        log_activity('farm', "Admin deleted farm {$f['farm_name']} ({$f['farm_code']}) and its data");
        flash('success', "Farm {$f['farm_name']} and everything linked to it was deleted.");
        break;
    case 'location':
        $l = q_one("SELECT * FROM farm_locations WHERE id = ?", [$id]); if (!$l) break;
        if ((int)q_val("SELECT COUNT(*) FROM farm_locations WHERE farm_id = ?", [$l['farm_id']]) < 2) { flash('error', 'A farm needs at least one location.'); break; }
        $nb = (int)q_val("SELECT COUNT(*) FROM batches WHERE location_id = ?", [$id]);
        if ($nb) { flash('error', "This location still has $nb batch(es). Delete or move them first."); break; }
        q("DELETE FROM farm_locations WHERE id = ?", [$id]);
        log_activity('farm', "Admin deleted location {$l['location_name']}");
        flash('success', 'Location deleted.');
        break;
    case 'batch':
        $b = q_one("SELECT * FROM batches WHERE id = ?", [$id]); if (!$b) break;
        purge_record_images('broiler', $ids("SELECT id FROM broiler_records WHERE batch_id = ?", [$id]));
        purge_record_images('layer', $ids("SELECT id FROM layer_records WHERE batch_id = ?", [$id]));
        q("DELETE FROM batches WHERE id = ?", [$id]);
        log_activity('batch', "Admin deleted batch {$b['batch_no']} and its records");
        flash('success', "Batch {$b['batch_no']} and its visit records / prescriptions were deleted.");
        break;
    case 'broiler': case 'layer':
        $tbl = $type . '_records';
        $r = q_one("SELECT record_no FROM $tbl WHERE id = ?", [$id]); if (!$r) break;
        purge_record_images($type, [$id]);
        q("DELETE FROM $tbl WHERE id = ?", [$id]);
        log_activity($type, "Admin deleted $type record {$r['record_no']}");
        flash('success', "Record {$r['record_no']} deleted.");
        break;
    case 'prescription':
        $x = q_one("SELECT presc_no FROM prescriptions WHERE id = ?", [$id]); if (!$x) break;
        q("DELETE FROM prescriptions WHERE id = ?", [$id]);
        log_activity('prescription', "Admin deleted prescription {$x['presc_no']}");
        flash('success', "Prescription {$x['presc_no']} deleted.");
        break;
    case 'image':
        $im = q_one("SELECT * FROM record_images WHERE id = ?", [$id]); if (!$im) break;
        $path = UPLOAD_DIR . $im['file_name'];
        if (is_file($path)) @unlink($path);
        q("DELETE FROM record_images WHERE id = ?", [$id]);
        flash('success', 'Image removed.');
        break;
    default:
        flash('error', 'Unknown item.');
}
redirect($ret);
