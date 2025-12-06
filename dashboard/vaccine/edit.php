<?php
include '../../layouts/head.php';
$base = 'dashboard/vaccine';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);

// Load row
$stmt = $conn->prepare("SELECT * FROM anti_ravies_vaccine WHERE anti_ravies_vaccine_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: index.php?error=NotFound");
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

        // Duplicate check excluding itself
        $stmt = $conn->prepare("SELECT COUNT(*) FROM anti_ravies_vaccine WHERE generic_name = ? AND brand_name = ? AND anti_ravies_vaccine_id != ?");
        $stmt->bind_param("ssi", $generic, $brand, $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Another vaccine with same name already exists.";
        } else {

            $stmt = $conn->prepare("UPDATE anti_ravies_vaccine SET generic_name = ?, brand_name = ? WHERE anti_ravies_vaccine_id = ?");
            $stmt->bind_param("ssi", $generic, $brand, $id);
            $stmt->execute();
            $stmt->close();

            $success = "Vaccine updated successfully!";
        }
    }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Vaccine</h5>
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
          <input type="text" name="generic_name" class="form-control"
                value="<?= htmlspecialchars($row['generic_name']) ?>" required>

          <label class="form-label mt-3">Brand Name</label>
          <input type="text" name="brand_name" class="form-control"
                value="<?= htmlspecialchars($row['brand_name']) ?>" required>

          <button class="btn btn-primary mt-4">Save Changes</button>
        </form>

      </div>
    </div>

  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
