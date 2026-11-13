<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../lib/DocxBuilder.php';
require_login(['admin', 'user']);

if (!class_exists('ZipArchive')) { exit('The PHP "zip" extension is required for Word downloads. Enable extension=zip in php.ini.'); }

$type = get('type');
$ids = array_values(array_filter(array_map('intval', (array)($_GET['ids'] ?? []))));
if ((int)get('id')) $ids = [(int)get('id')];
$farmId = (int)get('farm_id'); $locId = (int)get('location_id'); $batId = (int)get('batch_id');
$stamp = date('Y-m-d');
$by = $_SESSION['name'] ?? '';

function back_with_error(string $msg): void {
    flash('error', $msg);
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : (is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php'));
}
function in_clause(array $ids): string { return implode(',', array_map('intval', $ids)); }
function cover(DocxBuilder $d, string $title): void {
    $d->title($title, 'VetSmart · Farmchemie · Exported by ' . ($_SESSION['name'] ?? '') . ' on ' . date('d M Y, h:i A'));
}

/* ---------- Farms ---------- */
if ($type === 'farms') {
    $p = []; $w = '';
    if ($ids) $w .= ' AND f.id IN (' . in_clause($ids) . ')';
    if ((int)get('user_id')) { $w .= ' AND f.user_id = ?'; $p[] = (int)get('user_id'); }
    $farms = q_all("SELECT f.*, u.full_name vet FROM farms f JOIN users u ON u.id = f.user_id WHERE 1=1 $w ORDER BY f.farm_name ASC", $p);
    if (!$farms) back_with_error('No farm records to download.');
    $d = new DocxBuilder(false); cover($d, 'Registered Farm Records');
    foreach ($farms as $f) {
        $d->heading($f['farm_name'] . ' (' . $f['farm_code'] . ')', 1);
        $d->kv([
            ['Farm type', $f['farm_type']], ['Capacity', $f['capacity'] !== null ? number_format($f['capacity']) . ' birds' : ''],
            ['Farm contacts', implode(', ', array_filter([$f['contact1'], $f['contact2'], $f['contact3']]))],
            ['Owner', $f['owner_name']], ['Owner contacts', implode(', ', array_filter([$f['owner_contact1'], $f['owner_contact2']]))],
            ['Note', $f['note']], ['Registered by', $f['vet']], ['Registered on', fmt_dt($f['created_at'])],
        ]);
        $locs = q_all("SELECT * FROM farm_locations WHERE farm_id = ? ORDER BY location_name ASC", [$f['id']]);
        $d->heading('Locations', 2);
        $d->table(['Location', 'Latitude', 'Longitude'], array_map(fn($l) => [$l['location_name'], $l['latitude'], $l['longitude']], $locs), [5, 2, 2], '0F7D47');
    }
    $d->stream(count($farms) === 1 ? 'Farm_' . slug($farms[0]['farm_code']) . '_' . slug($farms[0]['farm_name']) . '.docx' : "VetSmart_Farm_Records_$stamp.docx");
}

/* ---------- Cages & batches ---------- */
if ($type === 'batches') {
    $p = []; $w = '';
    if ($ids) $w .= ' AND b.id IN (' . in_clause($ids) . ')';
    if ((int)get('user_id')) { $w .= ' AND b.user_id = ?'; $p[] = (int)get('user_id'); }
    if ($farmId) { $w .= ' AND b.farm_id = ?'; $p[] = $farmId; }
    if ($locId)  { $w .= ' AND b.location_id = ?'; $p[] = $locId; }
    $rows = q_all("SELECT b.*, f.farm_name, l.location_name FROM batches b JOIN farms f ON f.id = b.farm_id JOIN farm_locations l ON l.id = b.location_id
        WHERE 1=1 $w ORDER BY f.farm_name ASC, l.location_name ASC, b.batch_no ASC", $p);
    if (!$rows) back_with_error('No batch records to download.');
    $d = new DocxBuilder(true); cover($d, 'Cage & Batch Records');
    $d->table(['Farm', 'Location', 'Visit date & time', 'Batch no', 'Cage', 'Breed', 'Hatchery', 'Total input', 'Vaccination', 'Treatment history'],
        array_map(fn($r) => [$r['farm_name'], $r['location_name'], fmt_dt($r['visit_datetime']), $r['batch_no'], $r['cage_no'], $r['breed'], $r['hatchery'], number_format($r['total_input']), $r['vaccination'], $r['treatment_history']], $rows),
        [2, 2, 2, 2, 1, 1.4, 1.4, 1.2, 2.4, 2.4]);
    $d->para('Total birds placed: ' . number_format(array_sum(array_column($rows, 'total_input'))), ['bold' => true]);
    $d->stream("VetSmart_Batch_Records_$stamp.docx");
}

/* ---------- Broiler / Layer visit records ---------- */
if ($type === 'broiler' || $type === 'layer') {
    $labels = (require __DIR__ . '/../includes/record_labels.php')[$type];
    $p = []; $w = '';
    if ($ids) $w .= ' AND r.id IN (' . in_clause($ids) . ')';
    if ((int)get('user_id')) { $w .= ' AND r.user_id = ?'; $p[] = (int)get('user_id'); }
    foreach (['farm_id' => $farmId, 'location_id' => $locId, 'batch_id' => $batId] as $c => $v) if ($v) { $w .= " AND r.$c = ?"; $p[] = $v; }
    $rows = q_all("SELECT r.*, f.farm_name, f.farm_code, l.location_name, b.batch_no, u.full_name vet, u.designation vet_designation FROM {$type}_records r
        JOIN farms f ON f.id = r.farm_id JOIN farm_locations l ON l.id = r.location_id JOIN batches b ON b.id = r.batch_id JOIN users u ON u.id = r.user_id
        WHERE 1=1 $w ORDER BY f.farm_name ASC, r.id ASC", $p);
    if (!$rows) back_with_error('No ' . $type . ' records to download.');
    $T = ucfirst($type);
    $d = new DocxBuilder(false); cover($d, "$T Farm Visit Records");
    foreach ($rows as $i => $r) {
        if ($i > 0) $d->pageBreak();
        $d->heading($r['record_no'] . ' · ' . $r['farm_name'], 1);
        $kv = [['Farm', $r['farm_name'] . ' (' . $r['farm_code'] . ')'], ['Location', $r['location_name']], ['Batch', $r['batch_no']], ['Recorded by', $r['vet']]];
        foreach ($labels as $f => $label) $kv[] = [$label, $f === 'visit_date' ? fmt_dt($r[$f]) : $r[$f]];
        $d->kv($kv);
        $imgs = record_images($type, (int)$r['id']);
        if ($imgs) {
            $d->heading('Post-mortem images', 2);
            foreach ($imgs as $im) {
                $path = UPLOAD_DIR . $im['file_name'];
                if (is_file($path) && $d->image($path)) $d->para(basename($im['file_name']), ['italic' => true, 'color' => '5D7880', 'size' => 17]);
            }
        }
        // Veterinarian signature block
        $d->spacer(); $d->spacer();
        $d->para('______________________________', ['color' => '5D7880']);
        $d->para($r['vet'] . ($r['vet_designation'] ? ', ' . $r['vet_designation'] : ''), ['bold' => true]);
        $d->para('Veterinarian signature · ' . fmt_dt($r['visit_date'], 'd M Y'), ['color' => '5D7880', 'size' => 18]);
    }
    $d->stream(count($rows) === 1 ? slug($rows[0]['record_no']) . '_' . slug($rows[0]['farm_name']) . '.docx' : "VetSmart_{$T}_Records_$stamp.docx");
}

/* ---------- Prescription ---------- */
if ($type === 'prescription') {
    $p = [(int)get('id')];
    $r = q_one("SELECT x.*, f.farm_name, f.farm_code, b.batch_no, u.full_name vet, u.designation FROM prescriptions x
        JOIN farms f ON f.id = x.farm_id JOIN batches b ON b.id = x.batch_id JOIN users u ON u.id = x.user_id WHERE x.id = ?", $p);
    if (!$r) back_with_error('Prescription not found.');
    $items = q_all("SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY id", [$r['id']]);
    $d = new DocxBuilder(false); cover($d, 'Prescription ' . $r['presc_no']);
    $d->kv([['Farm', $r['farm_name'] . ' (' . $r['farm_code'] . ')'], ['Batch number', $r['batch_no']], ['Number of birds', $r['num_birds'] !== null ? number_format($r['num_birds']) : ''], ['Date & time', fmt_dt($r['presc_datetime'])]]);
    $d->heading('Prescription details', 2);
    $d->table(['Drugs', 'Indication', 'Duration', 'Total volume / drugs / weight'], array_map(fn($i) => [$i['drug'], $i['indication'], $i['duration'], $i['total_volume']], $items), [3, 3, 2, 3]);
    $d->heading('Remarks', 2); $d->para($r['remarks'] ?: '—');
    $d->spacer(); $d->spacer();
    $d->para('______________________________', ['color' => '5D7880']);
    $d->para($r['vet'] . ($r['designation'] ? ', ' . $r['designation'] : ''), ['bold' => true]);
    $d->stream($r['presc_no'] . '_' . slug($r['farm_name']) . '.docx');
}

back_with_error('Unknown download type.');
