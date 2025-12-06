<?php
// ======================================================================
// Community Patient PDF Generator
// Location: /dashboard/community/patient/pdf.php
// ======================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

include '../../../assets/db/db.php';

// ----------------------------------------------------------------------
// ACCESS CONTROL — COMMUNITY USER ONLY
// ----------------------------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'community_user') {
    header("HTTP/1.1 403 Forbidden");
    die("Unauthorized.");
}

$user_id = (int) $_SESSION['user_id'];

$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) die("Invalid patient ID");

// ----------------------------------------------------------------------
// FETCH PATIENT — must belong to this user
// ----------------------------------------------------------------------
$stmt = $conn->prepare("
  SELECT p.*, 
         u.first_name, u.last_name, u.age AS user_age, u.gender AS user_gender,
         u.address AS user_address, u.email AS user_email, u.phone_number AS user_phone,
         v.brand_name AS vaccine_brand, v.generic_name AS vaccine_generic,
         ba.animal_name, c.category_name,
         s.d0_first_dose_sched, s.d3_second_dose_sched, s.d7_third_dose_sched,
         s.d14_if_hospitalized_sched, s.d28_klastdose_sched,
         s.d0_first_dose, s.d3_second_dose, s.d7_third_dose,
         s.d14_if_hospitalized, s.d28_klastdose
  FROM patients p
  LEFT JOIN users u ON u.user_id = p.user_id
  LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
  LEFT JOIN biting_animal ba ON ba.biting_animal_id = p.biting_animal_id
  LEFT JOIN category c ON c.category_id = p.category_id
  LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
  WHERE p.patient_id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $patient_id, $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) die("Unauthorized or patient not found.");

// ----------------------------------------------------------------------
// Helper functions
// ----------------------------------------------------------------------
function h($v){ return htmlspecialchars($v ?? ''); }
function showDate($v){
    if (!$v || $v === "0000-00-00") return '';
    return date("F j, Y", strtotime($v));
}

// Prepare variables
$fname = h($patient['first_name']);
$lname = h($patient['last_name']);
$full_name = trim("$fname $lname");

$user_age = h($patient['user_age']);
$user_gender = h($patient['user_gender']);
$user_addr = h($patient['user_address']);
$user_email = h($patient['user_email']);
$user_phone = h($patient['user_phone']);

$type_bite = h($patient['type_of_bite']);
$category = h($patient['category_name']);
$animal = h($patient['animal_name']);
$animal_state = h($patient['animal_state_after_bite']);
$body_part = h($patient['body_part']);
$washing = h($patient['washing']);
$is_rig = $patient['is_rig'] ? "Yes" : "No";
$vaccine = trim(h($patient['vaccine_brand']) . " " . h($patient['vaccine_generic']));
$remarks = h($patient['remarks']);
$report_id = $patient['report_id'] ? "#" . intval($patient['report_id']) : "None";

// Schedule
$s_d0s = showDate($patient['d0_first_dose_sched']);
$s_d3s = showDate($patient['d3_second_dose_sched']);
$s_d7s = showDate($patient['d7_third_dose_sched']);
$s_d14s = showDate($patient['d14_if_hospitalized_sched']);
$s_d28s = showDate($patient['d28_klastdose_sched']);
$s_d0a = showDate($patient['d0_first_dose']);
$s_d3a = showDate($patient['d3_second_dose']);
$s_d7a = showDate($patient['d7_third_dose']);
$s_d14a = showDate($patient['d14_if_hospitalized']);
$s_d28a = showDate($patient['d28_klastdose']);

$generated_date = date("F j, Y");

// ----------------------------------------------------------------------
// Load TCPDF
// ----------------------------------------------------------------------
$tcpdfPath = __DIR__ . '/../../../assets/libs/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) die("TCPDF not found.");
require_once $tcpdfPath;

$pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);
$pdf->SetAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 12, 15);
$pdf->AddPage();

// ----------------------------------------------------------------------
// FIXED HEADER (Correct spacing to avoid overlapping)
// ----------------------------------------------------------------------
$logoWidth = 36;       // slightly smaller for better flow
$textWidth = 140;
$headerTop = 14;       // top padding (increased for spacing)

// Draw logo
$logoFile = __DIR__ . "/../../../assets/images/logo-white.jpg";
if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 15, $headerTop, $logoWidth);
}

// Starting X for text
$textX = 15 + $logoWidth + 10;

// --- MAIN TITLE: LFG ---
$pdf->SetXY($textX, $headerTop + 2);
$pdf->SetFont("helvetica", "B", 32);
$pdf->SetTextColor(0,0,0);
$pdf->Cell($textWidth, 16, "LFG", 0, 1, "L");

