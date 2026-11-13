<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['user']);
$pageTitle = 'Dashboard'; $pageSub = 'Your farms, flocks and visits at a glance'; $active = 'dashboard'; $useCharts = true;

$u = uid();
$farms    = (int)q_val("SELECT COUNT(*) FROM farms WHERE user_id = ?", [$u]);
$batches  = (int)q_val("SELECT COUNT(*) FROM batches WHERE user_id = ?", [$u]);
$birds    = (int)q_val("SELECT COALESCE(SUM(total_input),0) FROM batches WHERE user_id = ?", [$u]);
$broilers = (int)q_val("SELECT COUNT(*) FROM broiler_records WHERE user_id = ?", [$u]);
$layers   = (int)q_val("SELECT COUNT(*) FROM layer_records WHERE user_id = ?", [$u]);
$rx       = (int)q_val("SELECT COUNT(*) FROM prescriptions WHERE user_id = ?", [$u]);

$byFarm = q_all("SELECT f.farm_name, COALESCE(SUM(b.total_input),0) birds FROM farms f LEFT JOIN batches b ON b.farm_id = f.id WHERE f.user_id = ? GROUP BY f.id, f.farm_name ORDER BY f.farm_name ASC", [$u]);
$monthly = q_all("SELECT m, SUM(br) br, SUM(lr) lr FROM (
    SELECT DATE_FORMAT(created_at,'%Y-%m') m, 1 br, 0 lr FROM broiler_records WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    UNION ALL
    SELECT DATE_FORMAT(created_at,'%Y-%m'), 0, 1 FROM layer_records WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) t GROUP BY m ORDER BY m", [$u, $u]);
