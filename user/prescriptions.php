<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$pageTitle = 'Prescriptions'; $pageSub = 'Saved prescriptions, ready to export'; $active = 'prescriptions';

$farmId = (int)get('farm_id'); $focus = (int)get('id');
$p = []; $w = '';
$userId = is_admin() ? (int)get('user_id') : 0;
if ($userId) { $w .= ' AND x.user_id = ?'; $p[] = $userId; }
if ($farmId) { $w .= ' AND x.farm_id = ?'; $p[] = $farmId; }
$rows = q_all("SELECT x.*, f.farm_name, b.batch_no, u.full_name vet FROM prescriptions x JOIN farms f ON f.id = x.farm_id JOIN batches b ON b.id = x.batch_id JOIN users u ON u.id = x.user_id WHERE 1=1 $w ORDER BY x.id DESC", $p);
$rxItems = [];
if ($rows) foreach (q_all("SELECT * FROM prescription_items WHERE prescription_id IN (" . implode(',', array_map('intval', array_column($rows, 'id'))) . ") ORDER BY id") as $i) $rxItems[$i['prescription_id']][] = $i;

include __DIR__ . '/../includes/header.php';
if (is_admin()) include __DIR__ . '/../includes/delete_form.php';
?>
<div class="panel">
  <form class="row g-3 align-items-end" method="get">
    <?php if (is_admin()): ?><div class="col-md-4"><label class="form-label">Prescribed by (veterinarian)</label><select class="form-select" name="user_id"><?= vet_options($userId ?: null) ?></select></div><?php endif; ?>
    <div class="col-md-<?= is_admin() ? 4 : 6 ?>"><label class="form-label">Farm</label><select class="form-select" name="farm_id"><?= farm_options($farmId ?: null, 'All farms') ?></select></div>
    <div class="col-auto"><button class="btn btn-vs"><i class="bi bi-search"></i> Show</button></div>
    <?php if (!is_admin()): ?><div class="col-auto ms-md-auto"><a class="btn btn-soft" href="<?= url('user/prescription.php') ?>"><i class="bi bi-plus-lg"></i> New prescription</a></div><?php endif; ?>
  </form>
</div>
<?php if (!$rows): ?>
  <div class="panel"><div class="empty"><div class="egg-ico"><i class="bi bi-capsule"></i></div><b>No prescriptions yet</b>Saved prescriptions appear here.</div></div>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="panel" <?= $focus === (int)$r['id'] ? 'style="outline:3px solid var(--sky-2)"' : '' ?>>
  <div class="panel-head"><h3><span class="pill blue"><?= h($r['presc_no']) ?></span> <?= h($r['farm_name']) ?></h3>
    <div class="ms-auto"><a class="btn btn-sm btn-vs" href="<?= url('download/docx.php?type=prescription&id=' . $r['id']) ?>"><i class="bi bi-file-earmark-word"></i> Download .docx</a>
      <?php if (is_admin()): ?><a class="btn btn-sm btn-soft-blue" href="<?= url('admin/prescription_edit.php?id=' . $r['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
      <button type="button" class="btn btn-sm btn-del-x" data-del="prescription:<?= $r['id'] ?>" data-msg="Delete prescription <?= h($r['presc_no']) ?>?"><i class="bi bi-trash"></i> Delete</button><?php endif; ?></div></div>
  <div class="kv mb-3">
    <div><small>Batch</small><b><?= h($r['batch_no']) ?></b></div><div><small>Number of birds</small><b><?= $r['num_birds'] !== null ? number_format($r['num_birds']) : '—' ?></b></div>
    <div><small>Date &amp; time</small><b><?= h(fmt_dt($r['presc_datetime'])) ?></b></div><div><small>Prescribed by</small><b><?= h($r['vet']) ?></b></div>
    <?php if ($r['remarks']): ?><div class="wide"><small>Remarks</small><b><?= h($r['remarks']) ?></b></div><?php endif; ?>
  </div>
  <div class="table-wrap"><table class="table table-vs blue"><thead><tr><th>Drugs</th><th>Indication</th><th>Duration</th><th>Total volume / drugs / weight</th></tr></thead><tbody>
    <?php foreach ($rxItems[$r['id']] ?? [] as $i): ?><tr><td><?= h($i['drug']) ?></td><td><?= h($i['indication']) ?></td><td><?= h($i['duration']) ?></td><td><?= h($i['total_volume']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php';
