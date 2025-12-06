<?php
// dashboard/users/index.php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../layouts/head.php';

$UsersBase = 'dashboard/users';

// Admin-only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

// Fetch users depending on role
if ($_SESSION['role'] === 'staff') {
    // Staff can only see community users
    $res = $conn->query("
        SELECT user_id, username, first_name, last_name, age, gender, address, email, role, phone_number, date_registered
        FROM users
        WHERE role = 'community_user'
        ORDER BY user_id DESC
    ");
} else {
    // Admin can see all
    $res = $conn->query("
        SELECT user_id, username, first_name, last_name, age, gender, address, email, role, phone_number, date_registered
        FROM users
        ORDER BY user_id DESC
    ");
}

$users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card table-card">
      <div class="card-header flex justify-between items-center">
          <h5>Users Management</h5>

          <div class="flex gap-2">
              <a href="<?= $UsersBase ?>/export_users_excel.php" class="btn btn-outline-success">
                Export to Excel
              </a>
              <a href="<?= $UsersBase ?>/add.php" class="btn btn-primary">Add New User</a>
          </div>
        </div>

      <div class="card-body p-5">
        <!-- notifications -->
        <?php if (isset($_GET['added'])): ?>
          <div class="mb-4 px-4 py-3 mx-5 bg-success text-white rounded-lg">User added successfully.</div>
        <?php elseif (isset($_GET['updated'])): ?>
          <div class="mb-4 px-4 py-3 mx-5 bg-info text-white rounded-lg">User updated successfully.</div>
        <?php elseif (isset($_GET['deleted'])): ?>
          <div class="mb-4 px-4 py-3 mx-5 bg-red text-white rounded-lg">User deleted successfully.</div>
        <?php elseif (isset($_GET['error'])): ?>
          <div class="mb-4 px-4 py-3 mx-5 bg-warning text-black rounded-lg">An error occurred. Please try again.</div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="grid grid-cols-12 gap-3 mb-4 p-4 bg-gray-50 rounded">
          <div class="col-span-12 md:col-span-3">
            <input id="searchName" class="form-control" placeholder="Search by name">
          </div>
          <div class="col-span-12 md:col-span-3">
            <input id="searchEmail" class="form-control" placeholder="Search by email">
          </div>
          <?php if ($_SESSION['role'] !== 'community_user'): ?>
            <div class="col-span-12 md:col-span-3">
              <select id="filterRole" class="form-control">
            
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <option value="">All Roles</option>
                  <option value="admin">Admin</option>
                  <option value="staff">Staff</option>
                <?php endif; ?>
            
                <!-- Always visible if admin or staff -->
                <option value="community_user">Community User</option>
              </select>
            </div>
            <?php endif; ?>
          <div class="col-span-12 md:col-span-3 flex gap-2">
            <button id="clearFilters" class="btn btn-outline-secondary w-full">Clear</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="usersTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Role</th>
                <th>Phone</th>
                <th>Date Registered</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($users) === 0): ?>
                <tr><td colspan="10" class="text-center py-4">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?= (int)$u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['age']) ?: '—' ?></td>
                    <td><?= htmlspecialchars($u['gender']) ?: '—' ?></td>
                    <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                    <td><?= htmlspecialchars($u['phone_number'] ?: '—') ?></td>
                    <td><?= $u['date_registered'] ? date('F j, Y', strtotime($u['date_registered'])) : '—' ?></td>
                    <td class="flex gap-2">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $UsersBase ?>/edit.php?id=<?= $u['user_id'] ?>">Edit</a>
                      <a class="btn btn-sm btn-outline-danger" href="<?= $UsersBase ?>/delete.php?id=<?= $u['user_id'] ?>" onclick="return confirm('Delete this user?');">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchName = document.getElementById('searchName');
  const searchEmail = document.getElementById('searchEmail');
  const filterRole = document.getElementById('filterRole');
  const clearBtn = document.getElementById('clearFilters');
  const rows = document.querySelectorAll('#usersTable tbody tr');

  function filterRows() {
    const nameVal = searchName.value.toLowerCase();
    const emailVal = searchEmail.value.toLowerCase();
    const roleVal = filterRole.value.toLowerCase();

    rows.forEach(row => {
      // skip empty-row message
      if (!row.cells || row.cells.length < 2) return;
      const name = row.cells[1].textContent.toLowerCase();
      const email = row.cells[3].textContent.toLowerCase();
      const role = row.cells[6].textContent.toLowerCase();
      const match = name.includes(nameVal) && email.includes(emailVal) && (roleVal === '' || role === roleVal);
      row.style.display = match ? '' : 'none';
    });
  }

  [searchName, searchEmail, filterRole].forEach(el => el.addEventListener('input', filterRows));
  clearBtn.addEventListener('click', () => {
    searchName.value = '';
    searchEmail.value = '';
    filterRole.value = '';
    filterRows();
  });
});
</script>
