<?php
// ======================================================================
// edit.php - Community User Profile Editor
// Location: /dashboard/community/profile/edit.php
// ======================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------------------------
// ACCESS CONTROL
// ----------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../pages/login.php');
    exit;
}

include '../../../assets/db/db.php';

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// community_user OR admin/staff who want to edit their own profile
if (!in_array($user_role, ['community_user', 'admin', 'staff'])) {
    header('Location: ../../../pages/login.php');
    exit;
}

$ProfileBase = '/dashboard/community/profile';

// ----------------------------------------------------------------------
// FETCH CURRENT USER DATA
// ----------------------------------------------------------------------
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("User not found.");

$error_message = '';
$success_message = '';


// ----------------------------------------------------------------------
// HANDLE POST UPDATE
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $first_name = trim($_POST['first_name']);
        $last_name  = trim($_POST['last_name']);
        $username   = trim($_POST['username']);
        $email      = trim($_POST['email']);
        $phone      = trim($_POST['phone_number']);
        $age        = intval($_POST['age']);
        $gender     = trim($_POST['gender']);
        $address    = trim($_POST['address']);
        $password   = trim($_POST['password'] ?? '');

        if ($first_name === '' || $last_name === '' || $username === '' || $email === '') {
            throw new Exception("Please fill all required fields.");
        }

        // Check duplicate username & email (except own)
        $stmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE (username = ? OR email = ?) AND user_id != ?
        ");
        $stmt->bind_param('ssi', $username, $email, $user_id);
        $stmt->execute();
        $dup = $stmt->get_result();
        $stmt->close();

        if ($dup->num_rows > 0) {
            throw new Exception("Username or email already taken.");
        }

        // UPDATE user
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
        
            $stmt = $conn->prepare("
                UPDATE users SET 
                    first_name=?, last_name=?, username=?, email=?, 
                    phone_number=?, age=?, gender=?, address=?, password=? 
                WHERE user_id=?
            ");
            $stmt->bind_param(
                'sssssisssi',
                $first_name, $last_name, $username, $email,
                $phone, $age, $gender, $address, $hashed, $user_id
            );
        
        } else {
            $stmt = $conn->prepare("
                UPDATE users SET 
                    first_name=?, last_name=?, username=?, email=?, 
                    phone_number=?, age=?, gender=?, address=? 
                WHERE user_id=?
            ");
            $stmt->bind_param(
                'ssssisssi',
                $first_name, $last_name, $username, $email,
                $phone, $age, $gender, $address, $user_id
            );
        }

        $stmt->execute();
        $stmt->close();

        // redirect with success
        header("Location: $ProfileBase/index.php?updated=1");
        exit;

    } catch (Exception $e) {
        header("Location: $ProfileBase/index.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

function h($v) { return htmlspecialchars($v ?? ''); }

include '../../../layouts/head.php';
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
            <h5>Edit My Profile</h5>
            <a href="/dashboard/index.php" class="btn btn-outline-secondary">← Back</a>
        </div>

      <div class="card-body p-5">

        <!-- SUCCESS & ERROR BADGES -->
        <?php if (isset($_GET['updated'])): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded">
            Profile updated successfully.
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded">
            <?= h($_GET['error']) ?>
          </div>
        <?php endif; ?>

        <form method="POST">

          <div class="grid grid-cols-12 gap-4">

            <div class="col-span-6">
              <label class="form-label">First Name</label>
              <input name="first_name" class="form-control" required
                value="<?= h($user['first_name']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Last Name</label>
              <input name="last_name" class="form-control" required
                value="<?= h($user['last_name']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required
                value="<?= h($user['username']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required
                value="<?= h($user['email']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">New Password (optional)</label>
              <input type="password" name="password" class="form-control"
                placeholder="Leave empty to keep current">
            </div>

            <div class="col-span-6">
              <label class="form-label">Phone Number</label>
              <input name="phone_number" class="form-control"
                value="<?= h($user['phone_number']) ?>">
            </div>

            <div class="col-span-3">
              <label class="form-label">Age</label>
              <input type="number" name="age" class="form-control"
                value="<?= h($user['age']) ?>">
            </div>

            <div class="col-span-3">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select</option>
                <option value="Male"   <?= $user['gender']=='Male'?'selected':'' ?>>Male</option>
                <option value="Female" <?= $user['gender']=='Female'?'selected':'' ?>>Female</option>
              </select>
            </div>

            <div class="col-span-12">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= h($user['address']) ?></textarea>
            </div>

          </div>

          <div class="pt-4">
            <button class="btn btn-success mt-3">Save Changes</button>
          </div>

        </form>

      </div>
    </div>

  </div>
</div>

<?php include '../../../layouts/footer-block.php'; ?>
