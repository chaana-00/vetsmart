<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['user']);
$pageTitle = 'Add cage / batch'; $pageSub = 'Record a new flock placement for a farm'; $active = 'batch_add';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $farm = find_farm((int)post('farm_id'));
    $loc  = $farm ? find_location((int)post('location_id'), (int)$farm['id']) : null;
    $total = post('total_input');
    if (!$farm) $errors[] = 'Select a farm.';
    elseif (!$loc) $errors[] = 'Select one of this farm\'s locations.';
    if ($total === '' || !ctype_digit($total)) $errors[] = 'Total input must be a whole number.';
    if (post('cage_no') === '') $errors[] = 'Cage number is required.';
    if (!$errors) {
        $no = strtoupper(post('batch_no'));
        if ($no === '') {
            $n = (int)q_val("SELECT COUNT(*) FROM batches WHERE farm_id = ?", [$farm['id']]) + 1;
            do { $no = $farm['farm_code'] . '-B' . str_pad((string)$n++, 3, '0', STR_PAD_LEFT); } while (q_val("SELECT COUNT(*) FROM batches WHERE batch_no = ?", [$no]));
        } elseif (q_val("SELECT COUNT(*) FROM batches WHERE batch_no = ?", [$no])) {
            $errors[] = "Batch number $no already exists.";
        }
    }
    if (!$errors) {
        q("INSERT INTO batches (user_id, farm_id, location_id, batch_no, visit_datetime, cage_no, breed, hatchery, total_input, vaccination, treatment_history) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
          [uid(), $farm['id'], $loc['id'], $no, dt_local(post('visit_datetime')), post('cage_no'), nz(post('breed')), nz(post('hatchery')), (int)$total, nz(post('vaccination')), nz(post('treatment_history'))]);
        log_activity('batch', "Added batch $no (cage " . post('cage_no') . ") to {$farm['farm_name']}", 'user/batches.php?farm_id=' . $farm['id']);
        flash('success', "Batch $no saved.");
        redirect('user/batches.php?farm_id=' . $farm['id']);
    }
}
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if (!farm_list()): ?>
  <div class="panel"><div class="empty"><div class="egg-ico"><i class="bi bi-house-heart"></i></div><b>Register a farm first</b>Batches belong to a farm and one of its locations. <a href="<?= url('user/farm_register.php') ?>">Register a farm</a></div></div>
<?php else: ?>
<form method="post" class="panel" data-cascade>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Farm name</label>
      <select class="form-select" name="farm_id" data-farm required><?= farm_options((int)post('farm_id') ?: null) ?></select></div>
    <div class="col-md-6"><label class="form-label">Farm location</label>
      <select class="form-select" name="location_id" data-location data-selected="<?= h(post('location_id')) ?>" required><option value="">Select farm location</option></select></div>
    <div class="col-md-6"><label class="form-label">Visit date &amp; time</label><input class="form-control" type="datetime-local" name="visit_datetime" value="<?= h(post('visit_datetime', date('Y-m-d\TH:i'))) ?>"></div>
    <div class="col-md-6"><label class="form-label">Batch number</label><input class="form-control" name="batch_no" data-next-batch placeholder="Auto-generated" value="<?= h(post('batch_no')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Cage no</label><input class="form-control" name="cage_no" required value="<?= h(post('cage_no')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Breed</label><input class="form-control" name="breed" value="<?= h(post('breed')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Hatchery</label><input class="form-control" name="hatchery" value="<?= h(post('hatchery')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Total input (birds)</label><input class="form-control" name="total_input" inputmode="numeric" required value="<?= h(post('total_input')) ?>"></div>
    <div class="col-12"><label class="form-label">Vaccination details</label><textarea class="form-control" name="vaccination"><?= h(post('vaccination')) ?></textarea></div>
    <div class="col-12"><label class="form-label">Treatment history</label><textarea class="form-control" name="treatment_history"><?= h(post('treatment_history')) ?></textarea></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save batch details</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php';
