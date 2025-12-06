<?php
$currentPath = $_SERVER['PHP_SELF'];

function isActive($pathSegment) {
    global $currentPath;
    return strpos($currentPath, $pathSegment) !== false ? 'active' : '';
}
?>

<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
  <div class="navbar-wrapper">
    <div class="m-header flex items-center my-25 h-header-height">
      <a href="../../dashboard/index.php" class="b-brand flex items-center gap-3 pt-5 pb-5">
        <img src="<?= $assetBase ?>/assets/images/logo-white.png" class="img-fluid logos no-auto-logo" alt="logo" />
        <img src="<?= $assetBase ?>/assets/images/favicon.png" class="img-fluid logos logo-sm no-auto-logo" alt="logo" />
      </a>
    </div>
    <div class="navbar-content h-[calc(100vh_-_150px)] py-2.5">
      <ul class="pc-navbar">
        <?php include __DIR__ . '/menu-list.php'; ?>
      </ul>
    </div>
  </div>
</nav>
<!-- [ Sidebar Menu ] end -->
