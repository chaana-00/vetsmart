<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['user']);
$farm = find_farm((int)get('farm_id', post('farm_id')));
if (!$farm) { flash('error', 'Farm not found.'); redirect('user/farms.php'); }
$pageTitle = 'Add location'; $pageSub = 'Another site for ' . $farm['farm_name']; $active = 'farms'; $useMap = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('location_name') === '') { flash('error', 'Enter the location name or pick it on the map.'); }
    else {
        q("INSERT INTO farm_locations (farm_id, location_name, latitude, longitude) VALUES (?,?,?,?)", [$farm['id'], post('location_name'), coord(post('latitude')), coord(post('longitude'))]);
        log_activity('farm', 'Added location ' . post('location_name') . ' to ' . $farm['farm_name'], 'user/farms.php?farm_id=' . $farm['id']);
        flash('success', 'Location added.'); redirect('user/farms.php?farm_id=' . $farm['id']);
    }
}
include __DIR__ . '/../includes/header.php';
?>
<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="farm_id" value="<?= $farm['id'] ?>">
  <?php include __DIR__ . '/../includes/map_picker.php'; ?>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-vs" type="submit">Save location</button><a class="btn btn-soft" href="<?= url('user/farms.php?farm_id=' . $farm['id']) ?>">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php';
