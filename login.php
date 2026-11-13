<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect(is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $role = post('role'); $username = post('username'); $password = $_POST['password'] ?? '';
    if (!in_array($role, ['admin', 'user'], true) || $username === '' || $password === '') {
        $error = 'Choose a role and enter your username and password.';
    } else {
        $row = q_one("SELECT * FROM users WHERE username = ? AND role = ?", [$username, $role]);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            $error = 'Those details do not match an ' . $role . ' account.';
        } elseif (!(int)$row['is_active']) {
            $error = 'This account is disabled. Ask an administrator to re-enable it.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['full_name'];
            $_SESSION['designation'] = $row['designation'];
            q("UPDATE users SET last_login = NOW() WHERE id = ?", [$row['id']]);
            log_activity('login', $row['full_name'] . ' signed in');
            redirect($row['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth">
  <section class="auth-hero">
    <div class="egg-stack" aria-hidden="true">
      <span class="egg egg-a"></span><span class="egg egg-b"></span><span class="egg egg-c"><i class="bi bi-heart-pulse-fill"></i></span>
    </div>
    <div class="auth-copy">
      <img src="<?= url('assets/img/FCLGIF.gif') ?>" alt="VetSmart logo" class="auth-logo" width="120" height="180"><br><br>
      <h1>Every flock,<br>every visit, on record.</h1>
      <p>VetSmart digital veterinary farm management system designed to simplify farm record-keeping, monitor animal health, manage treatments and vaccinations, record veterinary visits, and generate farm reports efficiently.</p>
    </div>
    <ul class="auth-points">
      <li><i class="bi bi-geo-alt-fill"></i> Monitoring with location tracking</li>
      <li><i class="bi bi-file-earmark-word-fill"></i> Accurate record-keeping</li>
      <li><i class="bi bi-bell-fill"></i> Live alerts for every submission</li>
    </ul>
    <section class="auth-cta">
      <p>----------------------------------------------------------------------------------</p>
      <p>© 2027 | VetSmart Designed & Developed by DYVESTA IT | +94 70 228 5959</p>
      <a href="https://www.farmchemie.com/" target="_blank" rel="noopener" class="btn btn-outline-light"><i class="bi bi-box-arrow-up-right"></i> Visit Farmchemie</a>
    </section>
  </section>
  <section class="auth-form-wrap">
    <form method="post" class="auth-card" autocomplete="on">
      <?= csrf_field() ?>
      <div class="brand brand-dark">
        <span class="brand-egg"><i class="bi bi-heart-pulse-fill"></i></span>
        <span class="brand-text"><b>VetSmart</b><small>by Farmchemie</small></span>
      </div>
      <h2>Welcome back</h2>
      <p class="muted">Sign in as an administrator or as a veterinarian.</p>
      <?php if ($error): ?><div class="alert alert-danger soft-alert py-2"><?= h($error) ?></div><?php endif; ?>

      <div class="role-switch" role="radiogroup" aria-label="Role">
        <input type="radio" name="role" id="r_user" value="user" <?= post('role', 'user') === 'user' ? 'checked' : '' ?>>
        <label for="r_user"><i class="bi bi-person-badge"></i> User</label>
        <input type="radio" name="role" id="r_admin" value="admin" <?= post('role') === 'admin' ? 'checked' : '' ?>>
        <label for="r_admin"><i class="bi bi-shield-check"></i> Admin</label>
      </div>

      <label class="form-label" for="username">Username</label>
      <div class="input-icon"><i class="bi bi-envelope"></i><input class="form-control" id="username" name="username" value="<?= h(post('username')) ?>" required autofocus></div>

      <label class="form-label" for="password">Password</label>
      <div class="input-icon"><i class="bi bi-lock"></i><input class="form-control" id="password" type="password" name="password" required>
        <button type="button" class="eye" onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password'" aria-label="Show password"><i class="bi bi-eye"></i></button></div>

      <button class="btn btn-vs w-100 mt-4" type="submit">Sign in</button>
    </form>
  </section>
</div>
</body>
</html>
