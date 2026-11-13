<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user'], true);

$farm = find_farm((int)get('farm_id'));
if (!$farm) json_out(['batches' => []]);
$p = [$farm['id']];
$sql = "SELECT id, batch_no, cage_no, total_input FROM batches WHERE farm_id = ?";
if ((int)get('location_id') > 0) { $sql .= " AND location_id = ?"; $p[] = (int)get('location_id'); }
json_out(['batches' => q_all($sql . " ORDER BY batch_no ASC", $p)]);
