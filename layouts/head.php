<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once realpath(__DIR__ . '/../assets/db/db.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: https://animal-bite-center.com/pages/login.php');
  exit;
}

// Get current URL path and compute the correct base dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseDir = ''; // <--- adjust only if your folder name changes
$assetBase = $protocol . '://' . $host . $baseDir;
$user_fullname = $_SESSION['fullname'] ?? 'User';
$user_role = ucfirst($_SESSION['role'] ?? 'User');
?>

<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
  <title>Dashboard | Animal Bite Monitoring System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Animal Bite Monitoring System Dashboard">

  <!-- ✅ Dynamic Base Path -->
  <base href="<?= $assetBase ?>/">

  <!-- [Favicon] -->
  <link rel="icon" href="<?= $assetBase ?>/assets/images/favicon.svg" type="image/x-icon">

  <!-- [Font & Icons] -->
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/fonts/phosphor/duotone/style.css">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/fonts/tabler-icons.min.css">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/fonts/feather.css">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/fonts/fontawesome.css">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/fonts/material.css">
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/style.css" id="main-style-link">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
  <!-- [ Pre-loader ] -->
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>

  <!-- Sidebar + Header -->
  <?php include __DIR__ . '/sidebar.php'; ?>
  <?php include __DIR__ . '/topbar.php'; ?>

  <div class="pc-container">
    <div class="pc-content">
