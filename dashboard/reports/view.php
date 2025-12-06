<?php
// dashboard/reports/view.php

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

require_once '../../assets/db/db.php';
$ReportsBase = $assetBase . '/dashboard/reports';

$report_id = intval($_GET['id'] ?? 0);
if ($report_id <= 0) die("Invalid report ID");

// =====================================================
// FETCH REPORT + USER + CATEGORY + ANIMAL + BARANGAY
// =====================================================
$stmt = $conn->prepare("
    SELECT 
        r.*, 
        u.first_name, u.last_name, u.age AS user_age, 
        u.gender AS user_gender, u.address AS user_address,
        u.email AS user_email, u.phone_number AS user_phone,

        ba.animal_name,
        c.category_name,
        b.barangay_name

    FROM reports r
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
    LEFT JOIN category c ON c.category_id = r.category_id
    LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
    WHERE r.report_id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) die("Report not found");

// =====================================================
// FETCH LINKED PATIENT (if any)
// =====================================================
$patient = null;
if (!empty($report['report_id'])) {
    $stmt = $conn->prepare("
        SELECT 
            p.*, 
            s.*,
            v.brand_name AS vaccine_brand,
            v.generic_name AS vaccine_generic
        FROM patients p
        LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
        LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
        WHERE p.report_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// =====================================================
// FETCH PHOTOS
// =====================================================
$stmtPhotos = $conn->prepare("SELECT filename FROM photos WHERE report_id = ?");
$stmtPhotos->bind_param("i", $report_id);
$stmtPhotos->execute();
$photos = $stmtPhotos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPhotos->close();

// =====================================================
// FETCH DESCRIPTION FROM BITES TABLE
// =====================================================
$stmtDesc = $conn->prepare("SELECT description FROM bites WHERE report_id = ? LIMIT 1");
$stmtDesc->bind_param("i", $report_id);
$stmtDesc->execute();
$descRow = $stmtDesc->get_result()->fetch_assoc();
$stmtDesc->close();

$report_description = $descRow['description'] ?? '';

include '../../layouts/head.php';

function e($v){ return htmlspecialchars($v ?? ''); }
?>

<div class="grid grid-cols-12 gap-6 p-6">

    <div class="col-span-12 flex justify-between items-center mb-4">
        <h3 class="text-2xl font-semibold">Report #<?= $report_id ?></h3>

        <div class="flex gap-2">
            <a href="<?= $ReportsBase ?>/index.php" class="btn btn-outline-secondary">← Back</a>
            <a href="<?= $ReportsBase ?>/pdf.php?id=<?= $report_id ?>" target="_blank" class="btn btn-primary">Generate PDF</a>
        </div>
    </div>

    <!-- REPORTER INFORMATION -->
    <div class="col-span-12 md:col-span-4">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Reporter Information</h4>
            <div class="text-sm space-y-1 text-gray-700">
                <p><b>Name:</b> <?= e($report['first_name'] . " " . $report['last_name']) ?></p>
                <p><b>Phone:</b> <?= e($report['user_phone']) ?></p>
                <p><b>Email:</b> <?= e($report['user_email']) ?></p>
                <p><b>Address:</b> <?= e($report['user_address']) ?></p>
            </div>
        </div>
    </div>

    <!-- REPORT DETAILS -->
    <div class="col-span-12 md:col-span-8">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Report Details</h4>

            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                <p><b>Type of Bite:</b> <?= e($report['type_of_bite']) ?></p>
                <p><b>Animal Type:</b> <?= e($report['animal_name']) ?></p>

                <p><b>Category:</b> <?= e($report['category_name']) ?></p>
                <p><b>Barangay:</b> <?= e($report['barangay_name']) ?></p>

                <p><b>Status:</b> <?= e($report['status']) ?></p>
                <p><b>Date Reported:</b> <?= date("F j, Y", strtotime($report['date_reported'])) ?></p>

                <p class="col-span-2">
                    <b>Description:</b><br><?= nl2br(e($report_description)) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- MAP -->
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

    <!-- LINKED PATIENT (IF EXISTS) -->
    <?php if ($patient): ?>
    <div class="col-span-12">
        <div class="card p-4">
            <h4 class="font-semibold mb-3">Linked Patient Information</h4>

            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                <p><b>Type of Bite:</b> <?= e($patient['type_of_bite']) ?></p>
                <p><b>Body Part:</b> <?= e($patient['body_part']) ?></p>

                <p><b>Animal State:</b> <?= e($patient['animal_state_after_bite']) ?></p>
                <p><b>Vaccine:</b> <?= e($patient['vaccine_brand'] . " / " . $patient['vaccine_generic']) ?></p>

                <p><b>Route:</b> <?= e($patient['route']) ?></p>
                <p><b>RIG:</b> <?= $patient['is_rig'] ? 'Yes' : 'No' ?></p>

                <p class="col-span-2"><b>Remarks:</b> <?= nl2br(e($patient['remarks'])) ?></p>
            </div>

            <hr class="my-4">

            <h4 class="font-semibold mb-2">Vaccination Schedule</h4>

            <table class="table text-sm w-full">
                <thead>
                <tr class="text-left text-xs text-gray-600">
                    <th>Dose</th>
                    <th>Scheduled</th>
                    <th>Actual</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>D0</td><td><?= e($patient['d0_first_dose_sched']) ?></td><td><?= e($patient['d0_first_dose']) ?></td></tr>
                <tr><td>D3</td><td><?= e($patient['d3_second_dose_sched']) ?></td><td><?= e($patient['d3_second_dose']) ?></td></tr>
                <tr><td>D7</td><td><?= e($patient['d7_third_dose_sched']) ?></td><td><?= e($patient['d7_third_dose']) ?></td></tr>
                <tr><td>D14</td><td><?= e($patient['d14_if_hospitalized_sched']) ?></td><td><?= e($patient['d14_if_hospitalized']) ?></td></tr>
                <tr><td>D28</td><td><?= e($patient['d28_klastdose_sched']) ?></td><td><?= e($patient['d28_klastdose']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- PHOTOS -->
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

<?php include '../../layouts/footer-block.php'; ?>

<!-- MAP SCRIPTS -->
<?php if (!empty($report['latitud']) && !empty($report['longhitud'])): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const lat = <?= $report['latitud'] ?>;
    const lng = <?= $report['longhitud'] ?>;

    const map = L.map('map').setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup("Reported Bite Location")
        .openPopup();
});
</script>
<?php endif; ?>
