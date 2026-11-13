<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$x = q_one("SELECT x.*, f.farm_name, f.farm_code, b.batch_no FROM prescriptions x JOIN farms f ON f.id = x.farm_id JOIN batches b ON b.id = x.batch_id WHERE x.id = ?", [(int)get('id', post('id'))]);
if (!$x) { flash('error', 'Prescription not found.'); redirect('user/prescriptions.php'); }
$pageTitle = 'Edit prescription'; $pageSub = $x['presc_no'] . ' · ' . $x['farm_name']; $active = 'prescriptions';

$errors = []; $rows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $birds = post('num_birds');
    if ($birds !== '' && !ctype_digit($birds)) $errors[] = 'Number of birds must be a whole number.';
    foreach ((array)($_POST['drug'] ?? []) as $i => $d) {
        $d = trim((string)$d); if ($d === '') continue;
        $rows[] = [$d, trim((string)($_POST['indication'][$i] ?? '')), trim((string)($_POST['duration'][$i] ?? '')), trim((string)($_POST['volume'][$i] ?? ''))];
    }
    if (!$rows) $errors[] = 'Keep at least one drug.';
    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            q("UPDATE prescriptions SET num_birds=?, remarks=?, presc_datetime=? WHERE id=?", [$birds === '' ? null : (int)$birds, nz(post('remarks')), dt_local(post('presc_datetime')), $x['id']]);
            q("DELETE FROM prescription_items WHERE prescription_id = ?", [$x['id']]);
            foreach ($rows as $it) q("INSERT INTO prescription_items (prescription_id, drug, indication, duration, total_volume) VALUES (?,?,?,?,?)", [$x['id'], $it[0], nz($it[1]), nz($it[2]), nz($it[3])]);
            $pdo->commit();
            log_activity('prescription', "Admin updated prescription {$x['presc_no']}", 'user/prescriptions.php?id=' . $x['id']);
            flash('success', "Prescription {$x['presc_no']} updated.");
            redirect('user/prescriptions.php?id=' . $x['id']);
        } catch (Throwable $e) { $pdo->rollBack(); $errors[] = 'Could not save the prescription.'; }
    }
    $x = array_merge($x, $_POST);
} else {
    foreach (q_all("SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY id", [$x['id']]) as $i) $rows[] = [$i['drug'], $i['indication'], $i['duration'], $i['total_volume']];
}
if (!$rows) $rows[] = ['', '', '', ''];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/delete_form.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
  <div class="panel-head"><h3><?= h($x['presc_no']) ?></h3><div class="ms-auto">
    <a class="btn btn-sm btn-soft" href="<?= url('user/prescriptions.php') ?>"><i class="bi bi-arrow-left"></i> All prescriptions</a>
    <button type="button" class="btn btn-sm btn-del-x" data-del="prescription:<?= $x['id'] ?>" data-return="user/prescriptions.php" data-msg="Delete prescription <?= h($x['presc_no']) ?>?"><i class="bi bi-trash"></i> Delete</button></div></div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Farm</label><input class="form-control" disabled value="<?= h($x['farm_name'] . ' (' . $x['farm_code'] . ')') ?>"></div>
    <div class="col-md-4"><label class="form-label">Batch number</label><input class="form-control" disabled value="<?= h($x['batch_no']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Number of birds</label><input class="form-control" name="num_birds" inputmode="numeric" value="<?= h($x['num_birds']) ?>"></div>
  </div>
  <div class="form-section">Prescription details</div>
  <div class="table-wrap"><table class="table table-vs rx-table mb-0">
    <thead><tr><th>Drugs</th><th>Indication</th><th>Duration</th><th>Total volume / drugs / weight</th><th style="width:70px">Action</th></tr></thead>
    <tbody id="rxBody"><?php foreach ($rows as $o): ?><tr>
      <td><input class="form-control" name="drug[]" value="<?= h($o[0]) ?>"></td><td><input class="form-control" name="indication[]" value="<?= h($o[1]) ?>"></td>
      <td><input class="form-control" name="duration[]" value="<?= h($o[2]) ?>"></td><td><input class="form-control" name="volume[]" value="<?= h($o[3]) ?>"></td>
      <td><button type="button" class="btn-del" aria-label="Remove row"><i class="bi bi-x-lg"></i></button></td></tr><?php endforeach; ?></tbody></table></div>
  <button type="button" id="addRow" class="btn btn-soft-blue w-100 mt-2"><i class="bi bi-plus-lg"></i> Add row</button>
  <div class="row g-3 mt-1">
    <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks"><?= h($x['remarks']) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Date &amp; time</label><input class="form-control" type="datetime-local" name="presc_datetime" value="<?= !empty($x['presc_datetime']) && strtotime((string)$x['presc_datetime']) ? h(date('Y-m-d\TH:i', strtotime((string)$x['presc_datetime']))) : '' ?>"></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save changes</button>
</form>
<?php include __DIR__ . '/../includes/footer.php';
