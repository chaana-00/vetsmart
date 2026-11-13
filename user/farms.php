<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$pageTitle = 'Farm records'; $pageSub = 'Every registered farm, with its locations'; $active = 'farms';

$sel = (int)get('farm_id');
$p = []; $where = '';
$userId = is_admin() ? (int)get('user_id') : 0;
if ($sel) { $where .= ' AND f.id = ?'; $p[] = $sel; }
if ($userId) { $where .= ' AND f.user_id = ?'; $p[] = $userId; }
$farms = q_all("SELECT f.*, u.full_name vet FROM farms f JOIN users u ON u.id = f.user_id WHERE 1=1 $where ORDER BY f.farm_name ASC", $p);
$locs = [];
foreach (q_all("SELECT * FROM farm_locations ORDER BY location_name ASC") as $l) $locs[$l['farm_id']][] = $l;

include __DIR__ . '/../includes/header.php';
if (is_admin()) include __DIR__ . '/../includes/delete_form.php';
?>
<div class="panel">
  <form class="row g-3 align-items-end" method="get">
    <?php if (is_admin()): ?><div class="col-md-3"><label class="form-label">Registered by (veterinarian)</label>
      <select class="form-select" name="user_id"><?= vet_options($userId ?: null) ?></select></div><?php endif; ?>
    <div class="col-md-4"><label class="form-label">Farm</label>
      <select class="form-select" name="farm_id"><?= farm_options($sel ?: null, 'All farms') ?></select></div>
    <div class="col-auto"><button class="btn btn-vs"><i class="bi bi-search"></i> Show</button></div>
    <div class="col-auto ms-md-auto d-flex gap-2">
      <a class="btn btn-soft-blue" href="<?= url('download/docx.php?type=farms' . ($sel ? '&id=' . $sel : '') . ($userId ? '&user_id=' . $userId : '')) ?>"><i class="bi bi-file-earmark-word"></i> <?= $sel ? 'Download this farm' : 'Download all farms' ?> (.docx)</a>
    </div>
  </form>
</div>

<div class="panel">
  <?php if (!$farms): ?>
    <div class="empty"><div class="egg-ico"><i class="bi bi-house-heart"></i></div><b>No farms registered</b>
      <?php if (!is_admin()): ?><a href="<?= url('user/farm_register.php') ?>">Register your first farm</a><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-vs">
    <thead><tr><th>Farm</th><th>Farm ID</th><th>Locations</th><th>Farm contacts</th><th>Owner</th><th>Type</th><th class="num">Capacity</th><th>Registered by</th><th>Note</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($farms as $f): ?>
      <tr>
        <td><b><?= h($f['farm_name']) ?></b></td>
        <td><span class="pill"><?= h($f['farm_code']) ?></span></td>
        <td><?php foreach ($locs[$f['id']] ?? [] as $l): ?>
          <div><?= h($l['location_name']) ?>
          <?php if ($l['latitude'] !== null): ?><a class="ms-1" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= h($l['latitude']) ?>,<?= h($l['longitude']) ?>" title="Open in maps"><i class="bi bi-geo-alt-fill"></i></a><?php endif; ?></div>
        <?php endforeach; ?></td>
        <td><?= h(implode(', ', array_filter([$f['contact1'], $f['contact2'], $f['contact3']]))) ?></td>
        <td><?= h($f['owner_name']) ?><br><small class="muted"><?= h(implode(', ', array_filter([$f['owner_contact1'], $f['owner_contact2']]))) ?></small></td>
        <td><span class="pill blue"><?= h($f['farm_type']) ?></span></td>
        <td class="num"><?= $f['capacity'] !== null ? number_format($f['capacity']) : '—' ?></td>
        <td><?= h($f['vet']) ?></td>
        <td><?= h($f['note']) ?></td>
        <td class="text-nowrap">
          <?php if (!is_admin()): ?><a class="btn btn-sm btn-soft" href="<?= url('user/location_add.php?farm_id=' . $f['id']) ?>" title="Add another location"><i class="bi bi-geo-alt"></i> Location</a><?php endif; ?>
          <a class="btn btn-sm btn-soft-blue" href="<?= url('download/docx.php?type=farms&id=' . $f['id']) ?>" title="Download .docx"><i class="bi bi-download"></i></a>
          <?php if (is_admin()): ?><a class="btn btn-sm btn-soft" href="<?= url('admin/farm_edit.php?id=' . $f['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
          <button type="button" class="btn btn-sm btn-del-x" data-del="farm:<?= $f['id'] ?>" data-msg="Delete <?= h($f['farm_name']) ?> and ALL its locations, batches, visit records, images and prescriptions?" title="Delete"><i class="bi bi-trash"></i></button><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php';
