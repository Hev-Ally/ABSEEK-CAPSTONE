<?php
// report-incident.php (community user two-step soft wizard)
// Place under: /dashboard/reports/report-incident.php
// Assumes ../../../layouts/head.php sets up $conn (mysqli), session_start(), $assetBase etc.

include '../../../layouts/head.php';

// --- Access control: must be logged in ---
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../../pages/login.php');
  exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Only community users (or admin/staff allowed depending on your policy)
if ($user_role !== 'community_user' && $user_role !== 'admin' && $user_role !== 'staff') {
  header('Location: ../../../pages/login.php');
  exit;
}

// --- Handle full report submission (normal POST) ---
$success_message = '';
$error_message = '';

// Fetch lists needed for the form
$barangays = $conn->query("SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name ASC");
$animals = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_id ASC");

// get current user's phone + name (fresh from DB for this request)
$stmt = $conn->prepare("SELECT first_name, last_name, phone_number FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fullname = trim(($user_row['first_name'] ?? '') . ' ' . ($user_row['last_name'] ?? ''));
$phone_number = $user_row['phone_number'] ?? '';

// Final report submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  $type_of_bite = $_POST['type_of_bite'] ?? '';
  $biting_animal_id = (int)($_POST['biting_animal_id'] ?? 0);
  $barangay_id = (int)($_POST['barangay_id'] ?? 0);
  $category_id = (int)($_POST['category_id'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $lat = trim($_POST['latitude'] ?? '');
  $lng = trim($_POST['longitude'] ?? '');
  $report_user_id = $user_id; // community user reports their own

  // basic validation
  if ($type_of_bite === '' || $biting_animal_id <= 0 || $barangay_id <= 0 || $category_id <= 0) {
    $error_message = 'Please complete all required fields (type, animal, barangay, category).';
  } elseif (empty($phone_number)) {
    $error_message = 'Please update your phone number first (go back to Step 1).';
  } else {
    try {
      $conn->begin_transaction();

      $stmt = $conn->prepare("INSERT INTO reports (user_id, type_of_bite, category_id, biting_animal_id, barangay_id, longhitud, latitud, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $status_default = 'Reported';
      $stmt->bind_param('isiiisss', $report_user_id, $type_of_bite, $category_id, $biting_animal_id, $barangay_id, $lng, $lat, $status_default);
      $stmt->execute();
      $report_id = $stmt->insert_id;
      $stmt->close();

      // Insert into bites table (patient_id = 0)
      $stmt = $conn->prepare("INSERT INTO bites (patient_id, report_id, date_reported, description, barangay_id) VALUES (0, ?, NOW(), ?, ?)");
      $stmt->bind_param('isi', $report_id, $description, $barangay_id);
      $stmt->execute();
      $stmt->close();

      // Handle photos
      if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $uploadedCount = count(array_filter($_FILES['photos']['name']));
        if ($uploadedCount > 4) {
          throw new Exception('You can upload up to 4 photos only.');
        }

        $uploadDir = __DIR__ . '/../../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $safeName = preg_replace('/[^a-z0-9\- ]/i', '', strtolower(str_replace(' ', '-', $fullname ?: 'reporter')));
        $nowStamp = date('YmdHis');
        $index = 0;

        for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
          if (empty($_FILES['photos']['name'][$i])) continue;
          $index++;
          $tmp = $_FILES['photos']['tmp_name'][$i];
          $origName = $_FILES['photos']['name'][$i];
          $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
          $allowed = ['jpg','jpeg','png','webp'];
          if (!in_array($ext, $allowed)) {
            throw new Exception('Invalid file type for photo: ' . htmlspecialchars($origName));
          }
          $newFilename = "{$safeName}-{$nowStamp}-{$index}.{$ext}";
          $destination = $uploadDir . $newFilename;

          if (!move_uploaded_file($tmp, $destination)) {
            throw new Exception('Failed to move uploaded file: ' . htmlspecialchars($origName));
          }

          // Insert to photos table
          $stmt = $conn->prepare("INSERT INTO photos (report_id, filename) VALUES (?, ?)");
          $stmt->bind_param('is', $report_id, $newFilename);
          $stmt->execute();
          $stmt->close();
        }
      }

      $conn->commit();
      $success_message = '✅ Bite report submitted successfully!';
      // after successful submit, refetch phone (optional)
      $stmt = $conn->prepare("SELECT phone_number FROM users WHERE user_id = ?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $urow = $stmt->get_result()->fetch_assoc();
      $phone_number = $urow['phone_number'] ?? $phone_number;
      $stmt->close();

    } catch (Exception $e) {
      $conn->rollback();
      $error_message = '❌ Error saving report: ' . $e->getMessage();
    }
  }
}
?>
<!-- === HTML / UI (unchanged semantics, with fixes) === -->
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Report an Incident — Step Form</h5>
        <a href="<?= $assetBase ?>/dashboard/community/reports/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">
        <?php if ($error_message): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded-lg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded-lg"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <!-- STEP NAV -->
        <div class="flex gap-3 mb-5">
          <div id="step1Badge" class="px-3 py-2 rounded bg-info text-white">Step 1 — Update Phone</div>
          <div id="step2Badge" class="px-3 py-2 rounded bg-gray-200 text-gray-700">Step 2 — Report Details</div>
        </div>

        <!-- STEP 1 -->
        <div id="step1">
          <form id="phoneForm" class="space-y-3" onsubmit="return false;">
            <div>
              <label class="form-label">Your Name</label>
              <input type="text" class="form-control bg-gray-100" value="<?= htmlspecialchars($fullname) ?>" readonly>
            </div>
            <div>
              <label class="form-label">Phone Number</label>
              <input type="text" id="new_phone" class="form-control" value="<?= htmlspecialchars($phone_number) ?>" placeholder="Enter phone number">
            </div>
            <div class="flex gap-3 mt-5">
              <button type="button" id="savePhoneBtn" class="btn btn-primary">Save & Continue</button>
              <button type="button" id="skipToStep2" class="btn btn-outline-secondary">Skip (if already set)</button>
            </div>
          </form>
        </div>

        <!-- STEP 2 (hidden until phone saved or skip) -->
        <div id="step2" class="hidden mt-6">
          <form id="reportForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">

            <div class="grid grid-cols-12 gap-3">
              <div class="col-span-12 md:col-span-6">
                <label class="form-label">Type of Injury</label>
                <select name="type_of_bite" class="form-control" required>
                  <option value="">Select</option>
                  <option value="Bite">Bite</option>
                  <option value="Scratch">Scratch</option>
                </select>
              </div>

              <div class="col-span-12 md:col-span-6">
                <label class="form-label">Animal Type</label>
                <select name="biting_animal_id" class="form-control" required>
                  <option value="">Select animal</option>
                  <?php while ($a = $animals->fetch_assoc()): ?>
                    <option value="<?= (int)$a['biting_animal_id'] ?>"><?= htmlspecialchars($a['animal_name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-span-12 md:col-span-6">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control" required>
                  <option value="">Select category</option>
                  <?php while ($c = $categories->fetch_assoc()): ?>
                    <option value="<?= (int)$c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-span-12 md:col-span-6">
                <label class="form-label">Barangay</label>
                <select name="barangay_id" class="form-control" required>
                  <option value="">Select barangay</option>
                  <?php while ($b = $barangays->fetch_assoc()): ?>
                    <option value="<?= (int)$b['barangay_id'] ?>"><?= htmlspecialchars($b['barangay_name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div>
              <label class="form-label">Description</label>
              <textarea name="description" rows="4" class="form-control" required placeholder="Describe the incident..."></textarea>
            </div>

            <div>
              <label class="form-label">Pin Location (drag marker)</label>
              <div id="map" style="height: 320px; border-radius: 8px;"></div>
            </div>

            <div>
              <label class="form-label">Photos (max 4) — take with mobile camera or upload</label>
              <input type="file" name="photos[]" accept="image/*" multiple class="form-control" id="photosInput">
              <div class="mt-2 text-sm text-gray-500">Allowed: jpg, jpeg, png, webp. Max 4 photos.</div>
              <div id="photoPreview" class="grid grid-cols-4 gap-2 mt-3"></div>
            </div>

            <div class="flex gap-3">
              <button type="submit" class="btn btn-success">Submit Report</button>
              <button type="button" id="backToStep1" class="btn btn-outline-secondary">Back to Step 1</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include '../../../layouts/footer-block.php'; ?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const savePhoneBtn = document.getElementById('savePhoneBtn');
  const skipToStep2 = document.getElementById('skipToStep2');
  const backToStep1 = document.getElementById('backToStep1');
  const newPhoneInput = document.getElementById('new_phone');
  const step1Badge = document.getElementById('step1Badge');
  const step2Badge = document.getElementById('step2Badge');

  function showStep2() {
    step1.classList.add('hidden');
    step2.classList.remove('hidden');

    step1Badge.classList.remove('bg-info', 'text-white');
    step1Badge.classList.add('bg-gray-200', 'text-gray-700');

    step2Badge.classList.remove('bg-gray-200', 'text-gray-700');
    step2Badge.classList.add('bg-info', 'text-white');

    if (typeof initReportMap === 'function') initReportMap();
  }

  function showStep1() {
    step2.classList.add('hidden');
    step1.classList.remove('hidden');

    step1Badge.classList.add('bg-info', 'text-white');
    step1Badge.classList.remove('bg-gray-200', 'text-gray-700');

    step2Badge.classList.remove('bg-info', 'text-white');
    step2Badge.classList.add('bg-gray-200', 'text-gray-700');
  }

  // If phone present already, go directly to step 2 (and hide step1)
  if (newPhoneInput && newPhoneInput.value.trim() !== '') {
      showStep2();
      setTimeout(() => {
          if (typeof initReportMap === 'function') initReportMap();
      }, 300);
  }

  // Save phone - AJAX to update_phone.php (clean JSON)
  savePhoneBtn.addEventListener('click', async () => {
    const val = newPhoneInput.value.trim();
    if (val === '') {
      alert('Phone number cannot be empty.');
      return;
    }

    savePhoneBtn.disabled = true;
    const originalText = savePhoneBtn.textContent;
    savePhoneBtn.textContent = 'Saving...';

    try {
      const body = new URLSearchParams();
      body.append('new_phone', val);

      const res = await fetch('<?= $assetBase ?>/dashboard/community/reports/update_phone.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });

      // try parse JSON, if not JSON show error
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error('Non-JSON response:', text);
        alert('Server error while updating phone. See console for response.');
        return;
      }

      if (data.success) {
        showStep2();
        // show small notice
        const n = document.createElement('div');
        n.className = 'mb-3 px-4 py-2 bg-success text-white rounded';
        n.textContent = 'Phone updated. You can now continue to Step 2.';
        step1.prepend(n);
        setTimeout(() => n.remove(), 3000);
      } else {
        alert(data.message || 'Failed to update phone.');
      }
    } catch (e) {
      console.error(e);
      alert('Connection error while updating phone.');
    } finally {
      savePhoneBtn.disabled = false;
      savePhoneBtn.textContent = originalText;
    }
  });

  skipToStep2.addEventListener('click', () => {
    if (newPhoneInput.value.trim() === '') {
      if (!confirm('Your phone is empty — you should add one. Continue anyway?')) return;
    }
    showStep2();
  });

  backToStep1.addEventListener('click', showStep1);

  // Photo preview + limit
  const photosInput = document.getElementById('photosInput');
  const previewArea = document.getElementById('photoPreview');
  photosInput && photosInput.addEventListener('change', () => {
    previewArea.innerHTML = '';
    const files = Array.from(photosInput.files || []);
    if (files.length > 4) {
      alert('Maximum 4 photos allowed.');
      photosInput.value = '';
      return;
    }
    files.forEach(f => {
      if (!f.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.style.width = '100%';
      img.style.height = '80px';
      img.style.objectFit = 'cover';
      img.className = 'rounded';
      const reader = new FileReader();
      reader.onload = (e) => img.src = e.target.result;
      reader.readAsDataURL(f);
      const wrap = document.createElement('div');
      wrap.appendChild(img);
      previewArea.appendChild(wrap);
    });
  });

  // Map (init on demand)
  window.reportMap = null;
  window.reportMarker = null;

  window.initReportMap = function () {
    if (window.reportMap) return;

    const defaultLat = 13.964;
    const defaultLng = 121.527;

    const map = L.map('map').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    function setHidden(lat, lng) {
      document.getElementById('latitude').value = lat;
      document.getElementById('longitude').value = lng;
    }

    marker.on('dragend', function () {
      const pos = marker.getLatLng();
      setHidden(pos.lat, pos.lng);
    });

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        map.setView([lat, lng], 15);
        marker.setLatLng([lat, lng]);
        setHidden(lat, lng);
      }, () => {
        setHidden(defaultLat, defaultLng);
      }, { timeout: 5000 });
    } else {
      setHidden(defaultLat, defaultLng);
    }

    window.reportMap = map;
    window.reportMarker = marker;
  };

  // Final submit: ensure phone exists client-side (but server will re-check)
  const reportForm = document.getElementById('reportForm');
  reportForm && reportForm.addEventListener('submit', (e) => {
    if (newPhoneInput.value.trim() === '') {
      e.preventDefault();
      alert('Please update your phone in Step 1 before submitting.');
      showStep1();
      return false;
    }
    // else submit the form normally (server-side will process)
  });
});
</script>
