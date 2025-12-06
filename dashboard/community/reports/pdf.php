<?php
// dashboard/reports/pdf.php
if (session_status() === PHP_SESSION_NONE) session_start();

$allowed = ['community_user', 'admin', 'staff'];

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    header("HTTP/1.1 403 Forbidden");
    die("Unauthorized.");
}

require_once "../../../assets/db/db.php";

$report_id = intval($_GET["id"] ?? 0);
if ($report_id <= 0) die("Invalid report ID");

// ==========================
// Fetch Report Data (WITH DATE_REPORTED)
// ==========================
$stmt = $conn->prepare("
    SELECT r.*, 
           CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
           u.phone_number, 
           u.email,
           u.address,
           ba.animal_name,
           c.category_name,
           b2.barangay_name,
           bt.date_reported,
           bt.description AS bite_description
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN biting_animal ba ON r.biting_animal_id = ba.biting_animal_id
    LEFT JOIN category c ON r.category_id = c.category_id
    LEFT JOIN barangay b2 ON r.barangay_id = b2.barangay_id
    LEFT JOIN bites bt ON bt.report_id = r.report_id
    WHERE r.report_id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) die("Report not found");

// ==========================
// Fetch Photos
// ==========================
$stmt = $conn->prepare("SELECT filename FROM photos WHERE report_id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ==========================
// Sanitize Helper
// ==========================
function h($v) { return htmlspecialchars($v ?? ""); }

$generated_date = date("F j, Y");

// Format date_reported
$date_reported_display = $report['date_reported']
    ? date("F j, Y", strtotime($report['date_reported']))
    : "N/A";

// ==========================
// Load TCPDF
// ==========================
$tcpdfPath = __DIR__ . '/../../../assets/libs/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) die("TCPDF not found at $tcpdfPath");
require_once $tcpdfPath;

$pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->setPrintFooter(false);
$pdf->setPrintHeader(false);
$pdf->AddPage();

// ==========================
// Header
// ==========================
$logoFile = __DIR__ . "/../../../assets/images/logo-white.jpg";
$logoWidth = 40;
$textWidth = 140;
$headerY = 12;

if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 15, $headerY, $logoWidth, 0, "", "", "", false, 300);
}

$textX = 15 + $logoWidth + 8;

$pdf->SetXY($textX, $headerY);
$pdf->SetFont("helvetica", "B", 32);
$pdf->Cell($textWidth, 14, "LFG", 0, 1, "L");

$pdf->SetX($textX);
$pdf->SetFont("helvetica", "B", 26);
$pdf->Cell($textWidth, 12, "Animal Bite Center", 0, 1, "L");

// Badge
$badgeY = $headerY + 26;
$pdf->SetXY($textX, $badgeY);
$pdf->SetFont("helvetica", "B", 11);
$pdf->SetFillColor(50, 115, 220);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($textWidth, 10, "POST EXPOSURE PROPHYLAXIS (PEP) DOCUMENT", 0, 1, "L", true);

// Contact
$pdf->SetX($textX);
$pdf->SetFont("helvetica", "", 10);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell($textWidth, 6, "Sariaya Branch (Inside Briones Clinic)", 0, 1, "L");

// Divider
$dividerY = $badgeY + 22;
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $dividerY, 195, $dividerY);

$pdf->SetY($dividerY + 6);

// ==========================
// Body HTML 
// ==========================
$html = <<<HTML
<style>
.section-title { background:#f3f4f6; padding:6px 8px; font-weight:bold; margin-top:10px; font-size:12px; }
table.info { width:100%; border-collapse:collapse; margin-top:6px; font-size:11px; }
table.info td { padding:4px 6px; vertical-align:top; }
.muted { color:#666; font-size:10px; }
</style>

<div class="section-title">Reporter Information</div>
<table class="info">
  <tr><td width="30%"><b>Reporter Name</b></td><td>{$report['reporter_name']}</td></tr>
  <tr><td><b>Phone</b></td><td>{$report['phone_number']}</td></tr>
  <tr><td><b>Email</b></td><td>{$report['email']}</td></tr>
  <tr><td><b>Address</b></td><td class="muted">{$report['address']}</td></tr>
</table>

<div class="section-title">Report Details</div>
<table class="info">
  <tr><td width="30%"><b>Type of Bite</b></td><td>{$report['type_of_bite']}</td></tr>
  <tr><td><b>Animal</b></td><td>{$report['animal_name']}</td></tr>
  <tr><td><b>Category</b></td><td>{$report['category_name']}</td></tr>
  <tr><td><b>Barangay</b></td><td>{$report['barangay_name']}</td></tr>
  <tr><td><b>Status</b></td><td>{$report['status']}</td></tr>
  <tr><td><b>Date Reported</b></td><td>{$date_reported_display}</td></tr>
  <tr><td><b>Description</b></td><td>{$report['bite_description']}</td></tr>
  <tr><td><b>Latitude</b></td><td>{$report['latitud']}</td></tr>
  <tr><td><b>Longitude</b></td><td>{$report['longhitud']}</td></tr>
</table>
HTML;

$pdf->writeHTML($html, true, false, true, false, "");

// ==========================
// Photos Section
// ==========================
$pdf->Ln(3);

$pdf->SetFont("helvetica", "B", 12);
$pdf->Cell(0, 8, "Report Photos", 0, 1, "L");
$pdf->Ln(2);

if (count($photos) === 0) {
    $pdf->SetFont("helvetica", "", 10);
    $pdf->Cell(0, 6, "No photos uploaded.", 0, 1);
} else {
    foreach ($photos as $p) {
        $filePath = __DIR__ . "/../../../uploads/" . $p["filename"];
        if (file_exists($filePath)) {
            $pdf->Image($filePath, "", "", 60, 60, "", "", "", false, 150);
            $pdf->Ln(65);
        }
    }
}

// ==========================
// Footer
// ==========================
$pdf->Ln(5);
$pdf->SetFont("helvetica", "", 9);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 6, "Generated on: $generated_date", 0, 1, "L");

// ==========================
// Output
// ==========================
$filename = "report_" . $report_id . ".pdf";
$pdf->Output($filename, "I");
exit;
?>
