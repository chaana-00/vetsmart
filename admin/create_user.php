<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$pageTitle = 'Create user'; $pageSub = 'Add a veterinarian or another administrator'; $active = 'create_user';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('full_name'); $user = post('username'); $pw = $_POST['password'] ?? ''; $role = post('role', 'user');
    if ($name === '') $errors[] = 'Full name is required.';
    if ($user === '') $errors[] = 'Username is required.';
    if (!valid_phone(post('phone'))) $errors[] = 'Telephone number must be exactly 10 digits.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== ($_POST['confirm'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['user', 'admin'], true)) $role = 'user';
    if ($user !== '' && q_val("SELECT COUNT(*) FROM users WHERE username = ?", [$user])) $errors[] = 'That username is already taken.';
    if (!$errors) {
        q("INSERT INTO users (full_name, designation, company, phone, username, password_hash, role) VALUES (?,?,?,?,?,?,?)",
          [$name, nz(post('designation')), nz(post('company')), nz(post('phone')), $user, password_hash($pw, PASSWORD_DEFAULT), $role]);
        log_activity('user', "Created $role account for $name");
        flash('success', "$name can now sign in as $user.");
        redirect('admin/manage_users.php');
    }
}
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger soft-alert"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="panel" autocomplete="off">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" placeholder="Dr. Full Name" required value="<?= h(post('full_name')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" value="<?= h(post('designation')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" name="company" value="<?= h(post('company')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Telephone number</label><input class="form-control" name="phone" inputmode="numeric" maxlength="10" placeholder="10 digits" value="<?= h(post('phone')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" required autocomplete="off" value="<?= h(post('username')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role"><option value="user" <?= post('role') !== 'admin' ? 'selected' : '' ?>>User (veterinarian)</option><option value="admin" <?= post('role') === 'admin' ? 'selected' : '' ?>>Admin</option></select></div>
    <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"><div class="form-text">At least 8 characters.</div></div>
    <div class="col-md-6"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="confirm" required autocomplete="new-password"></div>
  </div>
  <button class="btn btn-vs mt-4 px-4" type="submit"><i class="bi bi-person-plus"></i> Save user</button>
</form>
<?php include __DIR__ . '/../includes/footer.php';