// --- SUBTITLE: Animal Bite Center ---
$pdf->SetX($textX);
$pdf->SetFont("helvetica", "B", 24);
$pdf->Cell($textWidth, 14, "Animal Bite Center", 0, 1, "L");

// --- BLUE BADGE ---
$pdf->SetXY($textX, $headerTop + 36);
$pdf->SetFont("helvetica", "B", 11);
$pdf->SetFillColor(50,115,220);
$pdf->SetTextColor(255,255,255);
$pdf->Cell($textWidth, 10, "POST EXPOSURE PROPHYLAXIS (PEP) DOCUMENT", 0, 1, "L", true);

// --- CONTACT INFO ---
$pdf->SetX($textX);
$pdf->SetFont("helvetica", "", 10);
$pdf->SetTextColor(70,70,70);
$pdf->Cell($textWidth, 6, "Sariaya Branch (Inside Briones Clinic)", 0, 1, "L");

$pdf->SetX($textX);
$pdf->Cell($textWidth, 5, "Contact #: 0930-6501447", 0, 1, "L");

// --- DIVIDER LINE ---
$dividerY = $headerTop + 58;   // moved down more
$pdf->SetDrawColor(180,180,180);
$pdf->Line(15, $dividerY, 195, $dividerY);

// Move body content below the header
$pdf->SetY($dividerY + 6);


// ----------------------------------------------------------------------
// MAIN BODY
// ----------------------------------------------------------------------
$html = <<<HTML
<style>
.section-title { background:#f3f4f6; padding:5px 8px; font-weight:bold; font-size:12px; margin-top:4px; }
table.info { width:100%; border-collapse:collapse; font-size:11px; }
table.info td { padding:3px 6px; vertical-align:top; }
table.sched { width:100%; border-collapse:collapse; font-size:11px; margin-top:5px; }
table.sched th, table.sched td { border:1px solid #aaa; padding:4px; }
.muted { color:#666; font-size:10px; }
</style>

<div class="section-title">User Information</div>
<table class="info">
  <tr><td width="28%"><b>Full Name</b></td><td>{$full_name}</td></tr>
  <tr><td><b>Age</b></td><td>{$user_age}</td></tr>
  <tr><td><b>Gender</b></td><td>{$user_gender}</td></tr>
  <tr><td><b>Email</b></td><td>{$user_email}</td></tr>
  <tr><td><b>Phone</b></td><td>{$user_phone}</td></tr>
  <tr><td><b>Address</b></td><td class="muted">{$user_addr}</td></tr>
</table>

<br>

<div class="section-title">Patient Case Details</div>
<table class="info">
  <tr><td width="28%"><b>Type of Bite</b></td><td>{$type_bite}</td></tr>
  <tr><td><b>Category</b></td><td>{$category}</td></tr>
  <tr><td><b>Animal Type</b></td><td>{$animal}</td></tr>
  <tr><td><b>Animal State</b></td><td>{$animal_state}</td></tr>
  <tr><td><b>Body Part</b></td><td>{$body_part}</td></tr>
  <tr><td><b>Washing</b></td><td>{$washing}</td></tr>
  <tr><td><b>RIG</b></td><td>{$is_rig}</td></tr>
  <tr><td><b>Vaccine</b></td><td>{$vaccine}</td></tr>
  <tr><td><b>Remarks</b></td><td class="muted">{$remarks}</td></tr>
  <tr><td><b>Linked Report</b></td><td>{$report_id}</td></tr>
</table>

<br>

<div class="section-title">Vaccination Schedule</div>
<table class="sched">
  <thead>
    <tr><th>Dose</th><th>Scheduled</th><th>Given</th></tr>
  </thead>
  <tbody>
    <tr><td>D0 — First Dose</td><td>{$s_d0s}</td><td>{$s_d0a}</td></tr>
    <tr><td>D3 — Second Dose</td><td>{$s_d3s}</td><td>{$s_d3a}</td></tr>
    <tr><td>D7 — Third Dose</td><td>{$s_d7s}</td><td>{$s_d7a}</td></tr>
    <tr><td>D14 — If Hospitalized</td><td>{$s_d14s}</td><td>{$s_d14a}</td></tr>
    <tr><td>D28 — Last Dose</td><td>{$s_d28s}</td><td>{$s_d28a}</td></tr>
  </tbody>
</table>
HTML;

$pdf->writeHTML($html, true, false, true, false, "");

// ----------------------------------------------------------------------
// FOOTER
// ----------------------------------------------------------------------
$pdf->Ln(4);
$pdf->SetFont("helvetica", "", 9);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0, 6, "Report generated on: {$generated_date}", 0, 1, "L");

$pdf->Output("patient_record_{$patient_id}.pdf", "I");
exit;
