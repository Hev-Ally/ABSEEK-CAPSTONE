<?php
// dashboard/reports/edit.php
include '../../layouts/head.php';
$ReportsBase = 'dashboard/reports';

// Only admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

$uploadsDir  = __DIR__ . '/../../uploads/';
$uploadsBase = '/uploads'; // Adjust based on your server path
$maxPhotos   = 4;

function safe($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
function slugify_name($first, $last) {
  $name = trim("{$first}-{$last}");
  $name = preg_replace('/\s+/', '-', strtolower($name));
  return preg_replace('/[^a-z0-9\-\_]/', '', $name);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('Invalid ID');
$report_id = intval($_GET['id']);

// Fetch report + bites.date_reported
$stmt = $conn->prepare("
  SELECT r.*, 
         CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
         u.first_name AS reporter_first,
         u.last_name AS reporter_last,
         u.phone_number AS reporter_phone,
         b.description AS bite_description,
         b.date_reported,
         ba.biting_animal_id,
         ba.animal_name,
         c.category_id,
         c.category_name,
         br.barangay_id,
         br.barangay_name
  FROM reports r
  LEFT JOIN users u ON r.user_id = u.user_id
  LEFT JOIN bites b ON r.report_id = b.report_id
  LEFT JOIN biting_animal ba ON r.biting_animal_id = ba.biting_animal_id
  LEFT JOIN category c ON r.category_id = c.category_id
  LEFT JOIN barangay br ON r.barangay_id = br.barangay_id
  WHERE r.report_id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) die("Report not found");

$photos = [];
$p = $conn->prepare("SELECT photo_id, filename FROM photos WHERE report_id=? ORDER BY photo_id ASC");
$p->bind_param('i', $report_id);
$p->execute();
$r = $p->get_result();
while ($row = $r->fetch_assoc()) $photos[] = $row;
$p->close();

$barangays = $conn->query("SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name ASC");
$animals   = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $user_id       = intval($_POST['user_id']);
  $type_of_bite  = $_POST['type_of_bite'];
  $biting_animal_id = intval($_POST['biting_animal_id']);
  $category_id   = intval($_POST['category_id']);
  $barangay_id   = intval($_POST['barangay_id']);
  $status        = $_POST['status'];
  $description   = $_POST['description'];
  $latitude      = trim($_POST['latitude']);
  $longitude     = trim($_POST['longitude']);

  // NEW: Custom date
  $date_reported = !empty($_POST['date_reported'])
      ? $_POST['date_reported']
      : $report['date_reported'];

  $conn->begin_transaction();
  try {

    // UPDATE reports
    $stmt = $conn->prepare("
      UPDATE reports 
      SET user_id=?, type_of_bite=?, category_id=?, biting_animal_id=?, 
          barangay_id=?, longhitud=?, latitud=?, status=?
      WHERE report_id=?
    ");
    $stmt->bind_param(
      'isiiisssi',
      $user_id, $type_of_bite, $category_id, $biting_animal_id,
      $barangay_id, $longitude, $latitude, $status,
      $report_id
    );
    $stmt->execute();
    $stmt->close();

    // UPDATE bites (with date)
    $stmt = $conn->prepare("
      UPDATE bites 
      SET description=?, barangay_id=?, date_reported=?
      WHERE report_id=?
    ");
    $stmt->bind_param('sisi', $description, $barangay_id, $date_reported, $report_id);
    $stmt->execute();
    $stmt->close();

    // Handle photo deletion
    if (!empty($_POST['delete_photos'])) {
      foreach ($_POST['delete_photos'] as $pid) {
        $pid = intval($pid);
        $q = $conn->prepare("SELECT filename FROM photos WHERE photo_id=? AND report_id=?");
        $q->bind_param('ii', $pid, $report_id);
        $q->execute();
        $f = $q->get_result()->fetch_assoc();
        $q->close();

        if ($f) {
          $file = $uploadsDir . $f['filename'];
          if (file_exists($file)) unlink($file);

          $d = $conn->prepare("DELETE FROM photos WHERE photo_id=?");
          $d->bind_param('i', $pid);
          $d->execute();
          $d->close();
        }
      }
    }

    // Upload new photos
    if (!empty($_FILES['photos']['name'][0])) {
      $uu = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id=?");
      $uu->bind_param('i', $user_id);
      $uu->execute();
      $uinfo = $uu->get_result()->fetch_assoc();
      $uu->close();

      $slug = slugify_name($uinfo['first_name'], $uinfo['last_name']);
      $ts = date('YmdHis');

      foreach ($_FILES['photos']['name'] as $i => $name) {
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;

        $filename = "{$slug}-{$ts}-" . ($i+1) . ".{$ext}";
        move_uploaded_file($_FILES['photos']['tmp_name'][$i], $uploadsDir . $filename);

        $ins = $conn->prepare("INSERT INTO photos (report_id, filename) VALUES (?, ?)");
        $ins->bind_param('is', $report_id, $filename);
        $ins->execute();
        $ins->close();
      }
    }

    $conn->commit();
    $success_message = "Report updated successfully.";

  } catch (Exception $e) {
    $conn->rollback();
    $error_message = "Update failed: " . $e->getMessage();
  }
}

?>
<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Edit Report #<?= safe($report_id) ?></h5>
        <a href="<?= $ReportsBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <?php if ($error_message): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded"><?= safe($error_message) ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded"><?= safe($success_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          
          <!-- Reporter -->
          <div>
            <label class="form-label">Reporter</label>
            <input type="text" class="form-control bg-gray-100" disabled value="<?= safe($report['reporter_name']) ?>">
            <input type="hidden" name="user_id" value="<?= safe($report['user_id']) ?>">
          </div>

          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-4">
              <label class="form-label">Type of Bite</label>
              <select name="type_of_bite" class="form-control">
                <option value="Bite" <?= $report['type_of_bite']=='Bite' ? 'selected':'' ?>>Bite</option>
                <option value="Scratch" <?= $report['type_of_bite']=='Scratch' ? 'selected':'' ?>>Scratch</option>
              </select>
            </div>

            <div class="col-span-4">
              <label class="form-label">Animal</label>
              <select name="biting_animal_id" class="form-control">
                <?php while ($a = $animals->fetch_assoc()): ?>
                  <option value="<?= $a['biting_animal_id'] ?>" 
                    <?= $report['biting_animal_id']==$a['biting_animal_id'] ? 'selected':'' ?>>
                    <?= safe($a['animal_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-span-4">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-control">
                <?php while ($c = $categories->fetch_assoc()): ?>
                  <option value="<?= $c['category_id'] ?>" 
                    <?= $report['category_id']==$c['category_id'] ? 'selected':'' ?>>
                    <?= safe($c['category_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <!-- NEW: Date Reported -->
          <div>
            <label class="form-label">Date Reported</label>
            <input type="date" name="date_reported" class="form-control"
              value="<?= safe(date('Y-m-d', strtotime($report['date_reported']))) ?>" required>
          </div>

          <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="4" class="form-control"><?= safe($report['bite_description']) ?></textarea>
          </div>
          <div>
            <label class="form-label">Barangay</label>
            <select name="barangay_id" class="form-control">
              <?php
              $barangays2 = $conn->query("SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name ASC");
              while ($b = $barangays2->fetch_assoc()):
              ?>
                <option value="<?= $b['barangay_id'] ?>"
                  <?= $report['barangay_id']==$b['barangay_id'] ? 'selected':'' ?>>
                  <?= safe($b['barangay_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <?php foreach (['Reported','Contacted','Admitted','Treated','Archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $report['status']==$s?'selected':'' ?>>
                  <?= $s ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Map -->
          <div>
            <label class="form-label">Location (drag marker)</label>
            <div id="map" style="height: 350px; border-radius: 8px;"></div>
            <input type="hidden" name="latitude" id="latitude" value="<?= safe($report['latitud']) ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= safe($report['longhitud']) ?>">
          </div>

          <!-- Existing photos -->
          <div>
            <label class="form-label">Existing Photos</label>
            <div class="flex gap-3 flex-wrap">
              <?php if (empty($photos)): ?>
                <div class="text-sm text-gray-500">No photos uploaded.</div>
              <?php else: foreach ($photos as $p): ?>
                <div class="w-24 text-center">
                  <img src="<?= $uploadsBase . '/' . safe($p['filename']) ?>" 
                       class="rounded border" 
                       style="width:100%;height:70px;object-fit:cover;">
                  <label class="text-xs">
                    <input type="checkbox" name="delete_photos[]" value="<?= $p['photo_id'] ?>"> Delete
                  </label>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <!-- Upload new photos -->
          <div>
            <label class="form-label">Add Photos</label>
            <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
            <small class="text-gray-500">Max total allowed photos: <?= $maxPhotos ?>.</small>
          </div>

          <button class="btn btn-primary mt-4 px-4">Save Changes</button>
        </form>

      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  let lat = parseFloat(document.getElementById("latitude").value) || 13.964;
  let lng = parseFloat(document.getElementById("longitude").value) || 121.527;

  const map = L.map('map').setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
  }).addTo(map);

  const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
  marker.on('dragend', function () {
    const pos = marker.getLatLng();
    document.getElementById("latitude").value = pos.lat;
    document.getElementById("longitude").value = pos.lng;
  });
});
</script>
