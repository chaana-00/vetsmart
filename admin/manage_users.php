<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$pageTitle = 'Manage users'; $pageSub = 'Edit details, reset passwords, disable or remove accounts'; $active = 'manage_users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id'); $act = post('action');
    $row = q_one("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$row) { flash('error', 'User not found.'); redirect('admin/manage_users.php'); }
    if ($act === 'save') {
        $pw = $_POST['password'] ?? ''; $role = in_array(post('role'), ['admin', 'user'], true) ? post('role') : 'user';
        $dup = q_val("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?", [post('username'), $id]);
        if (post('full_name') === '' || post('username') === '') flash('error', 'Name and username are required.');
        elseif ($dup) flash('error', 'That username is already taken.');
        elseif (!valid_phone(post('phone'))) flash('error', 'Telephone number must be exactly 10 digits.');
        elseif ($pw !== '' && strlen($pw) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif ($id === uid() && ($role !== 'admin' || !isset($_POST['is_active']))) flash('error', 'You cannot demote or disable your own account.');
        else {
            q("UPDATE users SET full_name=?, designation=?, company=?, phone=?, username=?, role=?, is_active=? WHERE id=?",
              [post('full_name'), nz(post('designation')), nz(post('company')), nz(post('phone')), post('username'), $role, isset($_POST['is_active']) ? 1 : 0, $id]);
            if ($pw !== '') q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($pw, PASSWORD_DEFAULT), $id]);
            if ($id === uid()) $_SESSION['name'] = post('full_name');
            log_activity('user', 'Updated account for ' . post('full_name'));
            flash('success', 'User updated.');
        }
    } elseif ($act === 'delete') {
        if ($id === uid()) flash('error', 'You cannot delete your own account.');
        else { q("DELETE FROM users WHERE id = ?", [$id]); log_activity('user', 'Deleted account for ' . $row['full_name']); flash('success', $row['full_name'] . ' and all of their records were removed.'); }
    }
    redirect('admin/manage_users.php');
}
$users = q_all("SELECT u.*, (SELECT COUNT(*) FROM farms WHERE user_id = u.id) farms FROM users u ORDER BY u.role ASC, u.full_name ASC");
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <div class="panel-head"><h3>All accounts <span class="pill gray ms-1"><?= count($users) ?></span></h3><div class="ms-auto"><a class="btn btn-sm btn-vs" href="<?= url('admin/create_user.php') ?>"><i class="bi bi-person-plus"></i> Create user</a></div></div>
  <div class="table-wrap"><table class="table table-vs"><thead><tr><th>#</th><th>Name</th><th>Designation</th><th>Company</th><th>Phone</th><th>Username</th><th>Role</th><th>Status</th><th class="num">Farms</th><th></th></tr></thead><tbody>
  <?php foreach ($users as $i => $u): ?><tr>
    <td><?= $i + 1 ?></td><td><b><?= h($u['full_name']) ?></b></td><td><?= h($u['designation']) ?></td><td><?= h($u['company']) ?></td><td><?= h($u['phone']) ?></td><td><?= h($u['username']) ?></td>
    <td><span class="pill <?= $u['role'] === 'admin' ? 'blue' : '' ?>"><?= h($u['role']) ?></span></td>
    <td><?= $u['is_active'] ? '<span class="pill">Active</span>' : '<span class="pill off">Disabled</span>' ?></td><td class="num"><?= $u['farms'] ?></td>
    <td class="text-nowrap">
      <button class="btn btn-sm btn-soft-blue" data-bs-toggle="modal" data-bs-target="#editModal" data-user='<?= h(json_encode(['id' => $u['id'], 'full_name' => $u['full_name'], 'designation' => $u['designation'], 'company' => $u['company'], 'phone' => $u['phone'], 'username' => $u['username'], 'role' => $u['role'], 'is_active' => (int)$u['is_active']])) ?>'><i class="bi bi-pencil"></i> Edit</button>
      <?php if ((int)$u['id'] !== uid()): ?>
      <form method="post" class="d-inline" data-confirm="Delete <?= h($u['full_name']) ?> and ALL of their farms, batches and records? This cannot be undone.">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <button class="btn btn-sm btn-del" style="width:auto;border-radius:10px;padding:0 12px" title="Delete"><i class="bi bi-trash"></i></button></form>
      <?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="post" class="modal-content" style="border-radius:22px;border:0" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="e_id">
  <div class="modal-header border-0"><h5 class="modal-title">Edit user</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" id="e_full_name" required></div>
    <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" id="e_designation"></div>
    <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" name="company" id="e_company"></div>
    <div class="col-md-6"><label class="form-label">Telephone</label><input class="form-control" name="phone" id="e_phone" maxlength="10" inputmode="numeric"></div>
    <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" id="e_username" required></div>
    <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role" id="e_role"><option value="user">User</option><option value="admin">Admin</option></select></div>
    <div class="col-md-6"><label class="form-label">New password</label><input class="form-control" type="password" name="password" minlength="8" placeholder="Leave blank to keep current" autocomplete="new-password"></div>
    <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch fs-6"><input class="form-check-input" type="checkbox" name="is_active" id="e_active" value="1"><label class="form-check-label" for="e_active">Account active</label></div></div>
  </div></div>
  <div class="modal-footer border-0"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-vs" type="submit">Save changes</button></div>
</form></div></div>
<?php
$extraJs = '<script>
document.getElementById("editModal").addEventListener("show.bs.modal",e=>{
  const u=JSON.parse(e.relatedTarget.dataset.user);
  for(const k of ["id","full_name","designation","company","phone","username","role"])document.getElementById("e_"+k).value=u[k]??"";
  document.getElementById("e_active").checked=!!u.is_active;
});
</script>';
include __DIR__ . '/../includes/footer.php';
