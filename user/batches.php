<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$pageTitle = 'Cages & batches'; $pageSub = 'Search by farm and location, then export to Word'; $active = 'batches';

$farmId = (int)get('farm_id'); $locId = (int)get('location_id');
$p = []; $w = '';
$userId = is_admin() ? (int)get('user_id') : 0;
if ($userId) { $w .= ' AND b.user_id = ?'; $p[] = $userId; }
if ($farmId) { $w .= ' AND b.farm_id = ?'; $p[] = $farmId; }
if ($locId)  { $w .= ' AND b.location_id = ?'; $p[] = $locId; }
$rows = q_all("SELECT b.*, f.farm_name, f.farm_code, l.location_name, u.full_name vet FROM batches b
  JOIN farms f ON f.id = b.farm_id JOIN farm_locations l ON l.id = b.location_id JOIN users u ON u.id = b.user_id
  WHERE 1=1 $w ORDER BY f.farm_name ASC, l.location_name ASC, b.batch_no ASC", $p);
$qs = http_build_query(array_filter(['user_id' => $userId, 'farm_id' => $farmId, 'location_id' => $locId]));

include __DIR__ . '/../includes/header.php';
if (is_admin()) include __DIR__ . '/../includes/delete_form.php';
?>
<div class="panel">
  <form class="row g-3 align-items-end" method="get" data-cascade>
    <?php if (is_admin()): ?><div class="col-md-3"><label class="form-label">Added by (veterinarian)</label><select class="form-select" name="user_id"><?= vet_options($userId ?: null) ?></select></div><?php endif; ?>
    <div class="col-md-<?= is_admin() ? 3 : 5 ?>"><label class="form-label">Farm name</label><select class="form-select" name="farm_id" data-farm><?= farm_options($farmId ?: null, 'All farms') ?></select></div>
    <div class="col-md-<?= is_admin() ? 4 : 5 ?>"><label class="form-label">Farm location</label>
      <select class="form-select" name="location_id" data-location data-autopick="off" data-selected="<?= $locId ?: '' ?>"><option value="">All locations</option></select></div>
    <div class="col-md-2"><button class="btn btn-vs w-100"><i class="bi bi-search"></i> Search</button></div>
  </form>
</div>

<form method="get" action="<?= url('download/docx.php') ?>" class="panel">
  <input type="hidden" name="type" value="batches">
  <div class="panel-head"><h3>Batch records <span class="pill gray ms-1"><?= count($rows) ?></span></h3>
    <div class="ms-auto">
      <?php if (!is_admin()): ?><a class="btn btn-sm btn-soft" href="<?= url('user/batch_add.php') ?>"><i class="bi bi-plus-lg"></i> Add batch</a><?php endif; ?>
      <button class="btn btn-sm btn-soft-blue" type="submit"><i class="bi bi-download"></i> Download selected (.docx)</button>
      <a class="btn btn-sm btn-vs" href="<?= url('download/docx.php?type=batches' . ($qs ? '&' . $qs : '')) ?>"><i class="bi bi-file-earmark-word"></i> Download all<?= $qs ? ' (filtered)' : '' ?></a>
    </div></div>
  <?php if (!$rows): ?>
    <div class="empty"><div class="egg-ico"><i class="bi bi-collection"></i></div><b>No batches found</b>Try another farm or location.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-vs blue">
    <thead><tr><th><input type="checkbox" class="form-check-input" data-check-all=".rowchk" aria-label="Select all"></th><th>Farm</th><th>Location</th><th>Visit date &amp; time</th><th>Batch no</th><th>Cage</th><th>Breed</th><th>Hatchery</th><th class="num">Total input</th><th>Vaccination</th><th>Treatment history</th><th>Added by</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr><td><input type="checkbox" class="form-check-input rowchk" name="ids[]" value="<?= $r['id'] ?>"></td>
        <td><b><?= h($r['farm_name']) ?></b></td><td><?= h($r['location_name']) ?></td><td class="text-nowrap"><?= h(fmt_dt($r['visit_datetime'])) ?></td>
        <td><span class="pill blue"><?= h($r['batch_no']) ?></span></td><td><?= h($r['cage_no']) ?></td><td><?= h($r['breed']) ?></td><td><?= h($r['hatchery']) ?></td>
        <td class="num"><?= number_format($r['total_input']) ?></td><td><?= h($r['vaccination']) ?></td><td><?= h($r['treatment_history']) ?></td>
        <td><?= h($r['vet']) ?></td>
        <?php if (is_admin()): ?><td class="text-nowrap"><a class="btn btn-sm btn-soft" href="<?= url('admin/batch_edit.php?id=' . $r['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
          <button type="button" class="btn btn-sm btn-del-x" data-del="batch:<?= $r['id'] ?>" data-msg="Delete batch <?= h($r['batch_no']) ?> with ALL its visit records, images and prescriptions?" title="Delete"><i class="bi bi-trash"></i></button></td><?php endif; ?></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</form>
<?php include __DIR__ . '/../includes/footer.php';
