<?php
// ======================================================================
// Community Patient Record Viewer
// Location: /dashboard/community/patient/index.php
// ======================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

include '../../../assets/db/db.php';
include '../../../layouts/head.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

if (!$user_id || $user_role !== 'community_user') {
    header("Location: ../../../pages/login.php");
    exit;
}

// Patient module base URL
$PatientBase = "/dashboard/community/patient";

// ----------------------------------------------------------------------
// FETCH LATEST PATIENT RECORD FOR THIS USER
// ----------------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        u.first_name, u.last_name, u.age AS user_age, u.gender AS user_gender,
        u.address AS user_address, u.email AS user_email, u.phone_number AS user_phone,
        ba.animal_name, 
        c.category_name,
        v.brand_name AS vaccine_brand, v.generic_name AS vaccine_generic,
        s.*
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.user_id
    LEFT JOIN biting_animal ba ON ba.biting_animal_id = p.biting_animal_id
    LEFT JOIN category c ON c.category_id = p.category_id
    LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
    LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
    WHERE p.user_id = ?
    ORDER BY p.patient_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

function h($s){ return htmlspecialchars($s ?? ''); }
function showDate($v){
    if (empty($v) || $v == "0000-00-00") return "—";
    return date("F j, Y", strtotime($v));
}
?>

