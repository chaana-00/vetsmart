<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['user']);
$pageTitle = 'Register farm'; $pageSub = 'Create the farm profile and pin its first location'; $active = 'farm_register'; $useMap = true;

$errors = []; $dupFarm = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('farm_name'); $code = strtoupper(post('farm_code'));
    $c = [nz(post('contact1')), nz(post('contact2')), nz(post('contact3'))];
    $oc = [nz(post('owner_contact1')), nz(post('owner_contact2'))];
    $type = post('farm_type'); $cap = post('capacity'); $locName = post('location_name');

    if ($name === '') $errors[] = 'Farm name is required.';
    if ($locName === '') $errors[] = 'Enter the farm location or pick it on the map.';
    if (!in_array($type, ['Broiler', 'Layer', 'Broiler & Layer', 'Other'], true)) $errors[] = 'Select a farm type.';
    foreach (array_merge($c, $oc) as $ph) if (!valid_phone($ph)) { $errors[] = 'Phone numbers must be exactly 10 digits.'; break; }
    if ($cap !== '' && !ctype_digit($cap)) $errors[] = 'Capacity must be a whole number.';
    if ($code === '') $code = 'FR' . str_pad((string)((int)q_val("SELECT COALESCE(MAX(id),0) FROM farms") + 1), 5, '0', STR_PAD_LEFT);
    if (q_val("SELECT COUNT(*) FROM farms WHERE farm_code = ?", [$code])) $errors[] = "Farm ID $code is already used.";
    if ($name !== '') {
        $dupFarm = q_one("SELECT f.id, f.farm_name, f.farm_code, u.full_name vet FROM farms f JOIN users u ON u.id = f.user_id WHERE LOWER(TRIM(f.farm_name)) = LOWER(?) LIMIT 1", [$name]);
        if ($dupFarm) $errors[] = 'This farm is already registered, so it was not created again.';
    }

    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            q("INSERT INTO farms (user_id, farm_name, farm_code, contact1, contact2, contact3, owner_name, owner_contact1, owner_contact2, farm_type, capacity, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
              [uid(), $name, $code, $c[0], $c[1], $c[2], nz(post('owner_name')), $oc[0], $oc[1], $type, $cap === '' ? null : (int)$cap, nz(post('note'))]);
            $fid = (int)$pdo->lastInsertId();
            q("INSERT INTO farm_locations (farm_id, location_name, latitude, longitude) VALUES (?,?,?,?)",
              [$fid, $locName, coord(post('latitude')), coord(post('longitude'))]);
            $pdo->commit();
            log_activity('farm', "Registered farm $name ($code) at $locName", 'user/farms.php?farm_id=' . $fid);
            flash('success', "Farm $name registered as $code. You can now add a cage and batch.");
            redirect('user/farms.php?farm_id=' . $fid);
        } catch (Throwable $e) { $pdo->rollBack(); $errors[] = 'Could not save the farm. Please try again.'; }
    }
}
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if ($dupFarm): ?><div class="alert alert-info soft-alert"><b><?= h($dupFarm['farm_name']) ?></b> (<?= h($dupFarm['farm_code']) ?>) was registered by <?= h($dupFarm['vet']) ?> and already appears in every farm dropdown.
  <a href="<?= url('user/farms.php?farm_id=' . $dupFarm['id']) ?>">View it</a> · <a href="<?= url('user/location_add.php?farm_id=' . $dupFarm['id']) ?>">Add another location to it</a> · <a href="<?= url('user/batch_add.php') ?>">Add a batch</a></div><?php endif; ?>
<form method="post" class="row g-4" novalidate>
  <?= csrf_field() ?>
  <div class="col-xl-6">
    <div class="panel h-100">
      <div class="panel-head"><h3>Farm details</h3></div>
      <div class="row g-3">
        <div class="col-md-7"><label class="form-label">Farm name</label><input class="form-control" name="farm_name" required value="<?= h(post('farm_name')) ?>"></div>
        <div class="col-md-5"><label class="form-label">Farm ID</label><input class="form-control" name="farm_code" placeholder="Auto, e.g. FR00001" value="<?= h(post('farm_code')) ?>"></div>
        <div class="col-12"><label class="form-label">Farm contact numbers</label>
          <div class="row g-2">
            <?php for ($i = 1; $i <= 3; $i++): ?><div class="col-md-4"><input class="form-control" name="contact<?= $i ?>" inputmode="numeric" maxlength="10" placeholder="0712345678" value="<?= h(post("contact$i")) ?>"></div><?php endfor; ?>
          </div></div>
        <div class="col-12"><label class="form-label">Farm owner name</label><input class="form-control" name="owner_name" placeholder="Full name" value="<?= h(post('owner_name')) ?>"></div>
        <div class="col-12"><label class="form-label">Owner contact numbers</label>
          <div class="row g-2">
            <?php for ($i = 1; $i <= 2; $i++): ?><div class="col-md-6"><input class="form-control" name="owner_contact<?= $i ?>" inputmode="numeric" maxlength="10" placeholder="0712345678" value="<?= h(post("owner_contact$i")) ?>"></div><?php endfor; ?>
          </div></div>
        <div class="col-md-6"><label class="form-label">Farm type</label>
          <select class="form-select" name="farm_type" required><option value="">Select type</option>
            <?php foreach (['Broiler', 'Layer', 'Broiler & Layer', 'Other'] as $t): ?><option <?= post('farm_type') === $t ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Farm capacity (birds)</label><input class="form-control" name="capacity" inputmode="numeric" value="<?= h(post('capacity')) ?>"></div>
        <div class="col-12"><label class="form-label">Note</label><textarea class="form-control" name="note"><?= h(post('note')) ?></textarea></div>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="panel h-100">
      <div class="panel-head"><h3>First location</h3></div>
      <?php include __DIR__ . '/../includes/map_picker.php'; ?>
    </div>
  </div>
  <div class="col-12"><button class="btn btn-vs px-4" type="submit"><i class="bi bi-check2-circle"></i> Save farm</button></div>
</form>
<?php include __DIR__ . '/../includes/footer.php';
