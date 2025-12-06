<?php
// dashboard/patients/index.php
include '../../layouts/head.php';
$PatientsBase = 'dashboard/patients';

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
        <h5>Patients</h5>
        <div class="flex gap-2">
            <a href="<?= $PatientsBase ?>/export_excel.php" class="btn btn-outline-success">Export Excel</a>
            <a href="<?= $PatientsBase ?>/add.php" class="btn btn-primary">Add Patient</a>
        </div>
      </div>

      <div class="card-body p-5">
        
        <!-- Filters -->
        <div class="grid grid-cols-12 gap-3 mb-4 p-4 bg-gray-50 rounded-lg">

          <div class="col-span-12 md:col-span-4">
            <input type="text" id="filterSearch" class="form-control"
              placeholder="Search name / email / phone / address">
          </div>

          <div class="col-span-12 md:col-span-2">
            <select id="filterAnimal" class="form-control">
              <option value="">All Animal Types</option>
              <?php
                $res = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
                while ($a = $res->fetch_assoc()) {
                    echo '<option value="'.$a['biting_animal_id'].'">'.$a['animal_name'].'</option>';
                }
              ?>
            </select>
          </div>

          <div class="col-span-12 md:col-span-2">
            <select id="filterCategory" class="form-control">
              <option value="">All Categories</option>
              <?php
                $res = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
                while ($c = $res->fetch_assoc()) {
                    echo '<option value="'.$c['category_id'].'">'.$c['category_name'].'</option>';
                }
              ?>
            </select>
          </div>

          <div class="col-span-12 md:col-span-2">
            <select id="filterVaccine" class="form-control">
              <option value="">All Vaccines</option>
              <?php
                $res = $conn->query("SELECT anti_ravies_vaccine_id, brand_name, generic_name 
                                     FROM anti_ravies_vaccine ORDER BY brand_name ASC");
                while ($v = $res->fetch_assoc()) {
                    echo '<option value="'.$v['anti_ravies_vaccine_id'].'">'.$v['brand_name'].' / '.$v['generic_name'].'</option>';
                }
              ?>
            </select>
          </div>

          <div class="col-span-12 md:col-span-2 flex gap-2">
            <button id="clearFilters" class="btn btn-outline-secondary w-full">Clear</button>
            <button id="applyFilters" class="btn btn-primary w-full">Apply</button>
          </div>

        </div>

        <!-- TABLE -->
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="patientsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Patient Name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Animal Type</th>
                <th>Bite Type</th>
                <th>Category</th>
                <th>Vaccine</th>
                <th>Report</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="patientsTbody"></tbody>
          </table>

          <div id="noResults" class="text-center text-gray-500 py-4 hidden">No results found.</div>

          <div class="text-center mt-4">
            <button id="loadMoreBtn" class="btn btn-outline-primary px-5">Load More</button>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const assetBase = <?= json_encode($assetBase) ?>;
  const PatientsBase = assetBase + "/dashboard/patients";

  const perPage = 10;
  let page = 1;
  let loading = false;
  let exhausted = false;

  const tbody = document.getElementById("patientsTbody");
  const loadMoreBtn = document.getElementById("loadMoreBtn");
  const noResults = document.getElementById("noResults");

  const searchEl = document.getElementById("filterSearch");
  const animalEl = document.getElementById("filterAnimal");
  const categoryEl = document.getElementById("filterCategory");
  const vaccineEl = document.getElementById("filterVaccine");
  const clearBtn = document.getElementById("clearFilters");

  function getFilters() {
    return {
      q: searchEl.value.trim(),
      animal: animalEl.value,
      category: categoryEl.value,
      vaccine: vaccineEl.value
    };
  }

  function escapeHtml(s) {
    if (!s) return "";
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function renderRow(p) {
    const vaccine = p.vaccine_brand 
      ? `${p.vaccine_brand}${p.vaccine_generic ? ' / '+p.vaccine_generic : ''}`
      : '';

    const linked = p.report_id > 0
      ? `<span class="inline-block bg-gray-100 px-2 py-1 rounded text-sm">#${p.report_id}</span>`
      : "";

    return `
      <tr>
        <td>${p.patient_id}</td>
        <td>
          ${escapeHtml(p.first_name + " " + p.last_name)}
          <div class="text-xs text-gray-500">${escapeHtml(p.email)} • ${escapeHtml(p.phone_number)}</div>
        </td>
        <td>${escapeHtml(p.age)}</td>
        <td>${escapeHtml(p.gender)}</td>
        <td>${escapeHtml(p.animal_name)}</td>
        <td>${escapeHtml(p.type_of_bite)}</td>
        <td>${escapeHtml(p.category_name)}</td>
        <td>${escapeHtml(vaccine)}</td>
        <td>${linked}</td>
        <td class="flex gap-2">
          <a href="${PatientsBase}/view.php?id=${p.patient_id}" class="btn btn-sm btn-outline-info">View</a>
          <a href="${PatientsBase}/edit.php?id=${p.patient_id}" class="btn btn-sm btn-outline-primary">Edit</a>
          <button class="btn btn-sm btn-outline-success notify-btn" data-id="${p.patient_id}">Notify</button>
          <a href="${PatientsBase}/schedule.php?id=${p.patient_id}" class="btn btn-sm btn-outline-warning">Vaccination</a>
          <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${p.patient_id}">Delete</button>
        </td>
      </tr>
    `;
  }

  async function loadPatients(reset = false) {
    if (loading) return;

    if (reset) {
      page = 1;
      exhausted = false;
      tbody.innerHTML = "";
      noResults.classList.add("hidden");
      loadMoreBtn.classList.remove("hidden");
    }

    if (exhausted) return;

    loading = true;
    loadMoreBtn.disabled = true;
    loadMoreBtn.textContent = "Loading...";

    const f = getFilters();

    const url = PatientsBase + "/load_patients.php?" + new URLSearchParams({
      page,
      per_page: perPage,
      q: f.q,
      animal: f.animal,
      category: f.category,
      vaccine: f.vaccine
    }).toString();

    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json();

      if (data.rows.length === 0 && page === 1) {
        noResults.classList.remove("hidden");
        loadMoreBtn.classList.add("hidden");
        exhausted = true;
      } else {
        data.rows.forEach(r => tbody.insertAdjacentHTML("beforeend", renderRow(r)));

        if (data.rows.length < perPage) {
          exhausted = true;
          loadMoreBtn.classList.add("hidden");
        } else {
          page++;
        }
      }

    } catch (err) {
      console.error("Load error:", err);
    }

    loadMoreBtn.disabled = false;
    loadMoreBtn.textContent = "Load More";

    attachDeleteEvents();
    attachNotifyEvents();
    loading = false;
  }

  // -----------------------------
  // NOTIFY BUTTON HANDLER (FIXED)
  // -----------------------------
  function attachNotifyEvents() {
    document.querySelectorAll(".notify-btn").forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;

        if (!confirm("Send schedule notification to patient #" + id + "?")) return;

        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Sending...";

        try {
          const res = await fetch(PatientsBase + "/notify.php", {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: "patient_id=" + id
          });

          const text = await res.text();
          let data;

          try {
            data = JSON.parse(text);
          } catch (e) {
            console.error("Bad JSON:", text);
            alert("Notification failed. (Invalid JSON)");
            btn.disabled = false;
            btn.textContent = original;
            return;
          }

          if (data.success) {
            // TOAST
            const n = document.createElement('div');
            n.className = 'fixed bottom-5 right-5 bg-green-600 text-white px-4 py-2 rounded shadow-lg z-[9999]';
            n.textContent = data.message || "Email sent!";
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 2000);

            // BUTTON ANIMATION
            btn.textContent = "Sent!";
            btn.classList.remove("btn-outline-success");
            btn.classList.add("btn-success");

            setTimeout(() => {
              btn.textContent = original;
              btn.disabled = false;
              btn.classList.remove("btn-success");
              btn.classList.add("btn-outline-success");
            }, 1500);

          } else {
            alert(data.message || "Failed to send notification");
            btn.disabled = false;
            btn.textContent = original;
          }

        } catch (err) {
          console.error(err);
          alert("Notification failed.");
          btn.disabled = false;
          btn.textContent = original;
        }
      };
    });
  }

  function attachDeleteEvents() {
    document.querySelectorAll(".delete-btn").forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;

        if (!confirm("Delete patient #" + id + "?")) return;

        const res = await fetch(PatientsBase + "/delete.php", {
          method: "POST",
          credentials: 'same-origin',
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "patient_id=" + id
        });

        const data = await res.json();
        if (data.success) loadPatients(true);
        else alert("Delete failed");
      };
    });
  }

  // AUTO FILTER
  searchEl.addEventListener('input', () => loadPatients(true));
  animalEl.addEventListener('change', () => loadPatients(true));
  categoryEl.addEventListener('change', () => loadPatients(true));
  vaccineEl.addEventListener('change', () => loadPatients(true));

  clearBtn.addEventListener('click', () => {
    searchEl.value = "";
    animalEl.value = "";
    categoryEl.value = "";
    vaccineEl.value = "";
    loadPatients(true);
  });

  // Export to Excel - placed inside DOMContentLoaded so searchEl etc. exist
  const exportBtn = document.getElementById("exportExcelBtn");
  if (exportBtn) {
    exportBtn.addEventListener("click", () => {
      const params = new URLSearchParams({
        q: searchEl.value.trim(),
        animal: animalEl.value,
        category: categoryEl.value,
        vaccine: vaccineEl.value
      });
      // start download (will navigate to export script)
      window.location.href = PatientsBase + "/export_excel.php?" + params.toString();
    });
  }

  loadMoreBtn.addEventListener('click', () => loadPatients(false));

  loadPatients(true);

});
// -----------------------------
// EXPORT TO EXCEL
// -----------------------------
document.getElementById("exportExcelBtn").addEventListener("click", () => {

    const params = new URLSearchParams({
        q: searchEl.value.trim(),
        animal: animalEl.value,
        category: categoryEl.value,
        vaccine: vaccineEl.value
    });

    window.location.href = PatientsBase + "/export_excel.php?" + params.toString();
});
</script>


