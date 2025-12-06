<?php
include '../../layouts/head.php';
$base = 'dashboard/vaccine';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $generic = trim($_POST['generic_name'] ?? '');
    $brand = trim($_POST['brand_name'] ?? '');

    if ($generic === '' || $brand === '') {
        $error = "Both fields are required.";
    } else {
        // Duplicate check
        $stmt = $conn->prepare("SELECT COUNT(*) FROM anti_ravies_vaccine WHERE generic_name = ? AND brand_name = ?");
        $stmt->bind_param("ss", $generic, $brand);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "This vaccine already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO anti_ravies_vaccine (generic_name, brand_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $generic, $brand);
            $stmt->execute();
            $stmt->close();

            $success = "Vaccine added successfully!";
        }
    }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add Vaccine</h5>
        <a href="<?= $base ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <?php if ($error): ?>
          <div class="bg-red text-white p-2 mb-3"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="bg-success text-white p-2 mb-3"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">

          <label class="form-label">Generic Name</label>
          <input type="text" name="generic_name" class="form-control" required>

          <label class="form-label mt-3">Brand Name</label>
          <input type="text" name="brand_name" class="form-control" required>

          <button class="btn btn-primary mt-4">Save</button>
        </form>

      </div>
    </div>

  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
