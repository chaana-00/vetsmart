<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$b = q_one("SELECT b.*, f.farm_name, f.farm_code FROM batches b JOIN farms f ON f.id = b.farm_id WHERE b.id = ?", [(int)get('id', post('id'))]);
if (!$b) { flash('error', 'Batch not found.'); redirect('user/batches.php'); }
$pageTitle = 'Edit cage / batch'; $pageSub = $b['batch_no'] . ' · ' . $b['farm_name']; $active = 'batches';
$locs = q_all("SELECT id, location_name FROM farm_locations WHERE farm_id = ? ORDER BY location_name ASC", [$b['farm_id']]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $no = strtoupper(post('batch_no')); $total = post('total_input'); $locId = (int)post('location_id');
    if (!find_location($locId, (int)$b['farm_id'])) $errors[] = 'Select one of this farm\'s locations.';
    if ($no === '') $errors[] = 'Batch number is required.';
    elseif (q_val("SELECT COUNT(*) FROM batches WHERE batch_no = ? AND id <> ?", [$no, $b['id']])) $errors[] = "Batch number $no already exists.";
    if ($total === '' || !ctype_digit($total)) $errors[] = 'Total input must be a whole number.';
    if (post('cage_no') === '') $errors[] = 'Cage number is required.';
    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        q("UPDATE batches SET location_id=?, batch_no=?, visit_datetime=?, cage_no=?, breed=?, hatchery=?, total_input=?, vaccination=?, treatment_history=? WHERE id=?",
          [$locId, $no, dt_local(post('visit_datetime')), post('cage_no'), nz(post('breed')), nz(post('hatchery')), (int)$total, nz(post('vaccination')), nz(post('treatment_history')), $b['id']]);
        q("UPDATE broiler_records SET location_id = ? WHERE batch_id = ?", [$locId, $b['id']]);
        q("UPDATE layer_records SET location_id = ? WHERE batch_id = ?", [$locId, $b['id']]);
        $pdo->commit();
        log_activity('batch', "Admin updated batch $no ({$b['farm_name']})", 'user/batches.php?farm_id=' . $b['farm_id']);
        flash('success', "Batch $no updated.");
        redirect('user/batches.php?farm_id=' . $b['farm_id']);
    }
    $b = array_merge($b, $_POST);
}
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/delete_form.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
  <div class="panel-head"><h3>Batch details</h3><div class="ms-auto">
    <a class="btn btn-sm btn-soft" href="<?= url('user/batches.php') ?>"><i class="bi bi-arrow-left"></i> All batches</a>
    <button type="button" class="btn btn-sm btn-del-x" data-del="batch:<?= $b['id'] ?>" data-return="user/batches.php" data-msg="Delete batch <?= h($b['batch_no']) ?> with ALL its visit records, images and prescriptions?"><i class="bi bi-trash"></i> Delete batch</button></div></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Farm</label><input class="form-control" value="<?= h($b['farm_name'] . ' (' . $b['farm_code'] . ')') ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Farm location</label><select class="form-select" name="location_id">
      <?php foreach ($locs as $l): ?><option value="<?= $l['id'] ?>" <?= (int)$b['location_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['location_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Visit date &amp; time</label><input class="form-control" type="datetime-local" name="visit_datetime" value="<?= !empty($b['visit_datetime']) ? h(date('Y-m-d\TH:i', strtotime($b['visit_datetime']))) : '' ?>"></div>
    <div class="col-md-6"><label class="form-label">Batch number</label><input class="form-control" name="batch_no" required value="<?= h($b['batch_no']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Cage no</label><input class="form-control" name="cage_no" required value="<?= h($b['cage_no']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Breed</label><input class="form-control" name="breed" value="<?= h($b['breed']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Hatchery</label><input class="form-control" name="hatchery" value="<?= h($b['hatchery']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Total input (birds)</label><input class="form-control" name="total_input" inputmode="numeric" required value="<?= h($b['total_input']) ?>"></div>
    <div class="col-12"><label class="form-label">Vaccination details</label><textarea class="form-control" name="vaccination"><?= h($b['vaccination']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Treatment history</label><textarea class="form-control" name="treatment_history"><?= h($b['treatment_history']) ?></textarea></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save changes</button>
</form>
<?php include __DIR__ . '/../includes/footer.php';
