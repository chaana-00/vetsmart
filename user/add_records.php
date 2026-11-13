<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login(['user']);
$pageTitle = 'New visit record'; $pageSub = 'Log a broiler or layer farm visit'; $active = 'add_records';

/* field => [label, input kind, col width] */
$common_top = [
    'registration_number' => ['Registration number', 'text', 6],
    'visit_date'          => ['Visit date & time', 'datetime-local', 6],
    'flock_size'          => ['Flock size', 'number', 4],
    'age'                 => ['Age (days / weeks)', 'text', 4],
];
$types = [
  'broiler' => ['label' => 'Broiler', 'prefix' => 'BR', 'table' => 'broiler_records', 'fields' => $common_top + [
      'average_weight' => ['Average weight', 'text', 4], 'weight_gain' => ['Weight gain', 'text', 4], 'feed_intake' => ['Feed intake', 'text', 4],
      'mortality' => ['Mortality', 'text', 4], 'mortality_pct' => ['Mortality %', 'text', 4],
      'complaint' => ['Complaint', 'area', 12], 'clinical_signs' => ['Clinical signs', 'area', 12], 'dd' => ['DD (differential diagnosis)', 'text', 12],
      'test_request' => ['Test request', 'area', 6], 'test_report' => ['Test report', 'area', 6],
      'recommendation' => ['Recommendation', 'area', 6], 'treatment' => ['Treatment', 'area', 6], 'follow_ups' => ['Follow ups', 'area', 12]]],
  'layer' => ['label' => 'Layer', 'prefix' => 'LR', 'table' => 'layer_records', 'fields' => $common_top + [
      'average_age' => ['Average age', 'text', 4], 'egg_production' => ['Egg production', 'text', 4], 'egg_production_pct' => ['Egg production %', 'text', 4],
      'feed_intake' => ['Feed intake', 'text', 4], 'mortality' => ['Mortality', 'text', 4], 'mortality_pct' => ['Mortality %', 'text', 4],
      'complaint' => ['Complaint / visit purpose', 'area', 12], 'clinical_signs' => ['Clinical signs', 'area', 12], 'post_mortem_changes' => ['Post-mortem changes', 'area', 12],
      'dd' => ['DD (differential diagnosis)', 'text', 12], 'test_request' => ['Test request', 'area', 6], 'test_report' => ['Test report', 'area', 6],
      'recommendation' => ['Recommendation / advice', 'area', 6], 'treatment' => ['Treatment', 'area', 6], 'follow_ups' => ['Follow ups', 'area', 12]]],
];
$tab = get('tab', post('form_type', 'broiler')); if (!isset($types[$tab])) $tab = 'broiler';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($types[post('form_type')])) {
    csrf_check();
    $tab = post('form_type'); $T = $types[$tab];
    $farm  = find_farm((int)post('farm_id'));
    $loc   = $farm ? find_location((int)post('location_id'), (int)$farm['id']) : null;
    $batch = ($farm && $loc) ? find_batch((int)post('batch_id'), (int)$farm['id']) : null;
    if (!$farm) $errors[] = 'Select a farm.';
    elseif (!$loc) $errors[] = 'Select the farm location.';
    elseif (!$batch) $errors[] = 'Select a batch for this farm.';
    if (post('flock_size') !== '' && !ctype_digit(post('flock_size'))) $errors[] = 'Flock size must be a whole number.';

    if (!$errors) {
        $cols = ['user_id', 'farm_id', 'location_id', 'batch_id']; $vals = [uid(), $farm['id'], $loc['id'], $batch['id']];
        foreach ($T['fields'] as $f => $def) {
            $cols[] = $f;
            if ($f === 'visit_date') $vals[] = dt_local(post($f));
            elseif ($f === 'flock_size') $vals[] = post($f) === '' ? null : (int)post($f);
            else $vals[] = nz(post($f));
        }
        $pdo = db();
        $sql = "INSERT INTO {$T['table']} (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
        q($sql, $vals);
        $rid = (int)$pdo->lastInsertId();
        $no = $T['prefix'] . '-' . str_pad((string)$rid, 6, '0', STR_PAD_LEFT);
        q("UPDATE {$T['table']} SET record_no = ? WHERE id = ?", [$no, $rid]);
        $imgs = save_postmortem($tab, $rid, $no, $farm, $loc, 'images_' . $tab);
        log_activity($tab, "{$T['label']} record $no saved for {$farm['farm_name']} ({$batch['batch_no']})" . ($imgs ? ", $imgs image(s)" : ''), "user/record_view.php?type=$tab&id=$rid");
        flash('success', "{$T['label']} record $no saved" . ($imgs ? " with $imgs post-mortem image(s)." : '.'));
        redirect("user/record_view.php?type=$tab&id=$rid");
    }
}
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if (!farm_list()): ?>
  <div class="panel"><div class="empty"><div class="egg-ico"><i class="bi bi-house-heart"></i></div><b>Register a farm and a batch first</b>Visit records are linked to a farm, location and batch. <a href="<?= url('user/farm_register.php') ?>">Register a farm</a></div></div>
