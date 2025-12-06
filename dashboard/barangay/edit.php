<?php
include '../../layouts/head.php';
$barangayBase = 'dashboard/barangay';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
$row = $conn->query("SELECT * FROM barangay WHERE barangay_id = $id")->fetch_assoc();

if (!$row) {
    header("Location: index.php?error=NotFound");
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangay_name = trim($_POST['barangay_name']);

    if ($barangay_name === '') {
        $error = "Barangay name is required.";
    } else {
        // Duplicate check except itself
        $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE barangay_name = ? AND barangay_id != ?");
        $stmt->bind_param("si", $barangay_name, $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Barangay already exists!";
        } else {
            $stmt = $conn->prepare("UPDATE barangay SET barangay_name=? WHERE barangay_id=?");
            $stmt->bind_param("si", $barangay_name, $id);
            $stmt->execute();
            $stmt->close();

            $success = "Updated successfully!";
        }
    }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Barangay</h5>
        <a href="<?= $barangayBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error): ?><div class="bg-red text-white p-2 mb-3"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="bg-success text-white p-2 mb-3"><?= $success ?></div><?php endif; ?>

        <form method="POST">
          <label class="form-label">Barangay Name</label>
          <input type="text" name="barangay_name" class="form-control"
                 value="<?= htmlspecialchars($row['barangay_name']) ?>" required>

          <button class="btn btn-primary mt-4">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
