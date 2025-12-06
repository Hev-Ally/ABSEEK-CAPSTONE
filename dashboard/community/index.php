<?php include '../../layouts/head.php'; ?>
<?php
require_once '../../assets/auth.php';
?>
<div class="page-header">
  <div class="page-block">
    <div class="page-header-title">
      <h5 class="mb-0 font-medium">Dashboard</h5>
    </div>
  </div>
</div>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12 xl:col-span-4 md:col-span-6">
    <div class="card">
      <div class="card-header"><h5>Welcome, <?= htmlspecialchars($user_fullname) ?>!</h5></div>
      <div class="card-body">
        <p>Your role: <strong><?= htmlspecialchars($user_role) ?></strong></p>
        <p>Use the sidebar to navigate through your dashboard features.</p>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