<?php else: ?>
<div class="seg" role="tablist">
  <button type="button" data-tab="broiler" class="<?= $tab === 'broiler' ? 'on-g' : '' ?>"><i class="bi bi-clipboard2-pulse"></i> Broiler</button>
  <button type="button" data-tab="layer" class="<?= $tab === 'layer' ? 'on-b' : '' ?>"><i class="bi bi-clipboard2-data"></i> Layer</button>
</div>

<?php foreach ($types as $key => $T): $isPosted = ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_type') === $key); ?>
<form method="post" enctype="multipart/form-data" class="panel" id="form-<?= $key ?>" data-cascade <?= $tab === $key ? '' : 'hidden' ?>>
  <?= csrf_field() ?><input type="hidden" name="form_type" value="<?= $key ?>">
  <div class="panel-head"><h3><?= $T['label'] ?> visit record</h3></div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Farm name</label>
      <select class="form-select" name="farm_id" data-farm required><?= farm_options($isPosted ? ((int)post('farm_id') ?: null) : null) ?></select></div>
    <div class="col-md-4"><label class="form-label">Farm location</label>
      <select class="form-select" name="location_id" data-location data-selected="<?= $isPosted ? h(post('location_id')) : '' ?>" required><option value="">Select farm location</option></select></div>
    <div class="col-md-4"><label class="form-label">Batch number</label>
      <select class="form-select" name="batch_id" data-batch data-selected="<?= $isPosted ? h(post('batch_id')) : '' ?>" required><option value="">Select batch</option></select></div>
    <?php foreach ($T['fields'] as $f => [$label, $kind, $w]):
        $val = $isPosted ? post($f) : ($f === 'visit_date' ? date('Y-m-d\TH:i') : ''); ?>
      <div class="col-md-<?= $w ?>"><label class="form-label"><?= h($label) ?></label>
        <?php if ($kind === 'area'): ?><textarea class="form-control" name="<?= $f ?>"><?= h($val) ?></textarea>
        <?php else: ?><input class="form-control" type="<?= $kind ?>" name="<?= $f ?>" value="<?= h($val) ?>" <?= $kind === 'number' ? 'inputmode="numeric" min="0"' : '' ?>><?php endif; ?></div>
      <?php if ($f === ($key === 'broiler' ? 'clinical_signs' : 'post_mortem_changes')): ?>
      <div class="col-12"><label class="form-label">Post-mortem images</label>
        <input class="form-control" type="file" name="images_<?= $key ?>[]" accept="image/jpeg,image/png,image/gif" multiple>
        <div class="form-text">JPG, PNG or GIF, up to <?= MAX_UPLOAD_MB ?> MB each. Saved as farm name + farm ID + location + record number.</div></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save <?= strtolower($T['label']) ?> record</button>
</form>
<?php endforeach; ?>
<?php endif; ?>
<?php
$extraJs = '<script>
(function(){
  const tabs=document.querySelectorAll(".seg [data-tab]");
  tabs.forEach(b=>b.addEventListener("click",()=>{
    tabs.forEach(x=>x.className="");
    b.className=b.dataset.tab==="broiler"?"on-g":"on-b";
    document.querySelectorAll("form[id^=form-]").forEach(f=>f.hidden=f.id!=="form-"+b.dataset.tab);
  }));
  document.querySelectorAll("form[id^=form-]").forEach(f=>{
    const m=f.querySelector("[name=mortality]"),s=f.querySelector("[name=flock_size]"),p=f.querySelector("[name=mortality_pct]");
    if(!m||!s||!p)return; p.addEventListener("input",()=>p.dataset.t="1");
    const calc=()=>{const a=parseFloat(m.value),b=parseFloat(s.value);if(!p.dataset.t&&a>=0&&b>0)p.value=(a/b*100).toFixed(2)+"%"};
    m.addEventListener("input",calc);s.addEventListener("input",calc);
  });
})();
</script>';
include __DIR__ . '/../includes/footer.php';
