<?php
// dashboard/patients/export_excel.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../assets/db/db.php';

// Allow only admin or staff
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    die("Unauthorized.");
}

// Fetch patients with correct user info
$result = $conn->query("
    SELECT 
        p.patient_id,
        u.first_name,
        u.last_name,
        u.age,
        u.gender,
        u.address,
        u.email,
        u.phone_number,
        b.animal_name,
        p.type_of_bite,
        c.category_name,
        v.brand_name AS vaccine_brand,
        v.generic_name AS vaccine_generic,
        p.report_id
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.user_id
    LEFT JOIN biting_animal b ON b.biting_animal_id = p.biting_animal_id
    LEFT JOIN category c ON c.category_id = p.category_id
    LEFT JOIN anti_ravies_vaccine v ON v.anti_ravies_vaccine_id = p.anti_ravies_vaccine_id
    ORDER BY p.patient_id DESC
");

// File name
$filename = "patients_list_" . date("Y-m-d") . ".xls";

// Headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Start table
echo "<table border='1'>";
echo "
<tr>
    <th>Patient ID</th>
    <th>First Name</th>
    <th>Last Name</th>
    <th>Age</th>
    <th>Gender</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Address</th>
    <th>Animal</th>
    <th>Bite Type</th>
    <th>Category</th>
    <th>Vaccine</th>
    <th>Report #</th>
</tr>
";

// Rows
while ($row = $result->fetch_assoc()) {

    $vaccine = $row['vaccine_brand'];
    if (!empty($row['vaccine_generic'])) {
        $vaccine .= " / " . $row['vaccine_generic'];
    }

    echo "<tr>";
    echo "<td>{$row['patient_id']}</td>";
    echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['age']) . "</td>";
    echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['address']) . "</td>";
    echo "<td>" . htmlspecialchars($row['animal_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['type_of_bite']) . "</td>";
    echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
    echo "<td>" . htmlspecialchars($vaccine) . "</td>";
    echo "<td>" . ($row['report_id'] ? '#'.$row['report_id'] : '') . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
?>
