<?php
include '../../layouts/head.php';
$barangayBase = 'dashboard/barangay';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangay_name = trim($_POST['barangay_name']);

    if ($barangay_name === '') {
        $error = "Barangay name is required.";
    } else {
        // Check duplicate
        $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE barangay_name = ?");
        $stmt->bind_param("s", $barangay_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Barangay already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO barangay (barangay_name) VALUES (?)");
            $stmt->bind_param("s", $barangay_name);
            $stmt->execute();
            $stmt->close();

            $success = "Barangay added successfully!";
        }
    }
}
?>
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add Barangay</h5>
        <a href="<?= $barangayBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error): ?><div class="bg-red text-white p-2 mb-3"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="bg-success text-white p-2 mb-3"><?= $success ?></div><?php endif; ?>

        <form method="POST">
          <label class="form-label">Barangay Name</label>
          <input type="text" name="barangay_name" class="form-control" required>

          <button class="btn btn-primary mt-4">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../../layouts/footer-block.php'; ?>
