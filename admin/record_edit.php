<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login(['admin']);
$type = get('type', post('type')) === 'layer' ? 'layer' : 'broiler';
$fields = (require __DIR__ . '/../includes/record_fields.php')[$type];
$r = q_one("SELECT r.*, f.farm_name, f.farm_code, l.location_name, b.batch_no FROM {$type}_records r
  JOIN farms f ON f.id = r.farm_id JOIN farm_locations l ON l.id = r.location_id JOIN batches b ON b.id = r.batch_id WHERE r.id = ?", [(int)get('id', post('id'))]);
if (!$r) { flash('error', 'Record not found.'); redirect('user/records.php?type=' . $type); }
$pageTitle = 'Edit ' . $type . ' record'; $pageSub = $r['record_no'] . ' · ' . $r['farm_name']; $active = 'records';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('flock_size') !== '' && !ctype_digit(post('flock_size'))) $errors[] = 'Flock size must be a whole number.';
    if (!$errors) {
        $sets = []; $vals = [];
        foreach ($fields as $f => $def) {
            $sets[] = "$f = ?";
            if ($f === 'visit_date') $vals[] = dt_local(post($f));
            elseif ($f === 'flock_size') $vals[] = post($f) === '' ? null : (int)post($f);
            else $vals[] = nz(post($f));
        }
        $vals[] = $r['id'];
        q("UPDATE {$type}_records SET " . implode(', ', $sets) . " WHERE id = ?", $vals);
        $added = save_postmortem($type, (int)$r['id'], $r['record_no'], find_farm((int)$r['farm_id']), find_location((int)$r['location_id'], (int)$r['farm_id']), 'images_new');
        log_activity($type, "Admin updated $type record {$r['record_no']}" . ($added ? ", added $added image(s)" : ''), "user/record_view.php?type=$type&id={$r['id']}");
        flash('success', "Record {$r['record_no']} updated.");
        redirect("admin/record_edit.php?type=$type&id={$r['id']}");
    }
    $r = array_merge($r, $_POST);
}
$imgs = record_images($type, (int)$r['id']);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/delete_form.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="panel">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="type" value="<?= $type ?>">
  <div class="panel-head"><h3><?= h($r['record_no']) ?></h3><div class="ms-auto">
    <a class="btn btn-sm btn-soft" href="<?= url("user/record_view.php?type=$type&id={$r['id']}") ?>"><i class="bi bi-eye"></i> View</a>
    <a class="btn btn-sm btn-soft" href="<?= url('user/records.php?type=' . $type) ?>"><i class="bi bi-arrow-left"></i> All records</a>
    <button type="button" class="btn btn-sm btn-del-x" data-del="<?= $type ?>:<?= $r['id'] ?>" data-return="user/records.php?type=<?= $type ?>" data-msg="Delete record <?= h($r['record_no']) ?> and its post-mortem images?"><i class="bi bi-trash"></i> Delete record</button></div></div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Farm</label><input class="form-control" disabled value="<?= h($r['farm_name'] . ' (' . $r['farm_code'] . ')') ?>"></div>
    <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" disabled value="<?= h($r['location_name']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Batch</label><input class="form-control" disabled value="<?= h($r['batch_no']) ?>"></div>
    <?php foreach ($fields as $f => [$label, $kind, $w]):
        $val = $r[$f] ?? '';
        if ($f === 'visit_date' && $val !== '' && $val !== null) $val = date('Y-m-d\TH:i', strtotime((string)$val)); ?>
      <div class="col-md-<?= $w ?>"><label class="form-label"><?= h($label) ?></label>
        <?php if ($kind === 'area'): ?><textarea class="form-control" name="<?= $f ?>"><?= h($val) ?></textarea>
        <?php else: ?><input class="form-control" type="<?= $kind ?>" name="<?= $f ?>" value="<?= h($val) ?>"><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="col-12"><label class="form-label">Add post-mortem images</label>
      <input class="form-control" type="file" name="images_new[]" accept="image/jpeg,image/png,image/gif" multiple>
      <div class="form-text">JPG, PNG or GIF, up to <?= MAX_UPLOAD_MB ?> MB each.</div></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-check2-circle"></i> Save changes</button>
</form>

<div class="panel">
  <div class="panel-head"><h3>Post-mortem images <span class="pill gray ms-1"><?= count($imgs) ?></span></h3></div>
  <?php if ($imgs): ?><div class="gallery"><?php foreach ($imgs as $i): ?>
    <div class="g-item"><a href="<?= url('download/image.php?id=' . $i['id']) ?>" target="_blank" title="<?= h(basename($i['file_name'])) ?>"><img loading="lazy" src="<?= url('download/image.php?id=' . $i['id']) ?>" alt="Post-mortem image"></a>
      <button type="button" class="g-del" data-del="image:<?= $i['id'] ?>" data-msg="Remove this image?" aria-label="Remove image"><i class="bi bi-trash"></i></button></div>
  <?php endforeach; ?></div>
  <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-image"></i></div><b>No images attached</b></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php';
