<?php
include '../../layouts/head.php';

// Base path
$ReportsBase = 'dashoard/reports';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../pages/login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$success_message = '';
$error_message = '';

// Fetch users for admin dropdown
$users = [];
if ($user_role === 'admin') {
  $users = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) AS fullname, phone_number FROM users ORDER BY first_name ASC");
}

// If non-admin, fetch their phone
$stmt = $conn->prepare("SELECT phone_number FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$phone_number = $user_data['phone_number'] ?? '';

// Get barangays
$barangays = $conn->query("SELECT * FROM barangay ORDER BY barangay_name ASC");

// Handle submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['type_of_bite'])) {
  $report_user_id = $user_role === 'admin' ? $_POST['user_id'] : $user_id;
  $type_of_bite = $_POST['type_of_bite'];
  $barangay_id = $_POST['barangay_id'];
  $description = $_POST['description'];
  $latitud = $_POST['latitude'] ?? '';
  $longhitud = $_POST['longitude'] ?? '';

  try {
    // Insert into reports
    $stmt = $conn->prepare("INSERT INTO reports (user_id, type_of_bite, barangay_id, latitud, longhitud) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $report_user_id, $type_of_bite, $barangay_id, $latitud, $longhitud);
    $stmt->execute();
    $report_id = $stmt->insert_id;
    $stmt->close();

    // Insert into bites
    $stmt = $conn->prepare("INSERT INTO bites (patient_id, report_id, date_reported, description, barangay_id) VALUES (0, ?, NOW(), ?, ?)");
    $stmt->bind_param("isi", $report_id, $description, $barangay_id);
    $stmt->execute();
    $stmt->close();

    $success_message = "✅ Bite report submitted successfully!";
  } catch (mysqli_sql_exception $e) {
    $error_message = "❌ Database error occurred while saving your report. Please try again.";
  }
}
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Report an Animal Bite</h5>
      </div>

      <div class="card-body p-5">
        <!-- Alerts -->
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

        <form method="POST" id="reportForm" class="space-y-4">
          <!-- User Selection -->
          <?php if ($user_role === 'admin'): ?>
            <div>
              <label class="form-label">Select User</label>
              <select name="user_id" id="userSelect" class="form-control" required>
                <option value="">Select user</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                  <option value="<?= $u['user_id'] ?>" data-phone="<?= htmlspecialchars($u['phone_number']) ?>">
                    <?= htmlspecialchars($u['fullname']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Phone Number</label>
              <input type="text" id="phoneDisplay" class="form-control bg-gray-100" readonly>
            </div>
          <?php else: ?>
            <div>
              <label class="form-label">Phone Number</label>
              <input type="text" class="form-control bg-gray-100" value="<?= htmlspecialchars($phone_number ?: 'N/A') ?>" readonly>
            </div>
          <?php endif; ?>

          <!-- Type of Bite -->
          <div>
            <label class="form-label">Type of Bite</label>
            <select name="type_of_bite" class="form-control" required>
              <option value="">Select Type</option>
              <option value="dog">Dog</option>
              <option value="cat">Cat</option>
            </select>
          </div>

          <!-- Barangay -->
          <div>
            <label class="form-label">Barangay</label>
            <select name="barangay_id" class="form-control" required>
              <?php while ($row = $barangays->fetch_assoc()): ?>
                <option value="<?= $row['barangay_id'] ?>"><?= htmlspecialchars($row['barangay_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label class="form-label">Description of Incident</label>
            <textarea name="description" rows="4" class="form-control" placeholder="Describe what happened..." required></textarea>
          </div>

          <!-- Map -->
          <div>
            <label class="form-label">Pin Location</label>
            <div id="map" style="height: 400px; border-radius: 10px;"></div>
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
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

<!-- 🗺️ Leaflet Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // Autofill phone on admin user select
  const userSelect = document.getElementById('userSelect');
  const phoneDisplay = document.getElementById('phoneDisplay');
  if (userSelect) {
    userSelect.addEventListener('change', function() {
      const phone = this.selectedOptions[0].getAttribute('data-phone') || '';
      phoneDisplay.value = phone || 'N/A';
    });
  }

  // Initialize map
  let map = L.map('map').setView([13.964, 121.527], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  let marker = L.marker([13.964, 121.527], { draggable: true }).addTo(map);

  // Update hidden fields on drag
  marker.on('dragend', function() {
    const pos = marker.getLatLng();
    document.getElementById('latitude').value = pos.lat;
    document.getElementById('longitude').value = pos.lng;
  });

  // Try to get user's location
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      map.setView([lat, lng], 15);
      marker.setLatLng([lat, lng]);
      document.getElementById('latitude').value = lat;
      document.getElementById('longitude').value = lng;
    }, function() {
      console.warn('⚠️ Location access denied.');
    });
  }
});
</script>
