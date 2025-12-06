<?php
// ======================================================================
// edit.php - Community User Report Editor
// Location: /dashboard/community/reports/edit.php
// ======================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------------------------
// ACCESS CONTROL
// ----------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../pages/login.php');
    exit;
}

include '../../../assets/db/db.php';

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, ['community_user', 'admin', 'staff'])) {
    header('Location: ../../../pages/login.php');
    exit;
}

$ReportsBase = '/dashboard/community/reports';

// ----------------------------------------------------------------------
// GET REPORT ID
// ----------------------------------------------------------------------
$report_id = intval($_GET['id'] ?? 0);
if ($report_id <= 0) die("Invalid report ID.");

// ----------------------------------------------------------------------
// FETCH REPORT
// ----------------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT r.*, b.date_reported, b.description AS bite_description
    FROM reports r
    LEFT JOIN bites b ON b.report_id = r.report_id
    WHERE r.report_id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) die("Report not found.");

// Community user can only edit their own
if ($user_role === 'community_user' && $report['user_id'] != $user_id) {
    die("Unauthorized.");
}

// ----------------------------------------------------------------------
// SELECT LISTS
// ----------------------------------------------------------------------
$barangays  = $conn->query("SELECT * FROM barangay ORDER BY barangay_name ASC");
$animals    = $conn->query("SELECT * FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT * FROM category ORDER BY category_id ASC");

// ----------------------------------------------------------------------
// EXISTING PHOTOS
// ----------------------------------------------------------------------
$stmt = $conn->prepare("SELECT photo_id, filename FROM photos WHERE report_id = ?");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error_message = '';
$success_message = '';

// ----------------------------------------------------------------------
// HANDLE POST UPDATE
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $type_of_bite = trim($_POST['type_of_bite']);
        $biting_animal_id = intval($_POST['biting_animal_id']);
        $category_id = intval($_POST['category_id']);
        $barangay_id = intval($_POST['barangay_id']);
        $description = trim($_POST['description']);
        $lat = trim($_POST['latitude']);
        $lng = trim($_POST['longitude']);

        if ($type_of_bite === '' || $biting_animal_id <= 0 || $category_id <= 0 || $barangay_id <= 0) {
            throw new Exception("Please complete all required fields.");
        }

        $conn->begin_transaction();

        // Update report
        $stmt = $conn->prepare("
            UPDATE reports 
            SET type_of_bite=?, category_id=?, biting_animal_id=?, barangay_id=?, longhitud=?, latitud=?
            WHERE report_id=?
        ");
        $stmt->bind_param('siiissi', $type_of_bite, $category_id, $biting_animal_id, $barangay_id, $lng, $lat, $report_id);
        $stmt->execute();
        $stmt->close();

        // Update bites
        $stmt = $conn->prepare("
            UPDATE bites SET description=?, barangay_id=?, date_reported = NOW()
            WHERE report_id=?
        ");
        $stmt->bind_param('sii', $description, $barangay_id, $report_id);
        $stmt->execute();
        $stmt->close();

        // COUNT EXISTING PHOTOS
        $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM photos WHERE report_id = ?");
        $countStmt->bind_param('i', $report_id);
        $countStmt->execute();
        $existingCount = $countStmt->get_result()->fetch_assoc()['cnt'];
        $countStmt->close();

        // NEW PHOTOS
        $newPhotos = 0;
        if (!empty($_FILES['photos']['name'])) {
            foreach ($_FILES['photos']['name'] as $n) {
                if ($n !== '') $newPhotos++;
            }
        }

        if ($existingCount + $newPhotos > 4) {
            throw new Exception("Maximum 4 photos allowed. Remove some existing photos.");
        }

        // Upload new photos
        if ($newPhotos > 0) {
            $uploadDir = __DIR__ . "/../../../uploads/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $prefix = "report-{$report_id}-" . date('YmdHis');
            $idx = 0;

            for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
                if ($_FILES['photos']['name'][$i] === '') continue;

                $idx++;
                $tmp = $_FILES['photos']['tmp_name'][$i];
                $orig = $_FILES['photos']['name'][$i];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    throw new Exception("Invalid file type: " . $orig);
                }

                $new = "{$prefix}-{$idx}.{$ext}";
                $dest = $uploadDir . $new;

                if (!move_uploaded_file($tmp, $dest)) {
                    throw new Exception("Failed uploading file: " . $orig);
                }

                $stmt = $conn->prepare("INSERT INTO photos (report_id, filename) VALUES (?,?)");
                $stmt->bind_param('is', $report_id, $new);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        header("Location: $ReportsBase/edit.php?id=$report_id&updated=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: $ReportsBase/edit.php?id=$report_id&error=" . urlencode($e->getMessage()));
        exit;
    }
}

function h($v) { return htmlspecialchars($v ?? ''); }

