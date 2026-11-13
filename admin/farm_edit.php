<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$farm = find_farm((int)get('id', post('id')));
if (!$farm) { flash('error', 'Farm not found.'); redirect('user/farms.php'); }
$pageTitle = 'Edit farm'; $pageSub = $farm['farm_name'] . ' (' . $farm['farm_code'] . ')'; $active = 'farms';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('farm_name'); $code = strtoupper(post('farm_code'));
    $c = [nz(post('contact1')), nz(post('contact2')), nz(post('contact3'))];
    $oc = [nz(post('owner_contact1')), nz(post('owner_contact2'))];
    $type = post('farm_type'); $cap = post('capacity');
    if ($name === '') $errors[] = 'Farm name is required.';
    if ($code === '') $errors[] = 'Farm ID is required.';
    if (!in_array($type, ['Broiler', 'Layer', 'Broiler & Layer', 'Other'], true)) $errors[] = 'Select a farm type.';
    foreach (array_merge($c, $oc) as $ph) if (!valid_phone($ph)) { $errors[] = 'Phone numbers must be exactly 10 digits.'; break; }
    if ($cap !== '' && !ctype_digit($cap)) $errors[] = 'Capacity must be a whole number.';
    if ($code !== '' && q_val("SELECT COUNT(*) FROM farms WHERE farm_code = ? AND id <> ?", [$code, $farm['id']])) $errors[] = "Farm ID $code is already used by another farm.";
    if ($name !== '' && q_val("SELECT COUNT(*) FROM farms WHERE LOWER(TRIM(farm_name)) = LOWER(?) AND id <> ?", [$name, $farm['id']])) $errors[] = 'Another farm already has this name.';
    if (!$errors) {
        q("UPDATE farms SET farm_name=?, farm_code=?, contact1=?, contact2=?, contact3=?, owner_name=?, owner_contact1=?, owner_contact2=?, farm_type=?, capacity=?, note=? WHERE id=?",
          [$name, $code, $c[0], $c[1], $c[2], nz(post('owner_name')), $oc[0], $oc[1], $type, $cap === '' ? null : (int)$cap, nz(post('note')), $farm['id']]);
        log_activity('farm', "Admin updated farm $name ($code)", 'user/farms.php?farm_id=' . $farm['id']);
        flash('success', 'Farm details updated.');
        redirect('admin/farm_edit.php?id=' . $farm['id']);
    }
    $farm = array_merge($farm, $_POST);
}
$locs = q_all("SELECT * FROM farm_locations WHERE farm_id = ? ORDER BY location_name ASC", [$farm['id']]);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/delete_form.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="panel" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $farm['id'] ?>">
  <div class="panel-head"><h3>Farm details</h3><div class="ms-auto">
    <a class="btn btn-sm btn-soft" href="<?= url('user/farms.php') ?>"><i class="bi bi-arrow-left"></i> All farms</a>
    <button type="button" class="btn btn-sm btn-del-x" data-del="farm:<?= $farm['id'] ?>" data-return="user/farms.php" data-msg="Delete <?= h($farm['farm_name']) ?> and ALL its locations, batches, visit records, images and prescriptions? This cannot be undone."><i class="bi bi-trash"></i> Delete farm</button></div></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Farm name</label><input class="form-control" name="farm_name" required value="<?= h($farm['farm_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Farm ID</label><input class="form-control" name="farm_code" required value="<?= h($farm['farm_code']) ?>"></div>
    <div class="col-12"><label class="form-label">Farm contact numbers</label><div class="row g-2">
      <?php for ($i = 1; $i <= 3; $i++): ?><div class="col-md-4"><input class="form-control" name="contact<?= $i ?>" maxlength="10" inputmode="numeric" value="<?= h($farm["contact$i"]) ?>"></div><?php endfor; ?></div></div>
    <div class="col-md-6"><label class="form-label">Farm owner name</label><input class="form-control" name="owner_name" value="<?= h($farm['owner_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Owner contact numbers</label><div class="row g-2">
      <?php for ($i = 1; $i <= 2; $i++): ?><div class="col-6"><input class="form-control" name="owner_contact<?= $i ?>" maxlength="10" inputmode="numeric" value="<?= h($farm["owner_contact$i"]) ?>"></div><?php endfor; ?></div></div>
    <div class="col-md-6"><label class="form-label">Farm type</label><select class="form-select" name="farm_type">
      <?php foreach (['Broiler', 'Layer', 'Broiler & Layer', 'Other'] as $t): ?><option <?= $farm['farm_type'] === $t ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Farm capacity (birds)</label><input class="form-control" name="capacity" inputmode="numeric" value="<?= h($farm['capacity']) ?>"></div>
    <div class="col-12"><label class="form-label">Note</label><textarea class="form-control" name="note"><?= h($farm['note']) ?></textarea></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save changes</button>
</form>

<div class="panel">
  <div class="panel-head"><h3>Locations <span class="pill gray ms-1"><?= count($locs) ?></span></h3>
    <div class="ms-auto"><a class="btn btn-sm btn-vs" href="<?= url('admin/location_edit.php?farm_id=' . $farm['id']) ?>"><i class="bi bi-plus-lg"></i> Add location</a></div></div>
  <div class="table-wrap"><table class="table table-vs"><thead><tr><th>Location</th><th>Latitude</th><th>Longitude</th><th></th></tr></thead><tbody>
    <?php foreach ($locs as $l): ?><tr><td><?= h($l['location_name']) ?></td><td><?= h($l['latitude']) ?></td><td><?= h($l['longitude']) ?></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-soft-blue" href="<?= url('admin/location_edit.php?id=' . $l['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
        <button type="button" class="btn btn-sm btn-del-x" data-del="location:<?= $l['id'] ?>" data-msg="Delete location <?= h($l['location_name']) ?>?"><i class="bi bi-trash"></i></button></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php include __DIR__ . '/../includes/footer.php';
