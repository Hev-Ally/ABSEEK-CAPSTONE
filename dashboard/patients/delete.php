<?php
header("Content-Type: application/json");
session_start();

require_once "../../assets/db/db.php"; // adjust if needed

// Access control
if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ['admin','staff'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Validate patient_id
if (!isset($_POST["patient_id"]) || empty($_POST["patient_id"])) {
    echo json_encode(["success" => false, "message" => "Missing patient ID"]);
    exit;
}

$patient_id = intval($_POST["patient_id"]);

try {
    // Fetch schedule_id linked to patient
    $stmt = $conn->prepare("SELECT schedule_id FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $patient = $res->fetch_assoc();

    if (!$patient) {
        echo json_encode(["success" => false, "message" => "Patient not found"]);
        exit;
    }

    $schedule_id = intval($patient["schedule_id"] ?? 0);

    // Begin transaction
    $conn->begin_transaction();

    // Delete patient record
    $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    // Delete associated schedule
    if ($schedule_id > 0) {
        $stmt = $conn->prepare("DELETE FROM schedule WHERE schedule_id = ?");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
    }

    $conn->commit();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
