<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user'], true);

$farm = find_farm((int)get('farm_id'));
if (!$farm) json_out(['locations' => [], 'next_batch' => '']);
$locs = q_all("SELECT id, location_name AS name FROM farm_locations WHERE farm_id = ? ORDER BY location_name ASC", [$farm['id']]);
json_out(['locations' => $locs, 'next_batch' => next_batch_no($farm)]);

function next_batch_no(array $farm): string {
    $n = (int)q_val("SELECT COUNT(*) FROM batches WHERE farm_id = ?", [$farm['id']]) + 1;
    return $farm['farm_code'] . '-B' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}
