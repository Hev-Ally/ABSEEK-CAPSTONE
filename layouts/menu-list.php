<?php
$userRole = $_SESSION['role'] ?? 'guest';
?>

<?php if ($userRole === 'admin'): ?>
<li class="pc-item pc-caption"><label>Management</label></li>

<li class="pc-item <?= isActive('dashboard/users/') ?>">
  <a href="<?= $assetBase ?>/dashboard/users/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="users"></i></span>
    <span class="pc-mtext">Users</span>
  </a>
</li>

<li class="pc-item <?= isActive('dashboard/reports/') ?>">
  <a href="<?= $assetBase ?>/dashboard/reports/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="file-text"></i></span>
    <span class="pc-mtext">Reports</span>
  </a>
</li>

<li class="pc-item <?= isActive('dashboard/patients/') ?>">
  <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="user-check"></i></span>
    <span class="pc-mtext">Patients</span>
  </a>
</li>

<li class="pc-item pc-hasmenu">
  <a href="" class="pc-link"><span class="pc-micon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-align-right"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg> </span><span class="pc-mtext">Analytics</span><span class="pc-arrow"><i class="ti ti-chevron-right"></i></span></a>
    <ul class="pc-submenu" style="display: none;">

      <!-- Analytics -->
      <li class="pc-item <?= isActive('dashboard/analytics/index.php') ?>">
        <a href="<?= $assetBase ?>/dashboard/analytics/index.php" class="pc-link">
          <span class="pc-micon"><i data-feather="activity"></i></span>
          <span class="pc-mtext">Analytics</span>
        </a>
      </li>
      
      <!-- Heatmap (existing) -->
      <li class="pc-item <?= isActive('dashboard/analytics/heatmap.php') ?>">
        <a href="<?= $assetBase ?>/dashboard/analytics/heatmap.php" class="pc-link">
          <span class="pc-micon"><i data-feather="activity"></i></span>
          <span class="pc-mtext">Heatmap</span>
        </a>
      </li>

    </ul>
</li>

<li class="pc-item pc-caption"><label>Misc.</label></li>

<li class="pc-item <?= isActive('dashboard/barangay/') ?>">
  <a href="<?= $assetBase ?>/dashboard/barangay/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="map-pin"></i></span>
    <span class="pc-mtext">Barangays</span>
  </a>
</li>
<li class="pc-item <?= isActive('dashboard/biting-animal/') ?>">
  <a href="<?= $assetBase ?>/dashboard/biting-animal/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="alert-triangle"></i></span>
    <span class="pc-mtext">Biting Animal</span>
  </a>
</li>
<li class="pc-item <?= isActive('dashboard/category/') ?>">
  <a href="<?= $assetBase ?>/dashboard/category/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="file-text"></i></span>
    <span class="pc-mtext">Category</span>
  </a>
</li>
<li class="pc-item <?= isActive('dashboard/vaccine/') ?>">
  <a href="<?= $assetBase ?>/dashboard/vaccine/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="edit-3"></i></span>
    <span class="pc-mtext">Vaccines</span>
  </a>
</li>
<?php endif; ?>

<?php if ($userRole === 'staff'): ?>
<li class="pc-item pc-caption"><label>Management</label></li>

<li class="pc-item <?= isActive('dashboard/users/') ?>">
  <a href="<?= $assetBase ?>/dashboard/users/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="users"></i></span>
    <span class="pc-mtext">Users</span>
  </a>
</li>

<li class="pc-item <?= isActive('dashboard/reports/') ?>">
  <a href="<?= $assetBase ?>/dashboard/reports/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="file-text"></i></span>
    <span class="pc-mtext">Reports</span>
  </a>
</li>

<li class="pc-item <?= isActive('dashboard/patients/') ?>">
  <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="user-check"></i></span>
    <span class="pc-mtext">Patients</span>
  </a>
</li>

