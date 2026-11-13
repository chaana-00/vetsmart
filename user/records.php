<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$pageTitle = 'Visit records'; $pageSub = 'Saved broiler and layer records'; $active = 'records';

$type = get('type', 'broiler') === 'layer' ? 'layer' : 'broiler';
$table = $type . '_records';
$farmId = (int)get('farm_id'); $locId = (int)get('location_id'); $batId = (int)get('batch_id');
$p = []; $w = '';
$userId = is_admin() ? (int)get('user_id') : 0;
if ($userId) { $w .= ' AND r.user_id = ?'; $p[] = $userId; }
foreach (['farm_id' => $farmId, 'location_id' => $locId, 'batch_id' => $batId] as $col => $v) if ($v) { $w .= " AND r.$col = ?"; $p[] = $v; }
$rows = q_all("SELECT r.*, f.farm_name, l.location_name, b.batch_no, u.full_name vet,
    (SELECT COUNT(*) FROM record_images i WHERE i.record_type = '$type' AND i.record_id = r.id) imgs
  FROM $table r JOIN farms f ON f.id = r.farm_id JOIN farm_locations l ON l.id = r.location_id JOIN batches b ON b.id = r.batch_id JOIN users u ON u.id = r.user_id
  WHERE 1=1 $w ORDER BY r.id DESC", $p);
$qs = http_build_query(array_filter(['user_id' => $userId, 'farm_id' => $farmId, 'location_id' => $locId, 'batch_id' => $batId]));

include __DIR__ . '/../includes/header.php';
if (is_admin()) include __DIR__ . '/../includes/delete_form.php';
?>
<div class="seg">
  <a href="?type=broiler<?= $userId ? '&user_id=' . $userId : '' ?>" class="<?= $type === 'broiler' ? 'on-g' : '' ?>"><i class="bi bi-clipboard2-pulse"></i> Broiler</a>
  <a href="?type=layer<?= $userId ? '&user_id=' . $userId : '' ?>" class="<?= $type === 'layer' ? 'on-b' : '' ?>"><i class="bi bi-clipboard2-data"></i> Layer</a>
</div>
<div class="panel">
  <form class="row g-3 align-items-end" method="get" data-cascade>
    <input type="hidden" name="type" value="<?= $type ?>">
    <?php if (is_admin()): ?><div class="col-md-3"><label class="form-label">Recorded by (veterinarian)</label><select class="form-select" name="user_id"><?= vet_options($userId ?: null) ?></select></div><?php endif; ?>
    <div class="col-md-<?= is_admin() ? 3 : 4 ?>"><label class="form-label">Farm name</label><select class="form-select" name="farm_id" data-farm><?= farm_options($farmId ?: null, 'All farms') ?></select></div>
    <div class="col-md-<?= is_admin() ? 2 : 3 ?>"><label class="form-label">Location</label><select class="form-select" name="location_id" data-location data-autopick="off" data-selected="<?= $locId ?: '' ?>"><option value="">All locations</option></select></div>
    <div class="col-md-<?= is_admin() ? 2 : 3 ?>"><label class="form-label">Batch</label><select class="form-select" name="batch_id" data-batch data-autopick="off" data-selected="<?= $batId ?: '' ?>"><option value="">All batches</option></select></div>
    <div class="col-md-<?= is_admin() ? 2 : 2 ?>"><button class="btn btn-vs w-100"><i class="bi bi-search"></i> Search</button></div>
  </form>
</div>
<form method="get" action="<?= url('download/docx.php') ?>" class="panel">
  <input type="hidden" name="type" value="<?= $type ?>">
  <div class="panel-head"><h3><?= ucfirst($type) ?> records <span class="pill gray ms-1"><?= count($rows) ?></span></h3>
    <div class="ms-auto">
      <?php if (!is_admin()): ?><a class="btn btn-sm btn-soft" href="<?= url('user/add_records.php?tab=' . $type) ?>"><i class="bi bi-plus-lg"></i> New record</a><?php endif; ?>
      <button class="btn btn-sm btn-soft-blue" type="submit"><i class="bi bi-download"></i> Download selected (.docx)</button>
      <a class="btn btn-sm btn-vs" href="<?= url('download/docx.php?type=' . $type . ($qs ? '&' . $qs : '')) ?>"><i class="bi bi-file-earmark-word"></i> Download all<?= $qs ? ' (filtered)' : '' ?></a>
    </div></div>
  <?php if (!$rows): ?>
    <div class="empty"><div class="egg-ico"><i class="bi bi-clipboard2"></i></div><b>No <?= $type ?> records found</b>Saved visit records will be listed here.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-vs <?= $type === 'layer' ? 'blue' : '' ?>">
    <thead><tr><th><input type="checkbox" class="form-check-input" data-check-all=".rowchk" aria-label="Select all"></th><th>Record</th><th>Farm</th><th>Location</th><th>Batch</th><th>Visit</th><th class="num">Flock</th><th>Age</th><th>Mortality</th><th>Images</th><th>Recorded by</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr><td><input type="checkbox" class="form-check-input rowchk" name="ids[]" value="<?= $r['id'] ?>"></td>
        <td><span class="pill <?= $type === 'layer' ? 'blue' : '' ?>"><?= h($r['record_no']) ?></span></td>
        <td><b><?= h($r['farm_name']) ?></b></td><td><?= h($r['location_name']) ?></td><td><?= h($r['batch_no']) ?></td>
        <td class="text-nowrap"><?= h(fmt_dt($r['visit_date'])) ?></td><td class="num"><?= $r['flock_size'] !== null ? number_format($r['flock_size']) : '—' ?></td>
        <td><?= h($r['age']) ?></td><td><?= h($r['mortality']) ?><?= $r['mortality_pct'] ? ' <small class="muted">(' . h($r['mortality_pct']) . ')</small>' : '' ?></td>
        <td><?= $r['imgs'] ? '<i class="bi bi-image"></i> ' . (int)$r['imgs'] : '—' ?></td>
        <td><?= h($r['vet']) ?></td>
        <td class="text-nowrap"><a class="btn btn-sm btn-soft" href="<?= url("user/record_view.php?type=$type&id={$r['id']}") ?>">View</a>
          <a class="btn btn-sm btn-soft-blue" href="<?= url("download/docx.php?type=$type&id={$r['id']}") ?>" title="Download .docx"><i class="bi bi-download"></i></a>
          <?php if (is_admin()): ?><a class="btn btn-sm btn-soft" href="<?= url("admin/record_edit.php?type=$type&id={$r['id']}") ?>"><i class="bi bi-pencil"></i></a>
          <button type="button" class="btn btn-sm btn-del-x" data-del="<?= $type ?>:<?= $r['id'] ?>" data-msg="Delete record <?= h($r['record_no']) ?> and its post-mortem images?" title="Delete"><i class="bi bi-trash"></i></button><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</form>
<?php include __DIR__ . '/../includes/footer.php';
