<?php
// lfgabc/dashboard/reports/index.php
include '../../layouts/head.php';
$ReportsBase = 'dashboard/reports';

// Require admin

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}
// Fetch reports with full joins (animal & category)
$sql = "
  SELECT
    r.report_id,
    r.user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
    u.phone_number,
    r.type_of_bite,
    ba.animal_name AS animal_type,
    c.category_name,
    br.barangay_name,
    r.status,
    r.latitud,
    r.longhitud
  FROM reports r
  LEFT JOIN users u ON r.user_id = u.user_id
  LEFT JOIN biting_animal ba ON r.biting_animal_id = ba.biting_animal_id
  LEFT JOIN category c ON r.category_id = c.category_id
  LEFT JOIN barangay br ON r.barangay_id = br.barangay_id
  ORDER BY r.report_id DESC
";
$result = $conn->query($sql);
$reports = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card table-card">
      <div class="card-header flex justify-between items-center">
        <h5>Reports Management</h5>
        <a href="<?= $ReportsBase ?>/add.php" class="btn btn-primary">Add Report</a>
      </div>

      <div class="card-body">
        <!-- Filters -->
        <div class="grid grid-cols-12 gap-3 mb-4 p-4 bg-gray-50 rounded-lg">
          <div class="col-span-12 md:col-span-3">
            <input type="text" id="filterReporter" class="form-control" placeholder="Search Reporter">
          </div>
          <div class="col-span-12 md:col-span-2">
            <select id="filterType" class="form-control">
              <option value="">All Types</option>
              <option value="Bite">Bite</option>
              <option value="Scratch">Scratch</option>
            </select>
          </div>
          <div class="col-span-12 md:col-span-3">
            <input type="text" id="filterAnimal" class="form-control" placeholder="Search Animal Type">
          </div>
          <div class="col-span-12 md:col-span-2">
            <select id="filterStatus" class="form-control">
              <option value="">All Status</option>
              <option value="Reported">Reported</option>
              <option value="Contacted">Contacted</option>
              <option value="Admitted">Admitted</option>
              <option value="Treated">Treated</option>
              <option value="Archived">Archived</option>
            </select>
          </div>
          <div class="col-span-12 md:col-span-2">
            <button id="clearFilters" class="btn btn-outline-secondary w-full">Clear</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="reportsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Reporter</th>
                <th>Phone</th>
                <th>Type</th>
                <th>Animal</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $r): ?>
              <tr data-report='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'>
                <td><?= $r['report_id'] ?></td>
                <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                <td><?= htmlspecialchars($r['phone_number']) ?></td>
                <td><?= htmlspecialchars($r['type_of_bite']) ?></td>
                <td><?= htmlspecialchars($r['animal_type'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['barangay_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td class="flex gap-2">
                  <a href="<?= $ReportsBase ?>/view.php?id=<?= $r['report_id'] ?>" 
                    class="btn btn-sm btn-outline-info">
                    View
                  </a>
                  <a href="<?= $ReportsBase ?>/edit.php?id=<?= $r['report_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <button class="btn btn-sm btn-outline-danger archive-report" data-id="<?= $r['report_id'] ?>">Archive</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="text-center mt-4 ml-5">
            <button id="loadMore" class="btn btn-outline-primary px-5">Load More</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Filtering + pagination (client-side)
  const rows = Array.from(document.querySelectorAll('#reportsTable tbody tr'));
  const reporterFilter = document.getElementById('filterReporter');
  const typeFilter = document.getElementById('filterType');
  const animalFilter = document.getElementById('filterAnimal');
  const statusFilter = document.getElementById('filterStatus');
  const clearBtn = document.getElementById('clearFilters');
  const loadMoreBtn = document.getElementById('loadMore');
  const tbody = document.querySelector('#reportsTable tbody');

  let rowsPerPage = 10;
  let currentIndex = 0;

  function getVisibleRows() {
    const rVal = reporterFilter.value.toLowerCase();
    const tVal = typeFilter.value.toLowerCase();
    const aVal = animalFilter.value.toLowerCase();
    const sVal = statusFilter.value.toLowerCase(); // selected status

    return rows.filter(r => {
      const cols = r.querySelectorAll('td');
      const reporter = cols[1].textContent.toLowerCase();
      const type = cols[3].textContent.toLowerCase();
      const animal = cols[4].textContent.toLowerCase();
      const status = cols[6].textContent.toLowerCase(); // row status

      // ⭐ NEW LOGIC: Hide archived unless selected
      const statusMatch =
        (sVal === '' && status !== 'archived') ||   // default = hide archived
        (sVal !== '' && status === sVal);           // show only selected status

      return reporter.includes(rVal) &&
             (tVal === '' || type === tVal) &&
             animal.includes(aVal) &&
             statusMatch;
    });
  }

  function renderPage() {
    rows.forEach(r => r.style.display = 'none');
    const visible = getVisibleRows();
    visible.slice(0, currentIndex + rowsPerPage).forEach(r => r.style.display = '');
    loadMoreBtn.style.display = visible.length > currentIndex + rowsPerPage ? '' : 'none';
  }

  function reloadFilters() {
    currentIndex = 0;
    renderPage();
  }

  [reporterFilter, typeFilter, animalFilter, statusFilter].forEach(i => 
    i.addEventListener('input', reloadFilters)
  );

  clearBtn.addEventListener('click', () => {
    reporterFilter.value = '';
    typeFilter.value = '';
    animalFilter.value = '';
    statusFilter.value = '';
    reloadFilters();
  });

  loadMoreBtn.addEventListener('click', () => {
    currentIndex += rowsPerPage;
    renderPage();
  });

  renderPage();

  // Archive
  document.querySelectorAll('.archive-report').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Archive this report?')) return;
      const id = btn.dataset.id;
      try {
        const res = await fetch('<?= $ReportsBase ?>/archive_report.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${id}`
        });
        const data = await res.json();
        if (data.success) {
          btn.closest('tr').querySelector('td:nth-child(7)').textContent = 'Archived';
          btn.disabled = true;
          btn.textContent = 'Archived';
          btn.classList.remove('btn-outline-danger');
          btn.classList.add('btn-success');
          const n = document.createElement('div');
          n.className = 'fixed bottom-5 right-5 bg-success text-white px-4 py-2 rounded shadow-lg z-[3000]';
          n.textContent = 'Report archived';
          document.body.appendChild(n);
          setTimeout(() => n.remove(), 2000);

          // Auto-hide immediately after archive
          reloadFilters();

        } else {
          alert('Failed to archive');
        }
      } catch {
        alert('Connection error');
      }
    });
  });

});
</script>