<div class="grid grid-cols-12 gap-x-6 p-6">

  <div class="col-span-12 flex justify-between items-center mb-4">
    <h2 class="text-2xl font-semibold">My Patient Record</h2>
    <div class="flex gap-2">
    <a href="<?= $assetBase ?>/dashboard/community/profile/index.php" class="btn btn-outline-secondary">← Back</a>

        <?php if ($patient): ?>
            <a target="_blank" 
               href="<?= $assetBase ?>/dashboard/community/patient/pdf.php?id=<?= (int)$patient['patient_id'] ?>" 
               class="btn btn-primary">
                Generate PDF
            </a>
        <?php endif; ?>
    </div>
  </div>

  <?php if (!$patient): ?>
    
    <!-- No patient record -->
    <div class="col-span-12">
      <div class="card p-6 text-center">
        <h3 class="text-xl font-semibold mb-3">No Patient Record Found</h3>
        <p class="text-gray-600 mb-4">
          You currently do not have any patient record.
        </p>
      </div>
    </div>

  <?php else: ?>

    <!-- USER INFORMATION -->
    <div class="col-span-12 md:col-span-4">
      <div class="card p-4">
        <h4 class="font-semibold mb-3">User Information</h4>
        <div class="space-y-2 text-sm text-gray-700">
          <div><strong>Name:</strong> <?= h($patient['first_name'].' '.$patient['last_name']) ?></div>
          <div><strong>Age:</strong> <?= h($patient['user_age']) ?></div>
          <div><strong>Gender:</strong> <?= h($patient['user_gender']) ?></div>
          <div><strong>Email:</strong> <?= h($patient['user_email']) ?></div>
          <div><strong>Phone:</strong> <?= h($patient['user_phone']) ?></div>
          <div>
            <strong>Address:</strong>
            <div class="text-sm text-gray-500"><?= h($patient['user_address']) ?></div>
          </div>
          <div><strong>Date Registered:</strong> <?= showDate($patient['date_registered'] ?? $patient['date_appointment'] ?? '') ?></div>
        </div>
      </div>
    </div>

    <!-- PATIENT DETAILS -->
    <div class="col-span-12 md:col-span-8">
      <div class="card p-4 mb-4">
        <h4 class="font-semibold mb-3">Case Details</h4>
        <div class="grid grid-cols-12 gap-3 text-sm text-gray-700">

          <div class="col-span-6"><strong>Type of Bite:</strong> <?= h($patient['type_of_bite']) ?></div>
          <div class="col-span-6"><strong>Category:</strong> <?= h($patient['category_name']) ?></div>

          <div class="col-span-6"><strong>Animal Type:</strong> <?= h($patient['animal_name']) ?></div>
          <div class="col-span-6"><strong>Animal State:</strong> <?= h($patient['animal_state_after_bite']) ?></div>

          <div class="col-span-6"><strong>Body Part:</strong> <?= h($patient['body_part']) ?></div>
          <div class="col-span-6"><strong>Washing:</strong> <?= h($patient['washing']) ?></div>

          <div class="col-span-6"><strong>RIG:</strong> <?= $patient['is_rig'] ? 'Yes' : 'No' ?></div>
          <div class="col-span-6">
            <strong>Vaccine:</strong>
            <?= h(($patient['vaccine_brand'] ?? '') . 
                  ($patient['vaccine_generic'] ? ' / '.$patient['vaccine_generic'] : '')) ?>
          </div>

          <div class="col-span-6"><strong>Route:</strong> <?= h($patient['route']) ?></div>

          <div class="col-span-12 mt-2">
            <strong>Remarks:</strong>
            <div class="text-sm text-gray-600 mt-1"><?= nl2br(h($patient['remarks'])) ?></div>
          </div>

        </div>
      </div>

      <!-- VACCINE SCHEDULE -->
      <div class="card p-4">
  <h4 class="font-semibold mb-3">Vaccination Schedule</h4>

  <!-- DESKTOP TABLE -->
  <div class="hidden md:block">
    <table class="table w-full text-sm">
      <thead>
        <tr class="text-left text-xs text-gray-600">
          <th>Dose</th>
          <th>Scheduled</th>
          <th>Given</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>D0 — First Dose</td><td><?= showDate($patient['d0_first_dose_sched']) ?></td><td><?= showDate($patient['d0_first_dose']) ?></td></tr>
        <tr><td>D3 — Second Dose</td><td><?= showDate($patient['d3_second_dose_sched']) ?></td><td><?= showDate($patient['d3_second_dose']) ?></td></tr>
        <tr><td>D7 — Third Dose</td><td><?= showDate($patient['d7_third_dose_sched']) ?></td><td><?= showDate($patient['d7_third_dose']) ?></td></tr>
        <tr><td>D14 — If Hospitalized</td><td><?= showDate($patient['d14_if_hospitalized_sched']) ?></td><td><?= showDate($patient['d14_if_hospitalized']) ?></td></tr>
        <tr><td>D28 — Last Dose</td><td><?= showDate($patient['d28_klastdose_sched']) ?></td><td><?= showDate($patient['d28_klastdose']) ?></td></tr>
      </tbody>
    </table>
  </div>

  <!-- MOBILE CARD VIEW -->
  <div class="grid grid-cols-1 gap-3 md:hidden">

    <!-- Dose Card -->
    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold">D0 — First Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled</div>
      <div class="text-sm"><?= showDate($patient['d0_first_dose_sched']) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Given</div>
      <div class="text-sm"><?= showDate($patient['d0_first_dose']) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D3 — Second Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled</div>
      <div class="text-sm"><?= showDate($patient['d3_second_dose_sched']) ?></div>
      <div class="font-semibold text-gray-500 mt-1">Given</div>
      <div class="text-sm"><?= showDate($patient['d3_second_dose']) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D7 — Third Dose</div>
      <div class="font-semibold  text-gray-500 mt-1">Scheduled</div>
      <div class="text-sm"><?= showDate($patient['d7_third_dose_sched']) ?></div>
      <div class="font-semibold  text-gray-500 mt-1">Given</div>
      <div class="text-sm"><?= showDate($patient['d7_third_dose']) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="font-semibold pb-5">D14 — If Hospitalized</div>
      <div class="font-semibold  text-gray-500 mt-1">Scheduled</div>
      <div class="text-sm"><?= showDate($patient['d14_if_hospitalized_sched']) ?></div>
      <div class="font-semibold  text-gray-500 mt-1">Given</div>
      <div class="text-sm"><?= showDate($patient['d14_if_hospitalized']) ?></div>
    </div>

    <div class="p-5 border rounded-lg shadow-sm bg-white">
      <div class="ffont-semibold pb-5">D28 — Last Dose</div>
      <div class="font-semibold text-gray-500 mt-1">Scheduled</div>
      <div class="text-sm"><?= showDate($patient['d28_klastdose_sched']) ?></div>
      <div class="font-semibold  text-gray-500 mt-1">Given</div>
      <div class="text-sm"><?= showDate($patient['d28_klastdose']) ?></div>
    </div>

  </div>
</div>

    </div>

  <?php endif; ?>

</div>

<?php include '../../../layouts/footer-block.php'; ?>
