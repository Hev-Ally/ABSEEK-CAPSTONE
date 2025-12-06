<?php
include '../../layouts/head.php';
$base = 'dashboard/category';

// Permissions
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
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

        // Duplicate check
        $stmt = $conn->prepare("SELECT COUNT(*) FROM category WHERE category_name = ?");
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "This category already exists!";
        } else {

            // Insert new category
            $stmt = $conn->prepare("INSERT INTO category (category_name, category_details) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_name, $category_details);
            $stmt->execute();
            $stmt->close();

            $success = "Category added successfully!";
        }
    }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add Category</h5>
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
          <input type="text" name="category_name" class="form-control" required>

          <label class="form-label mt-3">Category Details</label>
          <textarea name="category_details" class="form-control" rows="4"></textarea>

          <button class="btn btn-primary mt-4">Save</button>
        </form>

      </div>
    </div>

  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
