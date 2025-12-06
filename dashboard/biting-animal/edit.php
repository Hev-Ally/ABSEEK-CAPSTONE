<?php
include '../../layouts/head.php';
$animalBase = 'dashboard/biting-animal';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
$row = $conn->query("SELECT * FROM biting_animal WHERE biting_animal_id = $id")->fetch_assoc();

if (!$row) {
    header("Location: index.php?error=NotFound");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['animal_name']);
    if ($name === '') {
        $error = "Animal name is required";
    } else {
        $stmt = $conn->prepare("UPDATE biting_animal SET animal_name=? WHERE biting_animal_id=?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $success = "Updated successfully!";
    }
}
?>
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Animal</h5>
        <a href="<?= $animalBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error): ?><div class="bg-red text-white p-3 mb-3"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="bg-success text-white p-3 mb-3"><?= $success ?></div><?php endif; ?>

        <form method="POST">
          <label class="form-label">Animal Name</label>
          <input type="text" name="animal_name" class="form-control" value="<?= htmlspecialchars($row['animal_name']) ?>" required>

          <button class="btn btn-primary mt-4">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../../layouts/footer-block.php'; ?>
