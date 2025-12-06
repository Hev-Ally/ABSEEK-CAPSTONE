<?php
// dashboard/community/reports/view.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'community_user') {
    header("Location: ../../index.php");
    exit;
}

require_once '../../../assets/db/db.php';

$ReportsBase = '/dashboard/community/reports';
$report_id = intval($_GET['id'] ?? 0);
if ($report_id <= 0) die("Invalid ID");

// =====================================================
// FETCH REPORT + USER + CATEGORY + ANIMAL + BARANGAY + BITE DESCRIPTION
// =====================================================
$stmt = $conn->prepare("
    SELECT 
        r.*,
        CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
        u.phone_number, u.email,
        ba.animal_name,
        c.category_name,
        b.barangay_name,
        bt.date_reported,
        bt.description AS bite_description
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
    LEFT JOIN category c ON c.category_id = r.category_id
    LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
    LEFT JOIN bites bt ON bt.report_id = r.report_id
    WHERE r.report_id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) die("Report not found");

// =====================================================
// FETCH PHOTOS
// =====================================================
$stmtPhotos = $conn->prepare("SELECT filename FROM photos WHERE report_id = ?");
$stmtPhotos->bind_param("i", $report_id);
$stmtPhotos->execute();
$photos = $stmtPhotos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPhotos->close();

include '../../../layouts/head.php';

function e($v){ return htmlspecialchars($v ?? ''); }
?>

<div class="grid grid-cols-12 gap-6 p-6">

    <div class="col-span-12 flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold">Report #<?= $report_id ?></h3>

        <div class="flex gap-2">
            <a href="<?= $ReportsBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
            <a href="<?= $ReportsBase ?>/pdf.php?id=<?= $report_id ?>" target="_blank" class="btn btn-primary">
                Generate PDF
            </a>
        </div>
    </div>

    <!-- Reporter Info -->
    <div class="col-span-12 md:col-span-4">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Reporter Information</h4>
            <div class="text-sm space-y-1 text-gray-700">
                <p><b>Name:</b> <?= e($report['reporter_name']) ?></p>
                <p><b>Phone:</b> <?= e($report['phone_number']) ?></p>
                <p><b>Email:</b> <?= e($report['email']) ?></p>
            </div>
        </div>
    </div>

    <!-- Report Details -->
    <div class="col-span-12 md:col-span-8">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Report Details</h4>

            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                <p><b>Type of Bite:</b> <?= e($report['type_of_bite']) ?></p>
                <p><b>Animal Type:</b> <?= e($report['animal_name']) ?></p>

                <p><b>Category:</b> <?= e($report['category_name']) ?></p>
                <p><b>Barangay:</b> <?= e($report['barangay_name']) ?></p>

                <p><b>Status:</b> <?= e($report['status']) ?></p>
                <p><b>Date Reported:</b> <?= $report['date_reported'] ? date("F d, Y", strtotime($report['date_reported'])) : 'N/A' ?></p>

                <p class="col-span-2"><b>Description:</b><br>
                    <?= nl2br(e($report['bite_description'])) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="col-span-12">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Reported Location</h4>

            <?php if (!empty($report['latitud']) && !empty($report['longhitud'])): ?>
                <div id="map" style="height: 350px; border-radius: 10px;"></div>
            <?php else: ?>
                <div class="text-gray-500">No location data available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Photos -->
    <div class="col-span-12">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Photo Evidence</h4>

            <div class="flex flex-wrap gap-3">
                <?php if (count($photos)): ?>
                    <?php foreach ($photos as $p): ?>
                        <img src="<?= $assetBase ?>/uploads/<?= e($p['filename']) ?>"
                             class="w-40 h-40 object-cover rounded shadow">
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">No photos uploaded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php include '../../../layouts/footer-block.php'; ?>

<!-- Leaflet Map Script -->
<?php if (!empty($report['latitud']) && !empty($report['longhitud'])): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const lat = parseFloat(<?= json_encode($report['latitud']) ?>);
    const lng = parseFloat(<?= json_encode($report['longhitud']) ?>);

    const map = L.map('map').setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup("Reported Bite Location")
        .openPopup();
});
</script>
<?php endif; ?>
