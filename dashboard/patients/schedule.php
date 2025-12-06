<?php
// /dashboard/patients/schedule.php

// ----------------------------
// SESSION + SECURITY
// ----------------------------
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// ----------------------------
// DATABASE (ensure loaded for AJAX)
// ----------------------------
require '../../assets/db/db.php';

// ==========================================================
//  AJAX SAVE HANDLER — MUST RUN BEFORE ANY HTML OUTPUT
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_schedule') {

    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $patient_id  = intval($_POST['patient_id'] ?? 0);

    if ($schedule_id <= 0 || $patient_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid IDs"]);
        exit;
    }

    // incoming scheduled + actual dates (allow empty -> NULL)
    $d0_sched = trim($_POST['d0_first_dose_sched'] ?? '');
    $d3_sched = trim($_POST['d3_second_dose_sched'] ?? '');
    $d7_sched = trim($_POST['d7_third_dose_sched'] ?? '');
    $d14_sched = trim($_POST['d14_if_hospitalized_sched'] ?? '');
    $d28_sched = trim($_POST['d28_klastdose_sched'] ?? '');

    $d0_act = trim($_POST['d0_first_dose'] ?? '');
    $d3_act = trim($_POST['d3_second_dose'] ?? '');
    $d7_act = trim($_POST['d7_third_dose'] ?? '');
    $d14_act = trim($_POST['d14_if_hospitalized'] ?? '');
    $d28_act = trim($_POST['d28_klastdose'] ?? '');

    // Normalize empty strings to NULL when updating the DB using NULLIF(?, '')
    try {
        $stmt = $conn->prepare("
            UPDATE schedule SET
                d0_first_dose_sched = NULLIF(?, ''),
                d3_second_dose_sched = NULLIF(?, ''),
                d7_third_dose_sched = NULLIF(?, ''),
                d14_if_hospitalized_sched = NULLIF(?, ''),
                d28_klastdose_sched = NULLIF(?, ''),
                d0_first_dose = NULLIF(?, ''),
                d3_second_dose = NULLIF(?, ''),
                d7_third_dose = NULLIF(?, ''),
                d14_if_hospitalized = NULLIF(?, ''),
                d28_klastdose = NULLIF(?, '')
            WHERE schedule_id = ?
        ");
        $stmt->bind_param(
            "ssssssssssi",
            $d0_sched, $d3_sched, $d7_sched, $d14_sched, $d28_sched,
            $d0_act, $d3_act, $d7_act, $d14_act, $d28_act,
            $schedule_id
        );
        $ok = $stmt->execute();
        $stmt->close();

        // also ensure patients.schedule_id is linked (in case schedule existed but wasn't linked)
        if ($ok) {
            $stmt2 = $conn->prepare("UPDATE patients SET schedule_id=? WHERE patient_id=?");
            $stmt2->bind_param("ii", $schedule_id, $patient_id);
            $stmt2->execute();
            $stmt2->close();
        }

        echo json_encode([
            "success" => (bool)$ok,
            "message" => $ok ? "Schedule updated successfully!" : "Update failed."
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        exit;
    }
}

// ==========================================================
//  NORMAL PAGE LOAD — SAFE TO OUTPUT HTML
// ==========================================================
include '../../layouts/head.php';

// Patient ID required
$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid patient ID."));
    exit;
}

// Fetch patient + user
$stmt = $conn->prepare("
    SELECT p.patient_id, p.user_id, p.schedule_id,
           u.first_name, u.last_name
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("Location: index.php?error=" . urlencode("Patient not found"));
    exit;
}

$full_name = trim(htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')));
if ($full_name === '') $full_name = "Patient #{$patient_id}";

$schedule_id = intval($patient['schedule_id'] ?? 0);

// ==========================================================
//  AUTO-CREATE SCHEDULE IF MISSING
// ==========================================================
if ($schedule_id <= 0) {
    try {
        $conn->begin_transaction();

        $today = new DateTimeImmutable('today');

        $d0s  = $today->format('Y-m-d');
        $d3s  = $today->modify('+3 days')->format('Y-m-d');
        $d7s  = $today->modify('+7 days')->format('Y-m-d');
        $d14s = $today->modify('+14 days')->format('Y-m-d');
        $d28s = $today->modify('+28 days')->format('Y-m-d');

        $stmt = $conn->prepare("
            INSERT INTO schedule
                (d0_first_dose_sched, d3_second_dose_sched, d7_third_dose_sched,
                 d14_if_hospitalized_sched, d28_klastdose_sched)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $d0s, $d3s, $d7s, $d14s, $d28s);
        $stmt->execute();

        $new_id = $conn->insert_id;
        $stmt->close();

        // link to patient
        $stmt = $conn->prepare("UPDATE patients SET schedule_id=? WHERE patient_id=?");
        $stmt->bind_param("ii", $new_id, $patient_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $schedule_id = $new_id;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error creating schedule: " . $e->getMessage();
    }
}

// Fetch schedule
$stmt = $conn->prepare("SELECT * FROM schedule WHERE schedule_id=?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

function d($v) {
    if (!$v || $v === "0000-00-00") return "";
    return substr($v, 0, 10);
}

?>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">

      <div class="card-header flex justify-between items-center">
        <h5>Vaccination Schedule — <?= $full_name ?></h5>
        <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <!-- BADGES (JS will fill these too) -->
        <div id="msgArea"></div>

        <form id="scheduleForm" class="space-y-4 max-w-2xl" onsubmit="return false;">

          <input type="hidden" id="schedule_id" value="<?= $schedule_id ?>">
          <input type="hidden" id="patient_id" value="<?= $patient_id ?>">

          <div class="grid grid-cols-12 gap-3">

            <!-- Scheduled & Actual fields -->

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D0 — First Dose (Scheduled)</label>
              <input type="date" id="d0_first_dose_sched" class="form-control" value="<?= d($schedule['d0_first_dose_sched']) ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D0 — First Dose (Actual)</label>
              <input type="date" id="d0_first_dose" class="form-control" value="<?= d($schedule['d0_first_dose']) ?>">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D3 — Second Dose (Scheduled)</label>
              <input type="date" id="d3_second_dose_sched" class="form-control" value="<?= d($schedule['d3_second_dose_sched']) ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D3 — Second Dose (Actual)</label>
              <input type="date" id="d3_second_dose" class="form-control" value="<?= d($schedule['d3_second_dose']) ?>">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D7 — Third Dose (Scheduled)</label>
              <input type="date" id="d7_third_dose_sched" class="form-control" value="<?= d($schedule['d7_third_dose_sched']) ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D7 — Third Dose (Actual)</label>
              <input type="date" id="d7_third_dose" class="form-control" value="<?= d($schedule['d7_third_dose']) ?>">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D14 — If Hospitalized (Scheduled)</label>
              <input type="date" id="d14_if_hospitalized_sched" class="form-control" value="<?= d($schedule['d14_if_hospitalized_sched']) ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D14 — If Hospitalized (Actual)</label>
              <input type="date" id="d14_if_hospitalized" class="form-control" value="<?= d($schedule['d14_if_hospitalized']) ?>">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D28 — Last Dose (Scheduled)</label>
              <input type="date" id="d28_klastdose_sched" class="form-control" value="<?= d($schedule['d28_klastdose_sched']) ?>">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">D28 — Last Dose (Actual)</label>
              <input type="date" id="d28_klastdose" class="form-control" value="<?= d($schedule['d28_klastdose']) ?>">
            </div>

          </div>

          <button type="button" id="saveSchedule" class="btn btn-primary mt-4">Save Schedule</button>

        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const saveBtn = document.getElementById('saveSchedule');
  const msgArea = document.getElementById('msgArea');

  function showBadge(text, type = "success") {
    const color = type === "success" ? "bg-success" : "bg-red";
    msgArea.innerHTML = `
      <div class="mb-4 px-4 py-3 ${color} text-white rounded-lg">
        ${text}
      </div>
    `;
  }

  // utility: format Date object -> yyyy-mm-dd
  function ymd(dateObj) {
    const y = dateObj.getFullYear();
    let m = dateObj.getMonth() + 1;
    let d = dateObj.getDate();
    if (m < 10) m = '0' + m;
    if (d < 10) d = '0' + d;
    return `${y}-${m}-${d}`;
  }

  // parse yyyy-mm-dd -> Date or null
  function parseYmd(str) {
    if (!str) return null;
    const parts = str.split('-');
    if (parts.length !== 3) return null;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const dt = new Date(y, m, d);
    // basic validation
    if (isNaN(dt.getTime())) return null;
    return dt;
  }

  saveBtn.onclick = async () => {

    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const payload = new URLSearchParams();
    payload.append("action", "save_schedule");
    payload.append("schedule_id", document.getElementById("schedule_id").value);
    payload.append("patient_id", document.getElementById("patient_id").value);

    // scheduled fields
    payload.append("d0_first_dose_sched", document.getElementById("d0_first_dose_sched").value || "");
    payload.append("d3_second_dose_sched", document.getElementById("d3_second_dose_sched").value || "");
    payload.append("d7_third_dose_sched", document.getElementById("d7_third_dose_sched").value || "");
    payload.append("d14_if_hospitalized_sched", document.getElementById("d14_if_hospitalized_sched").value || "");
    payload.append("d28_klastdose_sched", document.getElementById("d28_klastdose_sched").value || "");

    // actual fields
    payload.append("d0_first_dose", document.getElementById("d0_first_dose").value || "");
    payload.append("d3_second_dose", document.getElementById("d3_second_dose").value || "");
    payload.append("d7_third_dose", document.getElementById("d7_third_dose").value || "");
    payload.append("d14_if_hospitalized", document.getElementById("d14_if_hospitalized").value || "");
    payload.append("d28_klastdose", document.getElementById("d28_klastdose").value || "");

    try {
      const res = await fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: payload.toString()
      });

      const data = await res.json();

      if (data.success) {
        showBadge(data.message, "success");

        setTimeout(() => {
          window.location.href = "<?= $assetBase ?>/dashboard/patients/index.php";
        }, 900); // short delay so badge shows briefly

      } else {
        showBadge(data.message || "Update failed", "error");
      }

    } catch (err) {
      showBadge("Connection error", "error");
    }

    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Schedule';
  };
});
</script>
