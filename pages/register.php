<?php
session_start();
require_once '../assets/db/db.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../assets/libs/phpmailer/src/Exception.php';
require __DIR__ . '/../assets/libs/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../assets/libs/phpmailer/src/SMTP.php';

$message = '';
$errorList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect form data
    $username   = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone_number'] ?? '');
    $age        = trim($_POST['age'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $agree      = isset($_POST['agree']);

    // =============================
    // VALIDATION
    // =============================
    if ($username === '') $errorList[] = 'Username is required.';
    if ($first_name === '') $errorList[] = 'First name is required.';
    if ($last_name === '') $errorList[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errorList[] = 'Valid email is required.';

    // Phone validation
    if ($phone !== '') {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $clean)) {
            $errorList[] = 'Enter a valid PH mobile number (09123456789 or +639123456789).';
        }
    }

    if ($age !== '' && (!is_numeric($age) || $age < 0 || $age > 120)) {
        $errorList[] = 'Enter a valid age.';
    }

    if (!in_array($gender, ['Male', 'Female'])) {
        $errorList[] = 'Please select a valid gender.';
    }

    if (strlen($password) < 6) $errorList[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errorList[] = 'Passwords do not match.';
    if (!$agree) $errorList[] = 'You must agree to the Terms & Conditions.';

    // =============================
    // UNIQUE CHECK
    // =============================
    if (empty($errorList)) {

        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errorList[] = 'Username or email already exists.';
            $check->close();
        } else {
            $check->close();

            // =============================
            // INSERT USER INTO DB
            // =============================
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $today  = date('Y-m-d H:i:s');
            $role   = 'community_user';

            $stmt = $conn->prepare("
                INSERT INTO users 
                (username, first_name, last_name, email, phone_number, age, gender, address, password, role, date_registered)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "sssssssssss",
                $username,
                $first_name,
                $last_name,
                $email,
                $phone,
                $age,
                $gender,
                $address,
                $hashed,
                $role,
                $today
            );

            if ($stmt->execute()) {

                // =============================
                // SEND EMAIL NOTIFICATION
                // =============================
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

                    $mail->AddEmbeddedImage('../assets/images/logo-white.png', 'abclogo', 'logo-white.png');

                    $mail->isHTML(true);
                    $mail->Subject = 'Your LFG Animal Bite Center Account Details';

                    $mail->Body = "
                    <div style='font-family: Arial; padding: 20px; background: #f5f5f5;'>
                      <div style='max-width: 480px; margin:auto; background:#fff; padding:25px; border-radius:8px;'>

                        <h2 style='color:#333;'>Hello " . strtoupper($first_name) . "!</h2>
                        <p>Your Animal Bite Center account has been successfully created.</p>

                        <p><strong>Here are your login details:</strong></p>

                        <div style='background:#f0f0f0; padding:15px; border-radius:8px;'>
                          <p><strong>Username:</strong> {$username}</p>
                          <p><strong>Password:</strong> {$password}</p>
                        </div>

                        <div style='text-align:center; margin:25px 0;'>
                          <img src='cid:abclogo' style='width:250x;'>
                        </div>

                        <p>Thank you,<br><strong>LFG Animal Bite Center</strong></p>

                      </div>
                    </div>
                    ";

                    $mail->send();

                } catch (Exception $e) {
                    error_log("Email Error: {$mail->ErrorInfo}");
                }

                $message = "Registration successful! <a href='login.php'>Login here</a>.";
                $_POST = []; // clear fields

            } else {
                $errorList[] = 'Failed to register.';
            }

            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>Register | Animal Bite Monitoring System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="../assets/css/style.css">

  <style>
    .pw-input-wrap { position: relative; }
    .pw-toggle-btn {
        position:absolute; right:10px; top:50%; transform:translateY(-50%);
        cursor:pointer; background:none; border:none; font-size:18px;
    }
  </style>
</head>

<body>

<div class="auth-main relative">
  <div class="auth-wrapper flex items-center w-full min-h-screen">
    <div class="auth-form flex items-center justify-center grow flex-col p-6">

      <div class="w-full max-md-w-600">

        <div class="card">
          <div class="card-body p-10 text-center">

            <div class="text-center mb-5">
              <img src="../assets/images/logo-white.png" class="mx-auto" style="width:240px;">
            </div>

            <h4 class="text-center mb-4">Sign Up</h4>

            <!-- ERROR -->
            <?php if (!empty($errorList)): ?>
              <div class="p-3 mb-3 p-5 rounded" style="background:#ffe5e5;color:#a10000;">
                <ul>
                  <?php foreach ($errorList as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php elseif ($message): ?>
              <div class="p-3 mb-3 p-5 rounded text-center" style="background:#e1ffe8;color:#007f1a;">
                <?= $message ?>
              </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST">

              <!-- NAME -->
              <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-6"><input name="first_name" class="form-control" placeholder="First Name" required value="<?= $_POST['first_name'] ?? '' ?>"></div>
                <div class="col-span-6"><input name="last_name" class="form-control" placeholder="Last Name" required value="<?= $_POST['last_name'] ?? '' ?>"></div>
              </div>

              <div class="mb-3"><input name="username" class="form-control" placeholder="Username" required value="<?= $_POST['username'] ?? '' ?>"></div>

              <!-- EMAIL + PHONE -->
              <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-6"><input type="email" name="email" class="form-control" placeholder="Email" required value="<?= $_POST['email'] ?? '' ?>"></div>
                <div class="col-span-6"><input name="phone_number" class="form-control" placeholder="Phone Number" value="<?= $_POST['phone_number'] ?? '' ?>"></div>
              </div>

              <!-- AGE + GENDER -->
              <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-6"><input type="number" name="age" class="form-control" placeholder="Age" value="<?= $_POST['age'] ?? '' ?>"></div>
                <div class="col-span-6">
                  <select name="gender" class="form-control">
                    <option value="">Gender</option>
                    <option value="Male"   <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                  </select>
                </div>
              </div>

              <textarea name="address" class="form-control mb-3" placeholder="Address" rows="2"><?= $_POST['address'] ?? '' ?></textarea>

              <!-- PASSWORD -->
              <div class="mb-3 pw-input-wrap">
                <input type="password" id="password" name="password" class="form-control" placeholder="Password">
                <button type="button" class="pw-toggle-btn" data-target="password">👁</button>
              </div>

              <div class="mb-3 pw-input-wrap">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm Password">
                <button type="button" class="pw-toggle-btn" data-target="confirm_password">👁</button>
              </div>

              <!-- AGREE -->
              <div class="form-check mb-3">
                <input type="checkbox" name="agree" class="form-check-input" id="agreeCheck">
                <label class="form-check-label" for="agreeCheck">I agree to the Terms & Conditions</label>
              </div>

              <button class="btn btn-primary w-full">Sign Up</button>

              <div class="mt-4 text-center">
                Already have an account? <a href="login.php">Login</a>
              </div>

            </form>

          </div>
        </div>

      </div>

    </div>
  </div>
</div>


<script>
// Password toggle
document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    let input = document.getElementById(btn.dataset.target);
    let isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? '🙈' : '👁';
  });
});

// Phone mask
document.querySelector('input[name="phone_number"]').addEventListener('input', function() {
    let v = this.value.replace(/[^0-9+]/g, '');
    if (v.indexOf('+') > 0) v = v.replace(/\+/g, '');
    if (v.startsWith('+')) v = '+' + v.replace(/[^\d]/g, '');
    if (v.startsWith('+63')) v = v.substring(0, 13);
    else v = v.substring(0, 11);
    this.value = v;
});
</script>

</body>
</html>