include '../../../layouts/head.php';
?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">

    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Report #<?= h($report_id) ?></h5>
        <a href="<?= $ReportsBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <!-- SUCCESS & ERROR BADGES -->
        <?php if (isset($_GET['updated'])): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded">
            Report updated successfully.
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded">
            <?= h($_GET['error']) ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

          <div class="grid grid-cols-12 gap-3">

            <div class="col-span-6">
              <label class="form-label">Type of Injury</label>
              <select name="type_of_bite" class="form-control" required>
                <option value="">Select</option>
                <option value="Bite"    <?= $report['type_of_bite']=='Bite'?'selected':'' ?>>Bite</option>
                <option value="Scratch" <?= $report['type_of_bite']=='Scratch'?'selected':'' ?>>Scratch</option>
              </select>
            </div>

            <div class="col-span-6">
              <label class="form-label">Animal</label>
              <select name="biting_animal_id" class="form-control" required>
                <option value="">Select animal</option>
                <?php $animals->data_seek(0); while($a=$animals->fetch_assoc()): ?>
                    <option value="<?= $a['biting_animal_id'] ?>" <?= $a['biting_animal_id']==$report['biting_animal_id']?'selected':'' ?>>
                        <?= h($a['animal_name']) ?>
                    </option>
                <?php endwhile ?>
              </select>
            </div>

            <div class="col-span-6">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-control" required>
                <option value="">Select</option>
                <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
                    <option value="<?= $c['category_id'] ?>" <?= $c['category_id']==$report['category_id']?'selected':'' ?>>
                        <?= h($c['category_name']) ?>
                    </option>
                <?php endwhile ?>
              </select>
            </div>

            <div class="col-span-6">
              <label class="form-label">Barangay</label>
              <select name="barangay_id" class="form-control" required>
                <option value="">Select</option>
                <?php $barangays->data_seek(0); while($b=$barangays->fetch_assoc()): ?>
                    <option value="<?= $b['barangay_id'] ?>" <?= $b['barangay_id']==$report['barangay_id']?'selected':'' ?>>
                        <?= h($b['barangay_name']) ?>
                    </option>
                <?php endwhile ?>
              </select>
            </div>

            <div class="col-span-12">
              <label class="form-label">Description</label>
              <textarea name="description" rows="4" class="form-control" required><?= h($report['bite_description']) ?></textarea>
            </div>

          </div>

          <!-- MAP -->
          <div class="mt-4">
            <label class="form-label">Pin Location (drag marker)</label>
            <div id="map" style="height: 350px; border-radius: 8px;"></div>
            <input type="hidden" name="latitude" id="latitude" value="<?= h($report['latitud']) ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= h($report['longhitud']) ?>">
          </div>

          <!-- EXISTING PHOTOS -->
          <div class="mt-4">
            <label class="form-label">Existing Photos (click ✖ to remove)</label>
            <div class="flex flex-wrap gap-3">
              <?php foreach($photos as $p): ?>
                <div id="photo-<?= $p['photo_id'] ?>" class="relative">
                  <img src="/uploads/<?= h($p['filename']) ?>" class="w-40 h-40 object-cover rounded shadow">
                  <button data-id="<?= $p['photo_id'] ?>" class="delete-photo absolute top-1 right-1 bg-white rounded-full px-2 py-1">✖</button>
                </div>
              <?php endforeach ?>
            </div>
          </div>

          <!-- NEW PHOTOS -->
          <div class="mt-4">
            <label class="form-label">Upload New Photos (max 4 total)</label>
            <input type="file" name="photos[]" accept="image/*" multiple class="form-control">
          </div>

          <button class="btn btn-success mt-4">Save Changes</button>

        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../../layouts/footer-block.php'; ?>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
// MAP INITIALIZE
document.addEventListener('DOMContentLoaded', function() {

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const lat = parseFloat(latInput.value) || 13.964;
    const lng = parseFloat(lngInput.value) || 121.527;

    const map = L.map('map').setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    const marker = L.marker([lat, lng], {draggable:true}).addTo(map);

    marker.on('dragend', function() {
        const pos = marker.getLatLng();
        latInput.value = pos.lat;
        lngInput.value = pos.lng;
    });

    // AJAX PHOTO DELETE
    document.querySelectorAll('.delete-photo').forEach(btn => {
        btn.addEventListener('click', async(e) => {
            e.preventDefault();
            const id = btn.dataset.id;

            if (!confirm("Delete this photo?")) return;

            const res = await fetch("delete_photo.php", {
                method:"POST",
                headers: {"Content-Type":"application/json"},
                body: JSON.stringify({ photo_id: id, report_id: <?= $report_id ?> })
            });

            const data = await res.json();
            if (data.success) {
                document.getElementById('photo-'+id).remove();
            } else {
                alert("Failed: " + data.message);
            }
        });
    });

});
</script>
