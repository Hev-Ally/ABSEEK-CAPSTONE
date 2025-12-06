<?php
include '../../layouts/head.php';
$vaccineBase = 'dashboard/vaccine';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card table-card">

      <div class="card-header flex justify-between items-center">
        <h5>Anti-Rabies Vaccines</h5>
        <a href="<?= $vaccineBase ?>/add.php" class="btn btn-primary">Add Vaccine</a>
      </div>

      <div class="card-body p-5">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Generic Name</th>
                <th>Brand Name</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <?php
              $res = $conn->query("SELECT * FROM anti_ravies_vaccine ORDER BY anti_ravies_vaccine_id ASC");
              while ($row = $res->fetch_assoc()):
              ?>
              <tr>
                <td><?= $row['anti_ravies_vaccine_id'] ?></td>
                <td><?= htmlspecialchars($row['generic_name']) ?></td>
                <td><?= htmlspecialchars($row['brand_name']) ?></td>

                <td class="flex gap-2">
                  <a href="<?= $vaccineBase ?>/edit.php?id=<?= $row['anti_ravies_vaccine_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $row['anti_ravies_vaccine_id'] ?>">Delete</button>
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
    if (!confirm("Delete this vaccine?")) return;

    fetch("<?= $vaccineBase ?>/delete.php", {
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
