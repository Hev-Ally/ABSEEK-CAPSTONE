<?php
// dashboard/patients/load_patients.php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../assets/db/db.php';

// SECURITY: allow only admin/staff
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    echo json_encode(["rows" => []]);
    exit;
}

/* ------------------------------
   Pagination Inputs
------------------------------ */
$page     = intval($_GET['page'] ?? 1);
$per_page = intval($_GET['per_page'] ?? 10);

$offset = ($page - 1) * $per_page;

/* ------------------------------
   Filter Inputs
------------------------------ */
$q        = trim($_GET['q'] ?? '');
$animal   = trim($_GET['animal'] ?? '');
$category = trim($_GET['category'] ?? '');
$vaccine  = trim($_GET['vaccine'] ?? '');

/* ------------------------------
   Base Query
------------------------------ */
$sql = "
SELECT
    p.patient_id,
    p.biting_animal_id,
    p.category_id,
    p.type_of_bite,
    p.anti_ravies_vaccine_id,
    p.report_id,

    -- USER INFO
    u.first_name,
    u.last_name,
    u.age,
    u.gender,
    u.address,
    u.email,
    u.phone_number,

    -- ANIMAL
    ba.animal_name,

    -- CATEGORY
    c.category_name,

    -- VACCINE
    v.brand_name AS vaccine_brand,
    v.generic_name AS vaccine_generic

FROM patients p
LEFT JOIN users u ON u.user_id = p.user_id
LEFT JOIN biting_animal ba ON ba.biting_animal_id = p.biting_animal_id
LEFT JOIN category c ON c.category_id = p.category_id
LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
WHERE 1
";

/* ------------------------------
   Filters
------------------------------ */
$params = [];
$types  = "";

if ($q !== "") {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ? OR u.address LIKE ?) ";
    $wild = "%{$q}%";
    $params = array_merge($params, [$wild, $wild, $wild, $wild, $wild]);
    $types .= "sssss";
}

if ($animal !== "") {
    $sql .= " AND p.biting_animal_id = ? ";
    $params[] = $animal;
    $types .= "i";
}

if ($category !== "") {
    $sql .= " AND p.category_id = ? ";
    $params[] = $category;
    $types .= "i";
}

if ($vaccine !== "") {
    $sql .= " AND p.anti_ravies_vaccine_id = ? ";
    $params[] = $vaccine;
    $types .= "i";
}

/* ------------------------------
   Sorting + Limit
------------------------------ */
$sql .= " ORDER BY p.patient_id DESC LIMIT ? OFFSET ? ";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

/* ------------------------------
   Execute
------------------------------ */
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

$stmt->close();

echo json_encode([
    "rows" => $rows
]);
exit;
