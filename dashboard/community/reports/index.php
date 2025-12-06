<?php
include '../../../layouts/head.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'community_user') {
    header('Location: ../../../pages/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$ReportsBase = '/dashboard/community/reports';

// Fetch reports for logged-in community user
$stmt = $conn->prepare("
    SELECT 
        r.report_id,
        r.type_of_bite,
        r.status,
        r.longhitud,
        r.latitud,
        b.description,
        br.barangay_name,
        a.animal_name,
        c.category_name
    FROM reports r
    LEFT JOIN bites b ON b.report_id = r.report_id
    LEFT JOIN barangay br ON br.barangay_id = r.barangay_id
    LEFT JOIN biting_animal a ON a.biting_animal_id = r.biting_animal_id
    LEFT JOIN category c ON c.category_id = r.category_id
    WHERE r.user_id = ?
    ORDER BY r.report_id DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch user info
$u = $conn->query("SELECT first_name, last_name, phone_number FROM users WHERE user_id = $userId")->fetch_assoc();
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card table-card">
      <div class="card-header flex justify-between items-center">
        <h5>My Reports</h5>
        <a href="<?= $ReportsBase ?>/report-incident.php" class="btn btn-primary">Report Incident</a>
      </div>

      <div class="card-body">

        <!-- Filters -->
        <div class="grid grid-cols-12 gap-3 mb-4 p-4 bg-gray-50 rounded-lg">
          <div class="col-span-12 md:col-span-4">
            <input type="text" id="filterType" class="form-control" placeholder="Search Type (Bite/Scratch)">
          </div>

          <div class="col-span-12 md:col-span-4">
            <input type="text" id="filterBarangay" class="form-control" placeholder="Search Barangay">
          </div>

          <div class="col-span-12 md:col-span-3">
            <select id="filterStatus" class="form-control">
              <option value="">All Status</option>
              <option value="Reported">Reported</option>
              <option value="Contacted">Contacted</option>
              <option value="Admitted">Admitted</option>
              <option value="Treated">Treated</option>
              <option value="Archived">Archived</option>
            </select>
          </div>

          <div class="col-span-12 md:col-span-1">
            <button id="clearFilters" class="btn btn-outline-secondary w-full">Clear</button>
          </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="reportsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th>Animal</th>
                <th>Category</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($reports as $r): ?>
              <tr>
                <td><?= $r['report_id'] ?></td>
                <td><?= $r['type_of_bite'] ?></td>
                <td><?= $r['animal_name'] ?></td>
                <td><?= $r['category_name'] ?></td>
                <td><?= $r['barangay_name'] ?></td>
                <td><?= $r['status'] ?></td>
                <td class="flex gap-2">
                  <a href="<?= $ReportsBase ?>/view.php?id=<?= $r['report_id'] ?>" 
                       class="btn btn-sm btn-outline-info">
                       View
                    </a>

                  <a href="<?= $ReportsBase ?>/edit.php?id=<?= $r['report_id'] ?>"
                    class="btn btn-sm btn-outline-primary">
                    Edit
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="text-center mt-4">
            <button id="loadMore" class="btn btn-outline-primary px-5">Load More</button>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-black/40 z-[2000] flex items-center justify-center">
  <div class="bg-white p-6 rounded-lg shadow-xl relative w-[400px]">
    <button id="closeModal" class="absolute top-3 right-4 text-gray-400 hover:text-black text-2xl">&times;</button>
    <h5 class="text-lg font-semibold mb-4">Report Details</h5>

    <div id="reportDetails" class="space-y-2 mb-4"></div>
    <div id="reportMap" class="w-[300px] h-[250px] border rounded"></div>

    <div id="reportPhotos" class="grid grid-cols-3 gap-2 mt-4"></div>
  </div>
</div>

<?php include '../../../layouts/footer-block.php'; ?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {

    /* ================================
       FILTERS
    ================================= */
    const filterType = document.getElementById("filterType");
    const filterBarangay = document.getElementById("filterBarangay");
    const filterStatus = document.getElementById("filterStatus");
    const clearBtn = document.getElementById("clearFilters");
    const table = document.getElementById("reportsTable");
    const rows = table.querySelectorAll("tbody tr");

    function applyFilters() {
        const typeVal = filterType.value.toLowerCase();
        const brgyVal = filterBarangay.value.toLowerCase();
        const statusVal = filterStatus.value.toLowerCase();

        rows.forEach(row => {
            const type = row.children[1].textContent.toLowerCase();
            const barangay = row.children[4].textContent.toLowerCase();
            const status = row.children[5].textContent.toLowerCase();

            const matchType = type.includes(typeVal);
            const matchBrgy = barangay.includes(brgyVal);
            const matchStatus = statusVal === "" || status === statusVal;

            row.style.display = (matchType && matchBrgy && matchStatus) ? "" : "none";
        });
    }

    filterType.addEventListener("input", applyFilters);
    filterBarangay.addEventListener("input", applyFilters);
    filterStatus.addEventListener("change", applyFilters);

    clearBtn.addEventListener("click", () => {
        filterType.value = "";
        filterBarangay.value = "";
        filterStatus.value = "";
        applyFilters();
    });



    /* ================================
       LOAD MORE BUTTON
    ================================= */
    const loadMoreBtn = document.getElementById("loadMore");
    let visibleRows = 10; // show first 10

    function updateVisibleRows() {
        rows.forEach((row, index) => {
            row.style.display = index < visibleRows ? "" : "none";
        });

        if (visibleRows >= rows.length) {
            loadMoreBtn.style.display = "none";
        }
    }

    updateVisibleRows();

    loadMoreBtn.addEventListener("click", () => {
        visibleRows += 10;
        updateVisibleRows();
    });



    /* ================================
       VIEW MODAL
    ================================= */
    const modal = document.getElementById("viewModal");
    const closeModal = document.getElementById("closeModal");
    const detailsDiv = document.getElementById("reportDetails");
    const photosDiv = document.getElementById("reportPhotos");
    let mapInstance = null;

    document.querySelectorAll(".view-report").forEach(btn => {
        btn.addEventListener("click", () => {
            const report = JSON.parse(btn.dataset.report);
            const photos = JSON.parse(btn.dataset.photos);

            // Fill details
            detailsDiv.innerHTML = `
                <p><b>Type:</b> ${report.type_of_bite}</p>
                <p><b>Animal:</b> ${report.animal_name}</p>
                <p><b>Category:</b> ${report.category_name}</p>
                <p><b>Barangay:</b> ${report.barangay_name}</p>
                <p><b>Status:</b> ${report.status}</p>
            `;

            // Photos
            photosDiv.innerHTML = "";
            if (photos.length) {
                photos.forEach(p => {
                    photosDiv.innerHTML += `
                        <img src="/lfgabc/uploads/${p.filename}"
                             class="w-20 h-20 object-cover rounded border shadow" />
                    `;
                });
            } else {
                photosDiv.innerHTML = `<p class="text-gray-500 text-sm">No photos uploaded.</p>`;
            }

            // Map
            setTimeout(() => {
                if (mapInstance) mapInstance.remove();

                if (!report.latitud || !report.longhitud) {
                    document.getElementById("reportMap").innerHTML =
                        `<p class="text-center text-gray-500 mt-10">No location available.</p>`;
                    return;
                }

                mapInstance = L.map("reportMap").setView(
                    [report.latitud, report.longhitud],
                    16
                );

                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                    maxZoom: 19
                }).addTo(mapInstance);

                L.marker([report.latitud, report.longhitud]).addTo(mapInstance);
            }, 200);

            modal.classList.remove("hidden");
        });
    });

    closeModal.addEventListener("click", () => {
        modal.classList.add("hidden");
    });

});
</script>