$summary = q_all("SELECT f.farm_name, l.location_name, b.batch_no, b.cage_no, b.breed, b.hatchery, b.total_input
    FROM batches b JOIN farms f ON f.id = b.farm_id JOIN farm_locations l ON l.id = b.location_id
    WHERE b.user_id = ? ORDER BY f.farm_name ASC, b.batch_no ASC LIMIT 10", [$u]);

include __DIR__ . '/../includes/header.php';
?>
<div class="quick">
  <a href="<?= url('user/farm_register.php') ?>"><i class="bi bi-plus-circle-fill"></i> Register farm</a>
  <a href="<?= url('user/batch_add.php') ?>"><i class="bi bi-egg-fill"></i> Add cage / batch</a>
  <a href="<?= url('user/add_records.php') ?>"><i class="bi bi-journal-plus"></i> New visit record</a>
  <a href="<?= url('user/prescription.php') ?>"><i class="bi bi-capsule"></i> Create prescription</a>
</div>

<div class="stat-grid">
  <div class="stat"><span class="stat-egg g"><i class="bi bi-house-heart-fill"></i></span><div class="stat-body"><div class="stat-num"><?= $farms ?></div><div class="stat-lab">Registered farms</div></div></div>
  <div class="stat"><span class="stat-egg b"><i class="bi bi-collection-fill"></i></span><div class="stat-body"><div class="stat-num"><?= $batches ?></div><div class="stat-lab">Batches</div></div></div>
  <div class="stat"><span class="stat-egg t"><i class="bi bi-egg-fill"></i></span><div class="stat-body"><div class="stat-num"><?= number_format($birds) ?></div><div class="stat-lab">Birds placed</div></div></div>
  <div class="stat"><span class="stat-egg g"><i class="bi bi-clipboard2-pulse-fill"></i></span><div class="stat-body"><div class="stat-num"><?= $broilers ?></div><div class="stat-lab">Broiler visits</div></div></div>
  <div class="stat"><span class="stat-egg b"><i class="bi bi-clipboard2-data-fill"></i></span><div class="stat-body"><div class="stat-num"><?= $layers ?></div><div class="stat-lab">Layer visits</div></div></div>
  <div class="stat"><span class="stat-egg t"><i class="bi bi-capsule"></i></span><div class="stat-body"><div class="stat-num"><?= $rx ?></div><div class="stat-lab">Prescriptions</div></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-7"><div class="panel h-100"><div class="panel-head"><h3>Birds placed by farm</h3></div>
    <?php if ($byFarm): ?><div style="height:280px"><canvas id="cFarm"></canvas></div>
    <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-bar-chart"></i></div><b>No farms yet</b>Register a farm to see its flock numbers here.</div><?php endif; ?></div></div>
  <div class="col-xl-5"><div class="panel h-100"><div class="panel-head"><h3>Share of birds by farm</h3></div>
    <?php if ($birds): ?><div style="height:280px"><canvas id="cShare"></canvas></div>
    <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-pie-chart"></i></div><b>No birds recorded</b>Add a batch with its total input.</div><?php endif; ?></div></div>
  <div class="col-12"><div class="panel"><div class="panel-head"><h3>Visit records, last 6 months</h3></div>
    <?php if ($monthly): ?><div style="height:240px"><canvas id="cMonth"></canvas></div>
    <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-graph-up"></i></div><b>No visits recorded</b>Save a broiler or layer record to start the trend.</div><?php endif; ?></div></div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Batch summary</h3><div class="ms-auto"><a class="btn btn-sm btn-soft" href="<?= url('user/batches.php') ?>">All batches</a></div></div>
  <?php if ($summary): ?>
  <div class="table-wrap"><table class="table table-vs"><thead><tr><th>Farm</th><th>Location</th><th>Batch</th><th>Cage</th><th>Breed</th><th>Hatchery</th><th class="num">Total input</th></tr></thead><tbody>
    <?php foreach ($summary as $r): ?><tr><td><?= h($r['farm_name']) ?></td><td><?= h($r['location_name']) ?></td><td><span class="pill blue"><?= h($r['batch_no']) ?></span></td><td><?= h($r['cage_no']) ?></td><td><?= h($r['breed']) ?></td><td><?= h($r['hatchery']) ?></td><td class="num"><?= number_format($r['total_input']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><div class="empty"><div class="egg-ico"><i class="bi bi-collection"></i></div><b>No batches yet</b>Add a cage and batch for one of your farms.</div><?php endif; ?>
</div>

<section class="auth-cta">
<br><p>© 2027 | VetSmart Designed & Developed by DYVESTA IT | +94 70 228 5959</p>
</section>

<?php
$extraJs = '<script>
const C={g:"#169c5b",b:"#1573c4",t:"#12948a",pal:["#169c5b","#1573c4","#12948a","#7be0ad","#5aa9e6","#0f7d47","#0d5a9e","#9bd6c1"]};
Chart.defaults.font.family="Figtree";Chart.defaults.color="#5d7880";
const bf=' . json_encode($byFarm) . ', mo=' . json_encode($monthly) . ';
if(document.getElementById("cFarm"))new Chart(cFarm,{type:"bar",data:{labels:bf.map(x=>x.farm_name),datasets:[{label:"Birds placed",data:bf.map(x=>+x.birds),backgroundColor:C.g,borderRadius:8,maxBarThickness:46}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:"#e3eeee"}},x:{grid:{display:false}}}}});
if(document.getElementById("cShare"))new Chart(cShare,{type:"doughnut",data:{labels:bf.map(x=>x.farm_name),datasets:[{data:bf.map(x=>+x.birds),backgroundColor:C.pal,borderWidth:3,borderColor:"#fff"}]},options:{maintainAspectRatio:false,cutout:"62%",plugins:{legend:{position:"bottom"}}}});
if(document.getElementById("cMonth"))new Chart(cMonth,{type:"line",data:{labels:mo.map(x=>x.m),datasets:[{label:"Broiler",data:mo.map(x=>+x.br),borderColor:C.g,backgroundColor:"rgba(22,156,91,.12)",fill:true,tension:.35},{label:"Layer",data:mo.map(x=>+x.lr),borderColor:C.b,backgroundColor:"rgba(21,115,196,.12)",fill:true,tension:.35}]},options:{maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:"#e3eeee"}},x:{grid:{display:false}}}}});
</script>';
include __DIR__ . '/../includes/footer.php';
