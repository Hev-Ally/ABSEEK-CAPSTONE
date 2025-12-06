<?php
// CLEAN JSON OUTPUT
header("Content-Type: application/json");
ob_clean();
session_start();
require_once '../../assets/db/db.php';

require __DIR__ . '/../../assets/libs/phpmailer/src/Exception.php';
require __DIR__ . '/../../assets/libs/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../../assets/libs/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Access control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing patient ID']);
    exit;
}

$patient_id = intval($_POST['patient_id']);

// Fetch patient + schedule info
$q = $conn->prepare("
    SELECT 
        u.email, u.first_name, u.last_name,
        s.*
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.user_id
    LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
    WHERE p.patient_id = ?
");
$q->bind_param("i", $patient_id);
$q->execute();
$info = $q->get_result()->fetch_assoc();
$q->close();

if (!$info || empty($info['email'])) {
    echo json_encode(['success' => false, 'message' => 'Patient email not found']);
    exit;
}

// Build table for pending schedules
$schedules = [
    "Day 0 (First Dose)" => ["sched" => $info['d0_first_dose_sched'], "done" => $info['d0_first_dose']],
    "Day 3 (Second Dose)" => ["sched" => $info['d3_second_dose_sched'], "done" => $info['d3_second_dose']],
    "Day 7 (Third Dose)" => ["sched" => $info['d7_third_dose_sched'], "done" => $info['d7_third_dose']],
    "Day 14 (If Hospitalized)" => ["sched" => $info['d14_if_hospitalized_sched'], "done" => $info['d14_if_hospitalized']],
    "Day 28 (Last Dose)" => ["sched" => $info['d28_klastdose_sched'], "done" => $info['d28_klastdose']],
];

$tableRows = "";
foreach ($schedules as $label => $row) {
    if (!empty($row['sched']) && empty($row['done'])) {
        $tableRows .= "
        <tr>
            <td style='padding: 6px 10px; border:1px solid #ccc;'>$label</td>
            <td style='padding: 6px 10px; border:1px solid #ccc;'>{$row['sched']}</td>
        </tr>";
    }
}

$body = "
<div style='font-family: Arial; font-size: 15px; color:#333;'>

    <img src='cid:logo' width='250' style='margin-bottom:20px;'>

    <p>Hello <b>{$info['first_name']} {$info['last_name']}</b>,</p>

    <p>This is a reminder of your upcoming vaccination schedule at the <b>LFG Animal Bite Center</b>.</p>

    <p><b>Your Pending Vaccination Schedule:</b></p>

    <table cellpadding='0' cellspacing='0' style='border-collapse: collapse; width:100%; margin-bottom:20px;'>
        <tr style='background:#f5f5f5;'>
            <th style='padding: 8px; border:1px solid #ccc;'>Dose</th>
            <th style='padding: 8px; border:1px solid #ccc;'>Schedule Date</th>
        </tr>
        $tableRows
    </table>

    <p>Please attend on your scheduled date to complete your vaccination.</p>

    <br><br>
    <p>Thank you,<br>
    <b>LFG Animal Bite Center</b></p>
</div>
";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'admin@animal-bite-center.com';
    $mail->Password = 'Popoy4682...';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('admin@animal-bite-center.com', 'LFG Animal Bite Center');
    $mail->addAddress($info['email']);

    $mail->isHTML(true);
    $mail->Subject = "Vaccination Schedule Reminder";

    // EMBED LOGO
    $mail->AddEmbeddedImage('../../assets/images/logo-white.png', 'logo', 'logo-white.png');

    $mail->Body = $body;

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Email sent!']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $mail->ErrorInfo]);
    exit;
}
