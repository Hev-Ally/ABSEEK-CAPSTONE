<?php
session_start();
require_once "../../assets/db/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid ID"]);
    exit;
}

$conn->query("DELETE FROM barangay WHERE barangay_id = $id");

echo json_encode(["success" => true]);
