<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['user']);
$pageTitle = 'Create prescription'; $pageSub = 'Drugs, indications and durations for a batch'; $active = 'prescription';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $farm  = find_farm((int)post('farm_id'));
    $batch = $farm ? find_batch((int)post('batch_id'), (int)$farm['id']) : null;
    if (!$farm) $errors[] = 'Select a farm.'; elseif (!$batch) $errors[] = 'Select a batch for this farm.';
    $birds = post('num_birds');
    if ($birds !== '' && !ctype_digit($birds)) $errors[] = 'Number of birds must be a whole number.';
    $items = [];
    foreach ((array)($_POST['drug'] ?? []) as $i => $d) {
        $d = trim((string)$d); if ($d === '') continue;
        $items[] = [$d, trim((string)($_POST['indication'][$i] ?? '')), trim((string)($_POST['duration'][$i] ?? '')), trim((string)($_POST['volume'][$i] ?? ''))];
    }
    if (!$items) $errors[] = 'Add at least one drug.';
    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            q("INSERT INTO prescriptions (user_id, farm_id, batch_id, num_birds, remarks, presc_datetime) VALUES (?,?,?,?,?,?)",
              [uid(), $farm['id'], $batch['id'], $birds === '' ? null : (int)$birds, nz(post('remarks')), dt_local(post('presc_datetime')) ?? date('Y-m-d H:i:s')]);
            $id = (int)$pdo->lastInsertId();
            $no = 'RX-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            q("UPDATE prescriptions SET presc_no = ? WHERE id = ?", [$no, $id]);
            foreach ($items as $it) q("INSERT INTO prescription_items (prescription_id, drug, indication, duration, total_volume) VALUES (?,?,?,?,?)", [$id, $it[0], nz($it[1]), nz($it[2]), nz($it[3])]);
            $pdo->commit();
            log_activity('prescription', "Prescription $no created for {$farm['farm_name']} ({$batch['batch_no']})", 'user/prescriptions.php?id=' . $id);
            flash('success', "Prescription $no saved.");
            redirect('user/prescriptions.php?id=' . $id);
        } catch (Throwable $e) { $pdo->rollBack(); $errors[] = 'Could not save the prescription.'; }
    }
}
$old = [];
foreach ((array)($_POST['drug'] ?? ['']) as $i => $d) $old[] = [$d, $_POST['indication'][$i] ?? '', $_POST['duration'][$i] ?? '', $_POST['volume'][$i] ?? ''];
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if (!farm_list()): ?>
  <div class="panel"><div class="empty"><div class="egg-ico"><i class="bi bi-capsule"></i></div><b>Register a farm and a batch first</b><a href="<?= url('user/farm_register.php') ?>">Register a farm</a></div></div>
<?php else: ?>
<form method="post" class="panel" data-cascade>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Farm name</label><select class="form-select" name="farm_id" data-farm required><?= farm_options((int)post('farm_id') ?: null) ?></select></div>
    <div class="col-md-6"><label class="form-label">Batch number</label><select class="form-select" name="batch_id" data-batch data-selected="<?= h(post('batch_id')) ?>" required><option value="">Select batch</option></select></div>
    <div class="col-md-6"><label class="form-label">Number of birds</label><input class="form-control" name="num_birds" data-birds inputmode="numeric" value="<?= h(post('num_birds')) ?>"><div class="form-text">Filled from the batch total input; change it if needed.</div></div>
  </div>
  <div class="form-section">Prescription details</div>
  <div class="table-wrap"><table class="table table-vs rx-table mb-0">
    <thead><tr><th>Drugs</th><th>Indication</th><th>Duration</th><th>Total volume / drugs / weight</th><th style="width:70px">Action</th></tr></thead>
    <tbody id="rxBody">
    <?php foreach ($old as $o): ?><tr>
      <td><input class="form-control" name="drug[]" value="<?= h($o[0]) ?>"></td><td><input class="form-control" name="indication[]" value="<?= h($o[1]) ?>"></td>
      <td><input class="form-control" name="duration[]" value="<?= h($o[2]) ?>"></td><td><input class="form-control" name="volume[]" value="<?= h($o[3]) ?>"></td>
      <td><button type="button" class="btn-del" aria-label="Remove row"><i class="bi bi-x-lg"></i></button></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <button type="button" id="addRow" class="btn btn-soft-blue w-100 mt-2"><i class="bi bi-plus-lg"></i> Add row</button>
  <div class="row g-3 mt-1">
    <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks"><?= h(post('remarks')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Date &amp; time</label><input class="form-control" type="datetime-local" name="presc_datetime" value="<?= h(post('presc_datetime', date('Y-m-d\TH:i'))) ?>"></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save prescription</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php';
