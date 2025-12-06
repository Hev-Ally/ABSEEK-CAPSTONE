<?php
session_start();
require_once '../assets/db/db.php';

$message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $emailOrUsername = trim($_POST['email']);
  $password = $_POST['password'];

  if (!empty($emailOrUsername) && !empty($password)) {
    // Query user by username or email
    $stmt = $conn->prepare("SELECT user_id, username, email, password, role, first_name, last_name FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $emailOrUsername, $emailOrUsername);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $user = $result->fetch_assoc();

      // Verify password
      if (password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['first_name'] . ' ' . $user['last_name'];

        // Redirect by role
        switch ($user['role']) {
          case 'admin':
            header("Location: ../dashboard/index.php");
            break;
          case 'staff':
            header("Location: ../dashboard/index.php");
            break;
          case 'community_user':
            header("Location: ../dashboard/index.php");
            break;
          default:
            header("Location: ../index.php");
        }
        exit;
      } else {
        $message = "Invalid password. Please try again.";
      }
    } else {
      $message = "No account found with that username or email.";
    }
  } else {
    $message = "Please fill in both fields.";
  }
}
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
  <title>Login | Animal Bite Monitoring System</title>

  <!-- Meta & Favicon -->
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Animal Bite Monitoring System Login Panel" />
  <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />

  <!-- Template CSS -->
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />

  <style>
    .pw-input-wrap { position: relative; }
    .pw-toggle-btn {
      position: absolute;
      right: 0.6rem;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      padding: 0.25rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .pw-toggle-btn svg { width: 20px; height: 20px; opacity: .8; }
  </style>
</head>

<body>
  <!-- Preloader -->
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>

  <!-- Auth Form -->
  <div class="auth-main relative">
    <div class="auth-wrapper v1 flex items-center w-full h-full min-h-screen">
      <div class="auth-form flex items-center justify-center grow flex-col min-h-screen relative p-6">
        <div class="w-full max-w-[350px] relative">
          <div class="auth-bg">
            <span class="absolute top-[-100px] right-[-100px] w-[300px] h-[300px] block rounded-full bg-theme-bg-1 animate-[floating_7s_infinite]"></span>
            <span class="absolute top-[150px] right-[-150px] w-5 h-5 block rounded-full bg-primary-500 animate-[floating_9s_infinite]"></span>
            <span class="absolute left-[-150px] bottom-[150px] w-5 h-5 block rounded-full bg-theme-bg-1 animate-[floating_7s_infinite]"></span>
            <span class="absolute left-[-100px] bottom-[-100px] w-[300px] h-[300px] block rounded-full bg-theme-bg-2 animate-[floating_9s_infinite]"></span>
          </div>

          <div class="card sm:my-12 w-full shadow-none">
            <div class="card-body !p-10">
              <div class="text-center mb-8">
                <a href="../index.php"><img src="../assets/images/logo-white.png" alt="logo" class="mx-auto"></a>
              </div>
              <h4 class="text-center font-medium mb-4">Login</h4>

              <!-- Error Message -->
              <?php if (!empty($message)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 10px; text-align: center;">
                  <?= htmlspecialchars($message); ?>
                </div>
              <?php endif; ?>

              <form method="POST" autocomplete="off">
                <div class="mb-3">
                  <input type="text" class="form-control" name="email" placeholder="Email or Username" required>
                </div>

                <!-- Password field with toggle -->
                <div class="mb-4 pw-input-wrap">
                  <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                  <button type="button" class="pw-toggle-btn" data-target="password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                </div>

                <div class="flex mt-1 justify-between items-center flex-wrap">
                  <div class="form-check">
                    <input class="form-check-input input-primary" type="checkbox" id="rememberMe">
                    <label class="form-check-label text-muted" for="rememberMe">Remember me?</label>
                  </div>
                  <h6 class="font-normal text-primary-500 mb-0"><a href="#">Forgot Password?</a></h6>
                </div>

                <div class="mt-4 text-center">
                  <button type="submit" class="btn btn-primary mx-auto shadow-2xl w-full">Login</button>
                </div>

                <div class="flex justify-between items-end flex-wrap mt-4">
                  <h6 class="font-medium mb-0">Don’t have an account?</h6>
                  <a href="register.php" class="text-primary-500">Create Account</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../assets/js/plugins/simplebar.min.js"></script>
  <script src="../assets/js/plugins/popper.min.js"></script>
  <script src="../assets/js/icon/custom-icon.js"></script>
  <script src="../assets/js/plugins/feather.min.js"></script>
  <script src="../assets/js/component.js"></script>
  <script src="../assets/js/theme.js"></script>
  <script src="../assets/js/script.js"></script>

  <script>
    // Toggle show/hide password visibility
    document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;
        const isPwd = input.type === 'password';
        input.type = isPwd ? 'text' : 'password';

        const svg = this.querySelector('svg');
        if (svg) {
          if (isPwd) {
            // crossed eye (hide)
            svg.innerHTML = '<path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
          } else {
            // normal eye
            svg.innerHTML = '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
          }
        }
      });
    });

    // Layout presets
    layout_change('false');
    layout_theme_sidebar_change('dark');
    change_box_container('false');
    layout_caption_change('true');
    layout_rtl_change('false');
    preset_change('preset-1');
    main_layout_change('vertical');
  </script>
</body>
</html>
