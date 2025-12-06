<?php
include '../../layouts/head.php';
$base = 'dashboard/category';

// Permissions
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);

// Load row
$stmt = $conn->prepare("SELECT * FROM category WHERE category_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: index.php?error=NotFound");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_name = trim($_POST['category_name'] ?? '');
    $category_details = trim($_POST['category_details'] ?? '');

    if ($category_name === '') {
        $error = "Category name is required.";
    } else {

        // Duplicate check excluding itself
        $stmt = $conn->prepare("SELECT COUNT(*) FROM category WHERE category_name = ? AND category_id != ?");
        $stmt->bind_param("si", $category_name, $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Another category with this name already exists!";
        } else {

            // Update
            $stmt = $conn->prepare("UPDATE category SET category_name = ?, category_details = ? WHERE category_id = ?");
            $stmt->bind_param("ssi", $category_name, $category_details, $id);
            $stmt->execute();
            $stmt->close();

            $success = "Category updated successfully!";
        }
    }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Category</h5>
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

          <label class="form-label">Category Name</label>
          <input type="text" name="category_name" class="form-control"
                value="<?= htmlspecialchars($row['category_name']) ?>" required>

          <label class="form-label mt-3">Category Details</label>
          <textarea name="category_details" class="form-control" rows="4"><?= htmlspecialchars($row['category_details']) ?></textarea>

          <button class="btn btn-primary mt-4">Save Changes</button>
        </form>

      </div>
    </div>

  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
