<?php
// lfgabc/dashboard/reports/add.php
include '../../layouts/head.php'; // header / session / $conn available
$ReportsBase = 'dashboard/reports';

// Only admin uses this Add page (community users use report_incident.php)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Fetch dropdown data
$barangays = $conn->query("SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name ASC");
$animals = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");

// Helper: sanitize filename piece
function slugify($text) {
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  if (empty($text)) return 'user';
  return $text;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type_of_bite'])) {
  $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
  $type_of_bite = trim($_POST['type_of_bite']);
  $animal_id = !empty($_POST['animal_id']) ? intval($_POST['animal_id']) : null;
  $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
  $barangay_id = !empty($_POST['barangay_id']) ? intval($_POST['barangay_id']) : null;
  $description = trim($_POST['description'] ?? '');
  $status = trim($_POST['status'] ?? 'Reported');
  $latitud = trim($_POST['latitude'] ?? '');
  $longhitud = trim($_POST['longitude'] ?? '');

  // NEW: Custom Date Reported
  $date_reported = !empty($_POST['date_reported'])
      ? $_POST['date_reported']
      : date('Y-m-d');

  if (empty($user_id) || empty($type_of_bite) || empty($animal_id) || empty($category_id) || empty($barangay_id)) {
    $error_message = "Please fill all required fields (user, type, animal, category, barangay).";
  } else {
    try {
      // Insert into reports
      $stmt = $conn->prepare("
        INSERT INTO reports (user_id, type_of_bite, barangay_id, latitud, longhitud, status, biting_animal_id, category_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->bind_param("isisssii", $user_id, $type_of_bite, $barangay_id, $latitud, $longhitud, $status, $animal_id, $category_id);
      $stmt->execute();
      $report_id = $stmt->insert_id;
      $stmt->close();

      // Insert into bites with selected date
      $stmt = $conn->prepare("
        INSERT INTO bites (patient_id, report_id, date_reported, description, barangay_id) 
        VALUES (0, ?, ?, ?, ?)
      ");
      $stmt->bind_param("isss", $report_id, $date_reported, $description, $barangay_id);
      $stmt->execute();
      $stmt->close();

      // Handle photos upload (optional)
      if (!empty($_FILES['photos']['name'][0])) {
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Get reporter fullname for filename
        $uStmt = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) AS fullname FROM users WHERE user_id = ?");
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $reporterFull = $uRow['fullname'] ?? 'reporter';
        $reporterSlug = slugify($reporterFull);
        $dateTag = date('YmdHis');

        $counter = 1;
        for ($i = 0; $i < count($_FILES['photos']['tmp_name']); $i++) {
          if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

          $tmpName = $_FILES['photos']['tmp_name'][$i];
          $origName = $_FILES['photos']['name'][$i];
          $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
          $allowed = ['jpg','jpeg','png','gif','webp'];
          if (!in_array($ext, $allowed)) continue;

          $fileName = "{$reporterSlug}-{$dateTag}-{$counter}.{$ext}";
          $targetPath = $uploadDir . $fileName;

          if (move_uploaded_file($tmpName, $targetPath)) {
            try {
              $pstmt = $conn->prepare("INSERT INTO photos (report_id, filename) VALUES (?, ?)");
              $pstmt->bind_param('is', $report_id, $fileName);
              $pstmt->execute();
              $pstmt->close();
            } catch (Exception $e) {
              try {
                $pstmt = $conn->prepare("INSERT INTO report_photos (report_id, file_name) VALUES (?, ?)");
                $pstmt->bind_param('is', $report_id, $fileName);
                $pstmt->execute();
                $pstmt->close();
              } catch (Exception $e2) {}
            }
            $counter++;
          }
        }
      }

      $success_message = "✅ Bite report created successfully!";

    } catch (mysqli_sql_exception $e) {
      $error_message = "❌ Database error: " . $e->getMessage();
    }
  }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add New Report</h5>
        <a href="<?= $ReportsBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <?php if ($error_message): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded-lg flex items-center">
            <i class="ti ti-alert-triangle mr-2"></i> <?= htmlspecialchars($error_message) ?>
          </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded-lg flex items-center">
            <i class="ti ti-check mr-2"></i> <?= htmlspecialchars($success_message) ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4 relative">

          <!-- User Autocomplete -->
          <div class="relative">
            <label class="form-label">Select User (reporter)</label>
            <input type="text" id="userSearch" class="form-control" placeholder="Search user..." autocomplete="off" required>
            <input type="hidden" name="user_id" id="userId">
            <ul id="userSuggestions" class="hidden absolute z-50 bg-white border border-gray-200 rounded-md w-full shadow-lg max-h-60 overflow-y-auto"></ul>
          </div>

          <div>
            <label class="form-label">Phone Number</label>
            <input type="text" id="phoneDisplay" class="form-control bg-gray-100" readonly>
          </div>

          <div>
            <label class="form-label">Type of Bite</label>
            <select name="type_of_bite" class="form-control" required>
              <option value="">Select Type</option>
              <option value="Bite">Bite</option>
              <option value="Scratch">Scratch</option>
            </select>
          </div>

          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Animal Type</label>
              <select name="animal_id" class="form-control" required>
                <option value="">Select animal</option>
                <?php while ($row = $animals->fetch_assoc()): ?>
                  <option value="<?= intval($row['biting_animal_id']) ?>"><?= htmlspecialchars($row['animal_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-control" required>
                <option value="">Select category</option>
                <?php while ($c = $categories->fetch_assoc()): ?>
                  <option value="<?= intval($c['category_id']) ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div>
            <label class="form-label">Barangay</label>
            <select name="barangay_id" class="form-control" required>
              <option value="">Select barangay</option>
              <?php while ($b = $barangays->fetch_assoc()): ?>
                <option value="<?= intval($b['barangay_id']) ?>"><?= htmlspecialchars($b['barangay_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
              <option value="Reported">Reported</option>
              <option value="Contacted">Contacted</option>
              <option value="Admitted">Admitted</option>
              <option value="Treated">Treated</option>
              <option value="Archived">Archived</option>
            </select>
          </div>

          <!-- NEW: DATE REPORTED FIELD -->
          <div>
            <label class="form-label">Date Reported</label>
            <input type="date" name="date_reported" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>

          <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="4" class="form-control" placeholder="Describe the incident..." required></textarea>
          </div>

          <div>
            <label class="form-label">Pin Location</label>
            <div id="map" style="height: 400px; border-radius: 10px;"></div>
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
          </div>

          <div>
            <label class="form-label">Upload Proof Photos (optional)</label>
            <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
            <small class="text-muted">You can take photos with your camera or select from gallery.</small>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-success">Submit Report</button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {

  // =============================
  // USER AUTOCOMPLETE
  // =============================
  const searchInput = document.getElementById('userSearch');
  const suggestions = document.getElementById('userSuggestions');
  const userIdField = document.getElementById('userId');
  const phoneDisplay = document.getElementById('phoneDisplay');

  searchInput.addEventListener('input', async function() {
    const q = this.value.trim();
    if (q.length < 2) {
      suggestions.classList.add('hidden');
      return;
    }

    try {
      const res = await fetch('<?= $ReportsBase ?>/search_user.php?q=' + encodeURIComponent(q));
      const users = await res.json();
      suggestions.innerHTML = '';

      if (users.length > 0) {
        users.forEach(u => {
          const li = document.createElement('li');
          li.textContent = `${u.fullname} (${u.phone_number || 'N/A'})`;
          li.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
          li.dataset.id = u.user_id;
          li.dataset.phone = u.phone_number || '';
          li.addEventListener('click', () => {
            searchInput.value = u.fullname;
            userIdField.value = u.user_id;
            phoneDisplay.value = u.phone_number || '';
            suggestions.classList.add('hidden');
          });
          suggestions.appendChild(li);
        });
        suggestions.classList.remove('hidden');
      } else {
        suggestions.innerHTML = '<li class="px-4 py-2 text-gray-500">No users found</li>';
        suggestions.classList.remove('hidden');
      }
    } catch (err) {
      console.error('Autocomplete error:', err);
      suggestions.classList.add('hidden');
    }
  });

  document.addEventListener('click', (e) => {
    if (!suggestions.contains(e.target) && e.target !== searchInput) {
      suggestions.classList.add('hidden');
    }
  });

  // =============================
  // MAP PICKER
  // =============================
  const defaultLat = 13.964;
  const defaultLng = 121.527;

  let map = L.map('map').setView([defaultLat, defaultLng], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  let marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

  document.getElementById('latitude').value = defaultLat;
  document.getElementById('longitude').value = defaultLng;

  marker.on('dragend', function() {
    const pos = marker.getLatLng();
    document.getElementById('latitude').value = pos.lat;
    document.getElementById('longitude').value = pos.lng;
  });

  // Try geolocation
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      map.setView([lat, lng], 15);
      marker.setLatLng([lat, lng]);

      document.getElementById('latitude').value = lat;
      document.getElementById('longitude').value = lng;
    });
  }
});
</script>
