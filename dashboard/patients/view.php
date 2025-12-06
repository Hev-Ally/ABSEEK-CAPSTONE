<?php
// dashboard/patients/view.php

include '../../layouts/head.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$PatientsBase = 'dashboard/patients';

// Access control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

require_once '../../assets/db/db.php';

// Validate input
$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    echo "<div class='p-6'>Invalid patient ID.</div>";
    exit;
}

// Fetch patient with user, vaccine, animal, category, schedule
$stmt = $conn->prepare("
  SELECT p.*, u.first_name, u.last_name, u.age AS user_age, u.gender AS user_gender, u.address AS user_address,
         u.email AS user_email, u.phone_number AS user_phone,
         v.brand_name AS vaccine_brand, v.generic_name AS vaccine_generic,
         ba.animal_name, c.category_name,
         s.d0_first_dose_sched, s.d3_second_dose_sched, s.d7_third_dose_sched, s.d14_if_hospitalized_sched, s.d28_klastdose_sched,
         s.d0_first_dose, s.d3_second_dose, s.d7_third_dose, s.d14_if_hospitalized, s.d28_klastdose
  FROM patients p
  LEFT JOIN users u ON u.user_id = p.user_id
  LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
  LEFT JOIN biting_animal ba ON ba.biting_animal_id = p.biting_animal_id
  LEFT JOIN category c ON c.category_id = p.category_id
  LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
  WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo "<div class='p-6'>Patient not found.</div>";
    exit;
}

// Helper
function e($s){ return htmlspecialchars($s ?? ''); }
function showDate($v){ if (empty($v) || $v === '0000-00-00') return ''; return date('F j, Y', strtotime($v)); }

