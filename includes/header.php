<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/nav.php';
$pageTitle = $pageTitle ?? 'Dashboard';
$active    = $active ?? '';
$u         = me();
$unread    = unread_count();
$initials  = strtoupper(mb_substr(preg_replace('/^(Dr\.?|Mr\.?|Ms\.?|Mrs\.?)\s+/i', '', $u['name']), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
<script>
window.VS = {
  base: <?= json_encode(BASE_URL) ?>,
  mapsKey: <?= json_encode(GOOGLE_MAPS_API_KEY) ?>,
  mapCenter: [<?= MAP_DEFAULT_LAT ?>, <?= MAP_DEFAULT_LNG ?>],
  pollMs: <?= (int)NOTIF_POLL_MS ?>,
  role: <?= json_encode($u['role']) ?>,
  csrf: <?= json_encode(csrf_token()) ?>
};
</script>
</head>
<body>
<div class="shell">
  <aside class="rail" id="rail">
    <a class="brand" href="<?= url(is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php') ?>">
      <span class="brand-egg"><i class="bi bi-heart-pulse-fill"></i></span>
      <span class="brand-text"><b>VetSmart</b><small>by Farmchemie</small></span>
    </a>
    <nav class="rail-nav">
      <?php foreach (nav_items() as [$navGroup, $navList]): ?>
        <div class="rail-group"><?= h($navGroup) ?></div>
        <?php foreach ($navList as [$navKey, $navIcon, $navLabel, $navHref]): ?>
          <a href="<?= url($navHref) ?>" class="rail-link <?= $active === $navKey ? 'is-active' : '' ?>">
            <i class="bi <?= $navIcon ?>"></i><span><?= h($navLabel) ?></span>
            <?php if ($navKey === 'notifications'): ?><em class="badge-dot js-unread" <?= $unread ? '' : 'hidden' ?>><?= $unread ?></em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <a class="rail-out" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-left"></i><span>Sign out</span></a>
  </aside>

  <div class="stage">
    <header class="topbar">
      <button class="btn-icon d-lg-none" id="railToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="topbar-title">
        <h1><?= h($pageTitle) ?></h1>
        <?php if (!empty($pageSub)): ?><p><?= h($pageSub) ?></p><?php endif; ?>
      </div>
      <div class="topbar-tools">
        <div class="dropdown">
          <button class="btn-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Notifications" id="bellBtn">
            <i class="bi bi-bell"></i>
            <em class="badge-dot js-unread" <?= $unread ? '' : 'hidden' ?>><?= $unread ?></em>
          </button>
          <div class="dropdown-menu dropdown-menu-end bell-menu">
            <div class="bell-head">
              <b><?= is_admin() ? 'Latest activity' : 'Your latest activity' ?></b>
              <button type="button" class="link-btn" id="markRead">Mark all read</button>
            </div>
            <div id="bellList" class="bell-list"><div class="bell-empty">Loading…</div></div>
            <a class="bell-foot" href="<?= url('user/notifications.php') ?>">Open full history</a>
          </div>
        </div>
        <div class="me">
          <span class="me-avatar"><?= h($initials) ?></span>
          <span class="me-text"><b><?= h($u['name']) ?></b><small><?= is_admin() ? 'Administrator' : h($u['designation'] ?: 'Veterinarian') ?></small></span>
        </div>
      </div>
    </header>
    <main class="content">
      <?php foreach (flashes() as $f): ?>
        <div class="alert alert-<?= $f['t'] === 'error' ? 'danger' : h($f['t']) ?> alert-dismissible fade show soft-alert" role="alert">
          <?= h($f['m']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
