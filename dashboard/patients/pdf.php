<?php
// dashboard/patients/pdf.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header('HTTP/1.1 403 Forbidden');
    die("Unauthorized.");
}

require_once '../../assets/db/db.php';

$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) die("Invalid ID");

// === Fetch patient info ===
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
  WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) die("Patient not found");

// ================================
// Helpers & sanitized variables
// ================================
function h($v){ return htmlspecialchars($v ?? ''); }
function showDate($v){
    if (empty($v) || $v === '0000-00-00') return '';
    return date('F j, Y', strtotime($v));
}

$fname      = h($patient['first_name']);
$lname      = h($patient['last_name']);
$full_name  = trim("$fname $lname");

$user_age   = h($patient['user_age']);
$user_gender= h($patient['user_gender']);
$user_addr  = h($patient['user_address']);
$user_email = h($patient['user_email']);
$user_phone = h($patient['user_phone']);

$type_bite  = h($patient['type_of_bite']);
$category   = h($patient['category_name']);
$animal     = h($patient['animal_name']);
$animal_state = h($patient['animal_state_after_bite']);
$body_part  = h($patient['body_part']);
$washing    = h($patient['washing']);
$is_rig     = $patient['is_rig'] ? "Yes" : "No";
$vaccine    = trim(h($patient['vaccine_brand']) . ' ' . h($patient['vaccine_generic']));
$remarks    = h($patient['remarks']);
$report_id  = $patient['report_id'] ? '#'.intval($patient['report_id']) : 'None';

// schedule
$s_d0s = showDate($patient['d0_first_dose_sched']);
$s_d3s = showDate($patient['d3_second_dose_sched']);
$s_d7s = showDate($patient['d7_third_dose_sched']);
$s_d14s= showDate($patient['d14_if_hospitalized_sched']);
$s_d28s= showDate($patient['d28_klastdose_sched']);
$s_d0a = showDate($patient['d0_first_dose']);
$s_d3a = showDate($patient['d3_second_dose']);
$s_d7a = showDate($patient['d7_third_dose']);
$s_d14a= showDate($patient['d14_if_hospitalized']);
$s_d28a= showDate($patient['d28_klastdose']);

$generated_date = date('F j, Y');

// ================================
// Load TCPDF
// ================================
$tcpdfPath = __DIR__ . '/../../assets/libs/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) die("TCPDF not found at $tcpdfPath");
require_once $tcpdfPath;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 12, 15);
$pdf->AddPage();

// ================================
// HEADER: Logo Left + Title Right
// ================================
$logoWidth = 40;
$textWidth = 140;
$headerY = 12;

$logoFile = __DIR__ . '/../../assets/images/logo-white.jpg';

if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 15, $headerY, $logoWidth, 0, 'JPG');
}

$textX = 15 + $logoWidth + 8;

$pdf->SetXY($textX, $headerY);

// LFG (50px approx)
$pdf->SetFont('helvetica', 'B', 32);
$pdf->SetTextColor(0,0,0);
$pdf->Cell($textWidth, 14, 'LFG', 0, 1, 'L');

// Animal Bite Center (40px approx)
$pdf->SetX($textX);
$pdf->SetFont('helvetica', 'B', 26);
$pdf->Cell($textWidth, 12, 'Animal Bite Center', 0, 1, 'L');

// Badge
$badgeY = $headerY + 26;
$pdf->SetXY($textX, $badgeY);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(50,115,220);
$pdf->SetTextColor(255,255,255);
$pdf->Cell($textWidth, 10, 'POST EXPOSURE PROPHYLAXIS (PEP) DOCUMENT', 0, 1, 'L', true);

// Contact Info
$pdf->SetX($textX);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(80,80,80);
$pdf->Cell($textWidth, 6, 'Sariaya Branch (Inside Briones Clinic)', 0, 1, 'L');
$pdf->SetX($textX);
$pdf->Cell($textWidth, 5, 'Contact #: 0930-6501447', 0, 1, 'L');

// Divider line
$dividerY = $badgeY + 22;
$pdf->SetDrawColor(200,200,200);
$pdf->Line(15, $dividerY, 195, $dividerY);

$pdf->SetY($dividerY + 4);

// ================================
// MAIN HTML BODY
// ================================
$html = <<<HTML
<style>
  .section-title { background:#f3f4f6; padding:5px 8px; font-weight:bold; font-size:12px; margin-top:4px; }
  table.info { width:100%; border-collapse:collapse; font-size:11px; }
  table.info td { padding:3px 6px; vertical-align:top; }
  table.sched { width:100%; border-collapse:collapse; font-size:11px; margin-top:5px; }
  table.sched th, table.sched td { border:1px solid #aaa; padding:5px; }
  .muted { color:#666; font-size:10px; }
</style>

<div class="section-title">User Information</div>
<table class="info">
  <tr><td width="28%"><b>Full Name</b></td><td>{$full_name}</td></tr>
  <tr><td><b>Age</b></td><td>{$user_age}</td></tr>
  <tr><td><b>Gender</b></td><td>{$user_gender}</td></tr>
  <tr><td><b>Phone</b></td><td>{$user_phone}</td></tr>
  <tr><td><b>Email</b></td><td>{$user_email}</td></tr>
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

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Ln(4);

// =================================================
// MGA DAPAT GAWIN (Bulleted Section)
// =================================================
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'MGA DAPAT GAWIN', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);

$bullets = [
    "Hugasan ang sugat gamit ang sabon at umaagos na tubig sa loob ng 10 minuto.",
    "Lagyan ng povidone iodine ang sugat, huwag lagyan ng ointment o takpan ng masikip ang sugat.",
    "TANDAAN: Huwag lagyan ng bawang, suka o toothpaste ang sugat. Hindi ito nakakagamot at baka magdulot pa ng tetanus.",
    "Dalhin kaagad ang pasyente sa malapit na pagamutan tulad ng LFG Anti-Rabis and Animal Bite Center sa Sariaya, Quezon.",
    "Hulihin ang aso at obserbahan ito ng 14 araw. Kapag nagpakita ng senyales ng rabies (naglalaway o nagwawala), dalhin ito sa nakatakdang ospital (tulad ng RITM) para ma-eksamen ang utak ng hayop."
];

foreach ($bullets as $b) {
    $pdf->MultiCell(0, 6, "• " . $b, 0, 'L', false, 1);
}

$pdf->Ln(4);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0, 6, "Report generated on: {$generated_date}", 0, 1, 'L');

// Output
$filename = "patient_" . strtolower(str_replace(' ', '_', $full_name)) . "_{$patient_id}.pdf";
$pdf->Output($filename, 'I');
exit;
