<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$pageTitle = 'Admin dashboard'; $pageSub = 'System-wide analytics'; $active = 'dashboard'; $useCharts = true;

$n = fn($t, $w = '1=1') => (int)q_val("SELECT COUNT(*) FROM $t WHERE $w");
$stats = [
  ['bi-people-fill', 'g', $n('users', "role='user'"), 'Veterinarians'],
  ['bi-house-heart-fill', 'b', $n('farms'), 'Farms'],
  ['bi-collection-fill', 't', $n('batches'), 'Batches'],
  ['bi-egg-fill', 'g', (int)q_val("SELECT COALESCE(SUM(total_input),0) FROM batches"), 'Birds placed'],
  ['bi-clipboard2-pulse-fill', 'b', $n('broiler_records'), 'Broiler visits'],
  ['bi-clipboard2-data-fill', 't', $n('layer_records'), 'Layer visits'],
  ['bi-capsule', 'g', $n('prescriptions'), 'Prescriptions'],
];
$users = q_all("SELECT u.id, u.full_name, u.designation, u.company, u.is_active, u.last_login,
   (SELECT COUNT(*) FROM farms WHERE user_id = u.id) farms,
   (SELECT COUNT(*) FROM broiler_records WHERE user_id = u.id) br,
   (SELECT COUNT(*) FROM layer_records WHERE user_id = u.id) lr,
   (SELECT COUNT(*) FROM prescriptions WHERE user_id = u.id) rx
   FROM users u WHERE u.role = 'user' ORDER BY u.full_name ASC");
$monthly = q_all("SELECT m, SUM(br) br, SUM(lr) lr, SUM(rx) rx FROM (
   SELECT DATE_FORMAT(created_at,'%Y-%m') m, 1 br, 0 lr, 0 rx FROM broiler_records WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
   UNION ALL SELECT DATE_FORMAT(created_at,'%Y-%m'), 0, 1, 0 FROM layer_records WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
   UNION ALL SELECT DATE_FORMAT(created_at,'%Y-%m'), 0, 0, 1 FROM prescriptions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) t GROUP BY m ORDER BY m");
$types = q_all("SELECT farm_type label, COUNT(*) c FROM farms GROUP BY farm_type");
$recent = q_all("SELECT * FROM activity_log ORDER BY id DESC LIMIT 8");
$icons = ['farm' => 'bi-house-heart-fill', 'batch' => 'bi-egg-fill', 'broiler' => 'bi-clipboard2-pulse-fill', 'layer' => 'bi-clipboard2-pulse-fill', 'prescription' => 'bi-capsule', 'user' => 'bi-person-plus-fill', 'login' => 'bi-box-arrow-in-right'];
include __DIR__ . '/../includes/header.php';
?>
<div class="quick">
  <a href="<?= url('admin/create_user.php') ?>"><i class="bi bi-person-plus-fill"></i> Create user</a>
  <a href="<?= url('admin/manage_users.php') ?>"><i class="bi bi-people-fill"></i> Manage users</a>
  <a href="<?= url('user/notifications.php') ?>"><i class="bi bi-bell-fill"></i> Activity & alerts</a>
</div>
<div class="stat-grid">
  <?php foreach ($stats as [$ic, $c, $num, $lab]): ?>
  <div class="stat"><span class="stat-egg <?= $c ?>"><i class="bi <?= $ic ?>"></i></span><div class="stat-body"><div class="stat-num" data-n="<?= $num ?>"><?= number_format($num) ?></div><div class="stat-lab"><?= h($lab) ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-xl-8"><div class="panel h-100"><div class="panel-head"><h3>Submissions, last 6 months</h3></div>
    <?php if ($monthly): ?><div style="height:290px"><canvas id="cMonth"></canvas></div><?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-graph-up"></i></div><b>No submissions yet</b></div><?php endif; ?></div></div>
  <div class="col-xl-4"><div class="panel h-100"><div class="panel-head"><h3>Farms by type</h3></div>
    <?php if ($types): ?><div style="height:290px"><canvas id="cType"></canvas></div><?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-pie-chart"></i></div><b>No farms yet</b></div><?php endif; ?></div></div>
  <div class="col-xl-7"><div class="panel h-100"><div class="panel-head"><h3>Veterinarians</h3><div class="ms-auto"><a class="btn btn-sm btn-soft" href="<?= url('admin/manage_users.php') ?>">Manage</a></div></div>
    <?php if (!$users): ?><div class="empty"><div class="egg-ico"><i class="bi bi-people"></i></div><b>No users yet</b><a href="<?= url('admin/create_user.php') ?>">Create the first user</a></div><?php else: ?>
    <div class="table-wrap"><table class="table table-vs"><thead><tr><th>#</th><th>Name</th><th>Company</th><th class="num">Farms</th><th class="num">Broiler</th><th class="num">Layer</th><th class="num">Rx</th><th>Last sign-in</th></tr></thead><tbody>
      <?php foreach ($users as $i => $u): ?><tr><td><?= $i + 1 ?></td><td><b><?= h($u['full_name']) ?></b><br><small class="muted"><?= h($u['designation']) ?></small></td><td><?= h($u['company']) ?></td>
        <td class="num"><?= $u['farms'] ?></td><td class="num"><?= $u['br'] ?></td><td class="num"><?= $u['lr'] ?></td><td class="num"><?= $u['rx'] ?></td>
        <td class="text-nowrap"><?= $u['is_active'] ? h($u['last_login'] ? time_ago($u['last_login']) : 'Never') : '<span class="pill off">Disabled</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
  <div class="col-xl-5"><div class="panel h-100 p-0 overflow-hidden"><div class="panel-head px-4 pt-4"><h3>Live activity</h3><div class="ms-auto"><a class="btn btn-sm btn-soft" href="<?= url('user/notifications.php') ?>">All</a></div></div>
    <ul class="feed" id="liveFeed">
      <?php foreach ($recent as $r): ?><li><span class="feed-ico <?= in_array($r['type'], ['batch', 'prescription', 'user'], true) ? 'b' : ($r['type'] === 'login' ? 'x' : '') ?>"><i class="bi <?= $icons[$r['type']] ?? 'bi-bell-fill' ?>"></i></span>
        <div class="feed-body"><?php if ($r['link']): ?><a href="<?= url($r['link']) ?>"><?= h($r['message']) ?></a><?php else: ?><span class="t"><?= h($r['message']) ?></span><?php endif; ?><small><?= h($r['actor_name']) ?> · <?= h(time_ago($r['created_at'])) ?></small></div></li><?php endforeach; ?>
    </ul></div></div>
</div>

<section class="auth-cta">
<br><p>© 2027 | VetSmart Designed & Developed by DYVESTA IT | +94 70 228 5959</p>
</section>

<?php
$extraJs = '<script>
Chart.defaults.font.family="Figtree";Chart.defaults.color="#5d7880";
const mo=' . json_encode($monthly) . ', ty=' . json_encode($types) . ';
if(document.getElementById("cMonth"))new Chart(cMonth,{type:"bar",data:{labels:mo.map(x=>x.m),datasets:[
 {label:"Broiler",data:mo.map(x=>+x.br),backgroundColor:"#169c5b",borderRadius:6},
 {label:"Layer",data:mo.map(x=>+x.lr),backgroundColor:"#1573c4",borderRadius:6},
 {label:"Prescriptions",data:mo.map(x=>+x.rx),backgroundColor:"#7be0ad",borderRadius:6}]},
 options:{maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:"#e3eeee"}},x:{grid:{display:false}}}}});
if(document.getElementById("cType"))new Chart(cType,{type:"doughnut",data:{labels:ty.map(x=>x.label),datasets:[{data:ty.map(x=>+x.c),backgroundColor:["#169c5b","#1573c4","#12948a","#9bd6c1"],borderWidth:3,borderColor:"#fff"}]},options:{maintainAspectRatio:false,cutout:"62%",plugins:{legend:{position:"bottom"}}}});
</script>';
include __DIR__ . '/../includes/footer.php';