<li class="pc-item pc-hasmenu">
  <a href="" class="pc-link"><span class="pc-micon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-align-right"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg> </span><span class="pc-mtext">Analytics</span><span class="pc-arrow"><i class="ti ti-chevron-right"></i></span></a>
    <ul class="pc-submenu" style="display: none;">

      <!-- Analytics -->
      <li class="pc-item <?= isActive('dashboard/analytics/index.php') ?>">
        <a href="<?= $assetBase ?>/dashboard/analytics/index.php" class="pc-link">
          <span class="pc-micon"><i data-feather="activity"></i></span>
          <span class="pc-mtext">Analytics</span>
        </a>
      </li>
      
      <!-- Heatmap (existing) -->
      <li class="pc-item <?= isActive('dashboard/analytics/heatmap.php') ?>">
        <a href="<?= $assetBase ?>/dashboard/analytics/heatmap.php" class="pc-link">
          <span class="pc-micon"><i data-feather="activity"></i></span>
          <span class="pc-mtext">Heatmap</span>
        </a>
      </li>

    </ul>
</li>
<li class="pc-item <?= isActive('dashboard/community/profile/') ?>">
  <a href="<?= $assetBase ?>/dashboard/community/profile/" class="pc-link">
    <span class="pc-micon"><i data-feather="user"></i></span>
    <span class="pc-mtext">Update Profile</span>
  </a>
</li>
<?php endif; ?>

<?php if ($userRole === 'community_user'): ?>
<li class="pc-item <?= isActive('dashboard/community/reports/index.php') ?>">
  <a href="<?= $assetBase ?>/dashboard/community/reports/index.php" class="pc-link">
    <span class="pc-micon"><i data-feather="file-text"></i></span>
    <span class="pc-mtext">Reports</span>
  </a>
</li>
<li class="pc-item <?= isActive('dashboard/community/reports/report-incident.php') ?>">
  <a href="<?= $assetBase ?>/dashboard/community/reports/report-incident.php" class="pc-link">
    <span class="pc-micon"><i data-feather="file-text"></i></span>
    <span class="pc-mtext">Report Incident</span>
  </a>
</li>
 <!-- Heatmap (existing) -->
<li class="pc-item <?= isActive('dashboard/analytics/heatmap.php') ?>">
<a href="<?= $assetBase ?>/dashboard/analytics/heatmap.php" class="pc-link">
  <span class="pc-micon"><i data-feather="activity"></i></span>
  <span class="pc-mtext">Heatmap</span>
</a>
</li>
<li class="pc-item <?= isActive('dashboard/community/patient/') ?>">
  <a href="<?= $assetBase ?>/dashboard/community/patient/" class="pc-link">
    <span class="pc-micon"><i data-feather="user"></i></span>
    <span class="pc-mtext">My Patient Info</span>
  </a>
</li>
<li class="pc-item <?= isActive('dashboard/community/profile/') ?>">
  <a href="<?= $assetBase ?>/dashboard/community/profile/" class="pc-link">
    <span class="pc-micon"><i data-feather="user"></i></span>
    <span class="pc-mtext">Update Profile</span>
  </a>
</li>
<?php endif; ?>

<li class="pc-item">
  <a href="<?= $assetBase ?>/pages/logout.php" class="pc-link">
    <span class="pc-micon"><i data-feather="log-out"></i></span>
    <span class="pc-mtext">Logout</span>
  </a>
</li>
<script>
  document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".pc-hasmenu > a.pc-link").forEach(function (menuLink) {
          menuLink.addEventListener("click", function (e) {
              e.preventDefault(); // stop navigation

              const parent = this.closest(".pc-hasmenu");
              const submenu = parent.querySelector(".pc-submenu");

              // Toggle submenu visibility
              submenu.style.display = submenu.style.display === "block" ? "none" : "block";

              // Optional: toggle active class
              parent.classList.toggle("active");
          });
      });
  });
</script>