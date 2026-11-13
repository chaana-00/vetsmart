<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login(['admin', 'user']);
$type = get('type') === 'layer' ? 'layer' : 'broiler';
$p = [(int)get('id')];
$r = q_one("SELECT r.*, f.farm_name, f.farm_code, l.location_name, b.batch_no, u.full_name vet FROM {$type}_records r
  JOIN farms f ON f.id = r.farm_id JOIN farm_locations l ON l.id = r.location_id JOIN batches b ON b.id = r.batch_id JOIN users u ON u.id = r.user_id
  WHERE r.id = ?", $p);
if (!$r) { flash('error', 'Record not found.'); redirect('user/records.php?type=' . $type); }
$pageTitle = ucfirst($type) . ' record ' . $r['record_no']; $pageSub = $r['farm_name'] . ' · ' . $r['location_name']; $active = 'records';
$labels = require __DIR__ . '/../includes/record_labels.php';
$imgs = record_images($type, (int)$r['id']);
include __DIR__ . '/../includes/header.php';
if (is_admin()) include __DIR__ . '/../includes/delete_form.php';
?>
<div class="panel">
  <div class="panel-head"><h3>Visit details</h3><div class="ms-auto">
    <a class="btn btn-sm btn-soft" href="<?= url('user/records.php?type=' . $type) ?>"><i class="bi bi-arrow-left"></i> All records</a>
    <a class="btn btn-sm btn-vs" href="<?= url("download/docx.php?type=$type&id={$r['id']}") ?>"><i class="bi bi-file-earmark-word"></i> Download .docx</a>
    <?php if (is_admin()): ?><a class="btn btn-sm btn-soft-blue" href="<?= url("admin/record_edit.php?type=$type&id={$r['id']}") ?>"><i class="bi bi-pencil"></i> Edit</a>
    <button type="button" class="btn btn-sm btn-del-x" data-del="<?= $type ?>:<?= $r['id'] ?>" data-return="user/records.php?type=<?= $type ?>" data-msg="Delete record <?= h($r['record_no']) ?> and its post-mortem images?"><i class="bi bi-trash"></i> Delete</button><?php endif; ?></div></div>
  <div class="kv">
    <div><small>Farm</small><b><?= h($r['farm_name']) ?> (<?= h($r['farm_code']) ?>)</b></div>
    <div><small>Location</small><b><?= h($r['location_name']) ?></b></div>
    <div><small>Batch</small><b><?= h($r['batch_no']) ?></b></div>
    <div><small>Recorded by</small><b><?= h($r['vet']) ?></b></div>
    <?php foreach ($labels[$type] as $f => $label): if ($f === 'visit_date') $v = fmt_dt($r[$f]); else $v = $r[$f]; ?>
      <div class="<?= in_array($f, ['complaint', 'clinical_signs', 'post_mortem_changes', 'dd', 'test_request', 'test_report', 'recommendation', 'treatment', 'follow_ups'], true) ? 'wide' : '' ?>"><small><?= h($label) ?></small><b><?= h($v !== null && $v !== '' ? $v : '—') ?></b></div>
    <?php endforeach; ?>
  </div>
</div>
<div class="panel">
  <div class="panel-head"><h3>Post-mortem images <span class="pill gray ms-1"><?= count($imgs) ?></span></h3></div>
  <?php if ($imgs): ?><div class="gallery"><?php foreach ($imgs as $i): ?>
    <a href="<?= url('download/image.php?id=' . $i['id']) ?>" target="_blank" title="<?= h(basename($i['file_name'])) ?>"><img loading="lazy" src="<?= url('download/image.php?id=' . $i['id']) ?>" alt="Post-mortem image"></a>
  <?php endforeach; ?></div>
  <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-image"></i></div><b>No images attached</b></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php';
