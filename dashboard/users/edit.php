<?php
// dashboard/users/edit.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../assets/db/db.php';

$UsersBase = 'dashboard/users';

// -------------------------------
// ACCESS CONTROL
// -------------------------------
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

// Validate ID
$user_id = intval($_GET['id'] ?? 0);
if ($user_id <= 0) {
    header("Location: $UsersBase/index.php");
    exit;
}

$success = $error = "";

// -------------------------------
// FETCH USER DATA
// -------------------------------
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: $UsersBase/index.php?error=notfound");
    exit;
}

// -------------------------------
// HANDLE UPDATE
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? 'community_user';
    $phone      = trim($_POST['phone_number'] ?? '');
    $age        = intval($_POST['age'] ?? 0);
    $gender     = $_POST['gender'] ?? '';
    $address    = trim($_POST['address'] ?? '');

    if ($first_name === '' || $last_name === '' || $username === '' || $email === '') {
        $error = "Please fill in all required fields.";
    } else {

        // Check duplicate username/email except the current user
        $stmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE (username = ? OR email = ?) AND user_id != ?
        ");
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        $dup = $stmt->get_result();
        $stmt->close();

        if ($dup->num_rows > 0) {
            $error = "Username or Email already taken by another user.";
        } else {

            // Update depending on password presence
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username=?, first_name=?, last_name=?, age=?, gender=?, address=?, 
                        email=?, password=?, phone_number=?, role=?
                    WHERE user_id=?
                ");
                $stmt->bind_param(
                    "sssissssssi",
                    $username, $first_name, $last_name,
                    $age, $gender, $address,
                    $email, $hashed, $phone, $role, $user_id
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username=?, first_name=?, last_name=?, age=?, gender=?, address=?, 
                        email=?, phone_number=?, role=?
                    WHERE user_id=?
                ");
                $stmt->bind_param(
                    "sssisssssi",
                    $username, $first_name, $last_name,
                    $age, $gender, $address,
                    $email, $phone, $role, $user_id
                );
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: index.php?updated=1");
                exit;
            } else {
                $error = "Failed to update user.";
                $stmt->close();
            }
        }
    }
}

// ------------------------------------------------------
// ONLY AFTER ALL REDIRECT LOGIC — INCLUDE HEAD TEMPLATE
// ------------------------------------------------------
include '../../layouts/head.php';
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit User</h5>
        <a href="<?= $UsersBase ?>/index.php" class="btn btn-outline-secondary">Back</a>
      </div>

      <div class="card-body p-5">

        <?php if ($error): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded">
            <?= $error ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <div class="grid grid-cols-12 gap-4">

            <div class="col-span-6">
              <label class="form-label">First Name</label>
              <input name="first_name" class="form-control" required
                value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Last Name</label>
              <input name="last_name" class="form-control" required
                value="<?= htmlspecialchars($_POST['last_name'] ?? $user['last_name']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required
                value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required
                value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>">
            </div>

            <div class="col-span-6">
              <label class="form-label">New Password <small>(leave blank to keep current)</small></label>
              <input type="password" name="password" class="form-control">
            </div>

            <div class="col-span-6">
              <label class="form-label">Role</label>
              <select name="role" class="form-control">
                <option value="admin" <?= (($user['role']) === 'admin') ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= (($user['role']) === 'staff') ? 'selected' : '' ?>>Staff</option>
                <option value="community_user" <?= (($user['role']) === 'community_user') ? 'selected' : '' ?>>Community User</option>
              </select>
            </div>

            <div class="col-span-6">
              <label class="form-label">Phone Number</label>
              <input name="phone_number" class="form-control"
                value="<?= htmlspecialchars($_POST['phone_number'] ?? $user['phone_number']) ?>">
            </div>

            <div class="col-span-3">
              <label class="form-label">Age</label>
              <input type="number" min="0" name="age" class="form-control"
                value="<?= htmlspecialchars($_POST['age'] ?? $user['age']) ?>">
            </div>

            <div class="col-span-3">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select</option>
                <option value="Male"   <?= (($user['gender']) === 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (($user['gender']) === 'Female') ? 'selected' : '' ?>>Female</option>
              </select>
            </div>

            <div class="col-span-12">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= 
                htmlspecialchars($_POST['address'] ?? $user['address']) 
              ?></textarea>
            </div>

          </div>

          <div class="pt-4">
            <button class="btn btn-primary mt-5">Save Changes</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
