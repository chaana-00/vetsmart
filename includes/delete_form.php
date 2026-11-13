<?php /* Hidden form used by every admin "Delete" button (data-del="type:id"). */ ?>
<form id="delForm" method="post" action="<?= url('admin/delete.php') ?>" hidden>
  <?= csrf_field() ?><input type="hidden" name="type"><input type="hidden" name="id"><input type="hidden" name="return" value="<?= h($_SERVER['REQUEST_URI'] ?? '') ?>">
</form>
