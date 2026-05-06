<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'User Management';
$db = getDB();

// Handle delete (soft)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== currentUser()['id']) {
        $db->prepare("UPDATE users SET is_active=0 WHERE user_id=?")->execute([$uid]);
    }
    header('Location: users.php?msg=deleted'); exit;
}

// Handle toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== currentUser()['id']) {
        // Get current active status
        $stmt = $db->prepare("SELECT is_active FROM users WHERE user_id=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        $isCurrentlyActive = (bool)$user['is_active'];
        
        // Toggle active status
        $db->prepare("UPDATE users SET is_active = !is_active WHERE user_id=?")->execute([$uid]);
        
        // If deactivating (toggling from 1 to 0), destroy user sessions
        if ($isCurrentlyActive) {
            // Store deactivated user ID in a revocation list session variable
            if (!isset($_SESSION['revoked_users'])) {
                $_SESSION['revoked_users'] = [];
            }
            $_SESSION['revoked_users'][$uid] = time();
        }
        $msg = $isCurrentlyActive ? 'deactivated' : 'activated';
    }
    header('Location: users.php?msg=' . ($msg ?? 'updated')); exit;
}

// Handle save (add/edit)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id       = (int)($_POST['user_id']??0);
    $username = trim($_POST['username']??'');
    $fullname = trim($_POST['full_name']??'');
    $role     = in_array($_POST['role']??'',['admin','cashier']) ? $_POST['role'] : 'cashier';
    $password = trim($_POST['password']??'');

    if ($id) {
        if ($password) {
            $db->prepare("UPDATE users SET username=?,full_name=?,role=?,password=? WHERE user_id=?")
               ->execute([$username,$fullname,$role,password_hash($password,PASSWORD_DEFAULT),$id]);
        } else {
            $db->prepare("UPDATE users SET username=?,full_name=?,role=? WHERE user_id=?")
               ->execute([$username,$fullname,$role,$id]);
        }
    } else {
        $hash = password_hash($password ?: 'changeme', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users(username,password,full_name,role) VALUES(?,?,?,?)")
           ->execute([$username,$hash,$fullname,$role]);
    }
    header('Location: users.php?msg=saved'); exit;
}

$users = $db->query(
    "SELECT u.*, COUNT(t.transaction_id) AS txn_count
     FROM users u
     LEFT JOIN transactions t ON t.cashier_id=u.user_id AND t.status='completed'
     GROUP BY u.user_id ORDER BY u.created_at DESC"
)->fetchAll();

require_once 'includes/header.php';
?>

<?php if(isset($_GET['msg'])): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-check-circle-fill"></i>
  <?php 
    $messages = [
        'saved' => 'User saved.',
        'deleted' => 'User deactivated.',
        'deactivated' => '✓ User has been deactivated and will be automatically logged out.',
        'activated' => '✓ User account has been reactivated.'
    ];
    echo htmlspecialchars($messages[$_GET['msg']] ?? 'Operation completed.')
  ?>
</div>
<?php endif; ?>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="user_id" id="modal_uid"/>
        <div class="modal-header">
          <h5 class="modal-title" id="userModalTitle">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" id="modal_fullname" class="form-control" required/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="modal_uname" class="form-control" required/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role" id="modal_role" class="form-select">
              <option value="cashier">Cashier</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
            <input type="password" name="password" id="modal_pass" class="form-control"
                   placeholder="Min 6 characters"/>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-brand px-4">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h6 class="mb-0">All Users <span class="badge badge-teal"><?=count($users)?></span></h6>
  <button class="btn btn-brand" onclick="openAdd()">
    <i class="bi bi-person-plus-fill me-1"></i>Add User
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Name</th><th>Username</th><th>Role</th><th>Transactions</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): $isSelf = $u['user_id']==currentUser()['id']; ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="user-avatar" style="width:32px;height:32px;font-size:12px">
                <?=strtoupper(substr($u['full_name'],0,1))?>
              </div>
              <span class="fw-semibold"><?=htmlspecialchars($u['full_name'])?></span>
              <?php if($isSelf): ?><span class="badge badge-green">You</span><?php endif; ?>
            </div>
          </td>
          <td><code><?=htmlspecialchars($u['username'])?></code></td>
          <td>
            <span class="badge <?=$u['role']==='admin'?'badge-amber':'badge-teal'?>">
              <?=ucfirst($u['role'])?>
            </span>
          </td>
          <td><?=number_format($u['txn_count'])?></td>
          <td>
            <?php if($u['is_active']): ?>
            <span class="badge badge-green">Active</span>
            <?php else: ?>
            <span class="badge badge-red">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-secondary me-1"
                    onclick='editUser(<?=json_encode($u)?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <?php if(!$isSelf): ?>
            <a href="users.php?toggle=<?=$u['user_id']?>"
               class="btn btn-sm btn-outline-<?=$u['is_active']?'warning':'success'?> me-1"
               title="<?=$u['is_active']?'Deactivate':'Activate'?>">
              <i class="bi bi-<?=$u['is_active']?'pause':'play'?>-circle"></i>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraJS = '<script>
function openAdd(){
  document.getElementById("userModalTitle").textContent="Add User";
  document.getElementById("modal_uid").value="";
  document.getElementById("modal_fullname").value="";
  document.getElementById("modal_uname").value="";
  document.getElementById("modal_role").value="cashier";
  document.getElementById("modal_pass").value="";
  document.getElementById("modal_pass").placeholder="Min 6 characters";
  new bootstrap.Modal(document.getElementById("userModal")).show();
}
function editUser(u){
  document.getElementById("userModalTitle").textContent="Edit User";
  document.getElementById("modal_uid").value=u.user_id;
  document.getElementById("modal_fullname").value=u.full_name;
  document.getElementById("modal_uname").value=u.username;
  document.getElementById("modal_role").value=u.role;
  document.getElementById("modal_pass").value="";
  document.getElementById("modal_pass").placeholder="Leave blank to keep password";
  new bootstrap.Modal(document.getElementById("userModal")).show();
}
</script>';
require_once 'includes/footer.php';
?>
