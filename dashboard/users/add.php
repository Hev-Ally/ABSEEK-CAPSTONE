<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../assets/db/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../assets/libs/phpmailer/src/Exception.php';
require __DIR__ . '/../../assets/libs/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../../assets/libs/phpmailer/src/SMTP.php';

$UsersBase = 'dashboard/users';

$success = $error = '';

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
    $date_registered = date('Y-m-d H:i:s');

    if ($first_name === '' || $last_name === '' || $username === '' || $email === '' || $password === '') {
        $error = "Please fill required fields.";
    } else {

        // Check duplicates
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $dup = $stmt->get_result();
        $stmt->close();

        if ($dup->num_rows > 0) {
            $error = "Username or Email already exists.";
        } else {

            $hashed = password_hash($password, PASSWORD_BCRYPT);

            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users 
                (username, first_name, last_name, age, gender, address, email, password, phone_number, role, date_registered)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'sssisssssss',
                $username, $first_name, $last_name,
                $age, $gender, $address, $email,
                $hashed, $phone, $role, $date_registered
            );

            if ($stmt->execute()) {
                $stmt->close();

                /*
                |==========================================
                | SEND EMAIL NOTIFICATION TO NEW USER
                |==========================================
                */

                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.hostinger.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'admin@animal-bite-center.com';
                    $mail->Password   = 'Popoy4682...';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    $mail->setFrom('admin@animal-bite-center.com', 'LFG Animal Bite Center');
                    $mail->addAddress($email, $first_name . " " . $last_name);

                    // Add logo
                    $mail->AddEmbeddedImage('../../assets/images/logo-white.png', 'abclogo', 'logo-white.png');

                    $mail->isHTML(true);
                    $mail->Subject = 'Your LFG Animal Bite Center Account Details';

                    $mail->Body = "
                    <div style='font-family: Arial; padding: 20px; background: #f5f5f5;'>
                      <div style='max-width: 480px; margin:auto; background:#fff; padding:25px; border-radius:8px;'>

                        <h2 style='color:#333;'>Hello " . strtoupper($first_name) . "!</h2>
                        <p>Your Animal Bite Center account has been successfully created by the system administrator.</p>

                        <p><strong>Here are your login details:</strong></p>

                        <div style='background:#f0f0f0; padding:15px; border-radius:8px;'>
                          <p><strong>Username:</strong> {$username}</p>
                          <p><strong>Password:</strong> {$password}</p>
                        </div>

                        <div style='text-align:center; margin:25px 0;'>
                          <img src='cid:abclogo' style='width:250px;'>
                        </div>

                        <p>Thank you,<br><strong>LFG Animal Bite Center</strong></p>
                      </div>
                    </div>
                    ";

                    $mail->send();

                } catch (Exception $e) {
                    error_log("Email sending failed: " . $mail->ErrorInfo);
                }

                // Redirect after success
                header("Location: index.php?added=1");
                exit;

            } else {
                $error = "Failed to add user.";
                $stmt->close();
            }
        }
    }
}

include '../../layouts/head.php';
?>



<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add New User</h5>
        <a href="<?= $UsersBase ?>/index.php" class="btn btn-outline-secondary">Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">First Name</label>
              <input name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Last Name</label>
              <input name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Password</label>
              <div class="relative">
                <input type="password" name="password" id="password" class="form-control pr-10" required>
                <button type="button" class="pw-toggle-btn absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                  👁
                </button>
              </div>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Role</label>
              <select name="role" class="form-control" required>
                <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                <option value="community_user" <?= (($_POST['role'] ?? '') === 'community_user') ? 'selected' : '' ?>>Community User</option>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
                <label class="form-label">Phone Number</label>
                <input name="phone_number" id="phone_number" class="form-control" 
                      placeholder="09123456789 or +639123456789"
                      value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
              </div>

            <div class="col-span-12 md:col-span-3">
              <label class="form-label">Age</label>
              <input type="number" name="age" min="0" class="form-control" value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
            </div>

            <div class="col-span-12 md:col-span-3">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select</option>
                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              </select>
            </div>

            <div class="col-span-12">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

          </div>

          <div class="pt-4">
            <button class="btn btn-primary mt-5">Save User</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<script>
// =======================
// SHOW/HIDE PASSWORD
// =======================
document.querySelector('.pw-toggle-btn')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    if (!input) return;

    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";
    this.textContent = isHidden ? "🙈" : "👁";
});

// =======================
// PHONE INPUT (PH Only)
// =======================
const phoneInput = document.getElementById('phone_number');

if (phoneInput) {
    phoneInput.addEventListener('input', function () {
        let v = this.value;

        // Allow only digits and +
        v = v.replace(/[^0-9+]/g, '');

        // Only allow + at start
        if (v.indexOf('+') > 0) {
            v = v.replace(/\+/g, '');
        }

        // Enforce PH number structure
        if (v.startsWith('+')) {
            // +63XXXXXXXXX   → length 13
            v = '+' + v.replace(/[^\d]/g, '');
            v = v.substring(0, 13);
        } else {
            // 09123456789 → length 11
            v = v.substring(0, 11);
        }

        this.value = v;
    });
}
</script>

