<?php
include '../../layouts/head.php';
$animalBase = 'dashboard/biting-animal';

// Access control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}
?>
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card table-card">
      <div class="card-header flex justify-between items-center">
        <h5>Biting Animals</h5>
        <a href="<?= $animalBase ?>/add.php" class="btn btn-primary">Add Animal</a>
      </div>

      <div class="card-body p-5">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Animal Name</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $res = $conn->query("SELECT * FROM biting_animal ORDER BY animal_name ASC");
              while ($row = $res->fetch_assoc()):
              ?>
              <tr>
                <td><?= $row['biting_animal_id'] ?></td>
                <td><?= htmlspecialchars($row['animal_name']) ?></td>
                <td class="flex gap-2">
                  <a href="<?= $animalBase ?>/edit.php?id=<?= $row['biting_animal_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $row['biting_animal_id'] ?>">Delete</button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("click", function(e) {
  if (e.target.closest(".delete-btn")) {
    let id = e.target.closest(".delete-btn").dataset.id;
    if (!confirm("Delete this animal?")) return;

    fetch("<?= $animalBase ?>/delete.php", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: "id=" + id
    })
    .then(r => r.text())
    .then(t => {
      try {
        let d = JSON.parse(t);
        if (d.success) location.reload();
        else alert(d.message);
      } catch {
        alert("Server error");
      }
    });
  }
});
</script>

<?php include '../../layouts/footer-block.php'; ?>