?>
<div class="grid grid-cols-12 gap-x-6 p-6">
  <div class="col-span-12 flex justify-between items-center mb-4">
    <h2 class="text-2xl font-semibold">Patient Record — <?= e($patient['first_name'].' '.$patient['last_name']) ?></h2>
    <div class="flex gap-2">
      <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="btn btn-outline-secondary">← Back</a>
      <a target="_blank" href="<?= $assetBase ?>/dashboard/patients/pdf.php?id=<?= (int)$patient_id ?>" class="btn btn-primary">Generate PDF</a>
    </div>
  </div>

  <!-- USER INFO -->
  <div class="col-span-12 md:col-span-4">
    <div class="card p-4">
      <h4 class="font-semibold mb-3">User Information</h4>
      <div class="space-y-2 text-sm text-gray-700">
        <div><strong>Name:</strong> <?= e($patient['first_name'].' '.$patient['last_name']) ?></div>
        <div><strong>Age:</strong> <?= e($patient['user_age']) ?></div>
        <div><strong>Gender:</strong> <?= e($patient['user_gender']) ?></div>
        <div><strong>Email:</strong> <?= e($patient['user_email']) ?></div>
        <div><strong>Phone:</strong> <?= e($patient['user_phone']) ?></div>
        <div><strong>Address:</strong> <div class="text-sm text-gray-500"><?= e($patient['user_address']) ?></div></div>
        <div><strong>Date Registered:</strong> <?= showDate($patient['date_registered'] ?? $patient['date_appointment'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- PATIENT DETAILS -->
  <div class="col-span-12 md:col-span-8">
    <div class="card p-4 mb-4">
      <h4 class="font-semibold mb-3">Case Details</h4>
      <div class="grid grid-cols-12 gap-3 text-sm text-gray-700">
        <div class="col-span-6"><strong>Type of Bite:</strong> <?= e($patient['type_of_bite']) ?></div>
        <div class="col-span-6"><strong>Category:</strong> <?= e($patient['category_name']) ?></div>

        <div class="col-span-6"><strong>Animal Type:</strong> <?= e($patient['animal_name']) ?></div>
        <div class="col-span-6"><strong>Animal State:</strong> <?= e($patient['animal_state_after_bite']) ?></div>

        <div class="col-span-6"><strong>Body Part:</strong> <?= e($patient['body_part']) ?></div>
        <div class="col-span-6"><strong>Washing:</strong> <?= e($patient['washing']) ?></div>

        <div class="col-span-6"><strong>RIG:</strong> <?= $patient['is_rig'] ? 'Yes' : 'No' ?></div>
        <div class="col-span-6"><strong>Vaccine:</strong> <?= e(($patient['vaccine_brand'] ? $patient['vaccine_brand'] : '') . ($patient['vaccine_generic'] ? ' / ' . $patient['vaccine_generic'] : '')) ?></div>

        <div class="col-span-6"><strong>Route:</strong> <?= e($patient['route']) ?></div>
        <div class="col-span-6"><strong>Linked Report:</strong> <?= $patient['report_id'] ? '#'.(int)$patient['report_id'] : 'None' ?></div>

        <div class="col-span-12 mt-2"><strong>Remarks:</strong>
          <div class="text-sm text-gray-600 mt-1"><?= nl2br(e($patient['remarks'])) ?></div>
        </div>
      </div>
    </div>

    <!-- SCHEDULE -->
    <div class="card p-4">
  <h4 class="font-semibold mb-3">Vaccination Schedule</h4>

  <!-- DESKTOP TABLE -->
  <div class="hidden md:block">
    <table class="table w-full text-sm">
      <thead>
        <tr class="text-left text-xs text-gray-600">
          <th>Dose</th>
          <th>Scheduled Date</th>
          <th>Actual Date Given</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>D0 — First Dose</td>
          <td><?= e(showDate($patient['d0_first_dose_sched'])) ?></td>
          <td><?= e(showDate($patient['d0_first_dose'])) ?></td>
        </tr>
        <tr>
          <td>D3 — Second Dose</td>
          <td><?= e(showDate($patient['d3_second_dose_sched'])) ?></td>
          <td><?= e(showDate($patient['d3_second_dose'])) ?></td>
        </tr>
        <tr>
          <td>D7 — Third Dose</td>
          <td><?= e(showDate($patient['d7_third_dose_sched'])) ?></td>
          <td><?= e(showDate($patient['d7_third_dose'])) ?></td>
        </tr>
        <tr>
          <td>D14 — If Hospitalized</td>
          <td><?= e(showDate($patient['d14_if_hospitalized_sched'])) ?></td>
          <td><?= e(showDate($patient['d14_if_hospitalized'])) ?></td>
        </tr>
        <tr>
          <td>D28 — Last Dose</td>
          <td><?= e(showDate($patient['d28_klastdose_sched'])) ?></td>
          <td><?= e(showDate($patient['d28_klastdose'])) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- MOBILE CARD VIEW -->
  <div class="grid grid-cols-1 gap-3 md:hidden">

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D0 — First Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled Date</div>
      <div class="text-sm"><?= e(showDate($patient['d0_first_dose_sched'])) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Actual Date Given</div>
      <div class="text-sm"><?= e(showDate($patient['d0_first_dose'])) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D3 — Second Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled Date</div>
      <div class="text-sm"><?= e(showDate($patient['d3_second_dose_sched'])) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Actual Date Given</div>
      <div class="text-sm"><?= e(showDate($patient['d3_second_dose'])) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D7 — Third Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled Date</div>
      <div class="text-sm"><?= e(showDate($patient['d7_third_dose_sched'])) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Actual Date Given</div>
      <div class="text-sm"><?= e(showDate($patient['d7_third_dose'])) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D14 — If Hospitalized</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled Date</div>
      <div class="text-sm"><?= e(showDate($patient['d14_if_hospitalized_sched'])) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Actual Date Given</div>
      <div class="text-sm"><?= e(showDate($patient['d14_if_hospitalized'])) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D28 — Last Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled Date</div>
      <div class="text-sm"><?= e(showDate($patient['d28_klastdose_sched'])) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Actual Date Given</div>
      <div class="text-sm"><?= e(showDate($patient['d28_klastdose'])) ?></div>
    </div>

  </div>

</div>

  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
