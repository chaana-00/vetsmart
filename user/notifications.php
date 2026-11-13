<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user']);
$pageTitle = is_admin() ? 'Activity & alerts' : 'History & alerts';
$pageSub = is_admin() ? 'Every submission by every user, live' : 'Everything you have submitted, live';
$active = 'notifications';

$seen = (int)q_val("SELECT notif_seen_id FROM users WHERE id = ?", [uid()]);
$type = get('type');
$p = []; $w = activity_scope($p);
if ($type !== '') { $w .= ' AND a.type = ?'; $p[] = $type; }
$rows = q_all("SELECT a.* FROM activity_log a WHERE 1=1 $w ORDER BY a.id DESC LIMIT 200", $p);
// viewing the page marks everything read
q("UPDATE users SET notif_seen_id = (SELECT COALESCE(MAX(id),0) FROM activity_log) WHERE id = ?", [uid()]);
$types = ['' => 'All', 'farm' => 'Farms', 'batch' => 'Batches', 'broiler' => 'Broiler', 'layer' => 'Layer', 'prescription' => 'Prescriptions', 'user' => 'Users', 'login' => 'Sign-ins'];
$blue = ['batch', 'prescription', 'user'];
$icons = ['farm' => 'bi-house-heart-fill', 'batch' => 'bi-egg-fill', 'broiler' => 'bi-clipboard2-pulse-fill', 'layer' => 'bi-clipboard2-pulse-fill', 'prescription' => 'bi-capsule', 'user' => 'bi-person-plus-fill', 'login' => 'bi-box-arrow-in-right'];
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach ($types as $k => $label): ?><a class="btn btn-sm <?= $type === (string)$k ? 'btn-vs' : 'btn-soft' ?>" href="?type=<?= h($k) ?>"><?= h($label) ?></a><?php endforeach; ?>
</div>
<div class="panel p-0 overflow-hidden">
  <?php if (!$rows): ?><div class="empty"><div class="egg-ico"><i class="bi bi-bell"></i></div><b>No activity yet</b>New submissions will show up here the moment they are saved.</div><?php endif; ?>
  <ul class="feed" id="liveFeed">
    <?php foreach ($rows as $n): ?>
    <li class="<?= $n['id'] > $seen ? 'is-new' : '' ?>">
      <span class="feed-ico <?= in_array($n['type'], $blue, true) ? 'b' : ($n['type'] === 'login' ? 'x' : '') ?>"><i class="bi <?= $icons[$n['type']] ?? 'bi-bell-fill' ?>"></i></span>
      <div class="feed-body"><?php if ($n['link']): ?><a href="<?= url($n['link']) ?>"><?= h($n['message']) ?></a><?php else: ?><span class="t"><?= h($n['message']) ?></span><?php endif; ?>
        <small><?= h($n['actor_name']) ?> · <?= h(fmt_dt($n['created_at'])) ?></small></div>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php include __DIR__ . '/../includes/footer.php';
