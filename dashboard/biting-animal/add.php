<?php
include '../../layouts/head.php';
$animalBase = 'dashboard/biting-animal';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_name = trim($_POST['animal_name']);

    if ($animal_name === '') {
        $error = "Animal name is required.";
    } else {
        // Duplicate check
        $stmt = $conn->prepare("SELECT COUNT(*) FROM biting_animal WHERE animal_name = ?");
        $stmt->bind_param("s", $animal_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "This animal already exists!";
        } else {
            // Insert new animal
            $stmt = $conn->prepare("INSERT INTO biting_animal (animal_name) VALUES (?)");
            $stmt->bind_param("s", $animal_name);
            $stmt->execute();
            $stmt->close();

            $success = "Animal added successfully!";
        }
    }
}
?>
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add Animal</h5>
        <a href="<?= $animalBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error): ?><div class="bg-red text-white p-2 mb-3"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="bg-success text-white p-2 mb-3"><?= $success ?></div><?php endif; ?>

        <form method="POST">
          <label class="form-label">Animal Name</label>
          <input type="text" name="animal_name" class="form-control" required>

          <button class="btn btn-primary mt-4">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../../layouts/footer-block.php'; ?>
