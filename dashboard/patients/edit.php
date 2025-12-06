<?php
// /dashboard/patients/edit.php
include '../../layouts/head.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PatientsBase = 'dashboard/patients';

// SECURITY
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}



// Patient ID
$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid patient ID."));
    exit;
}

/* --------------------------------------------------
   FETCH PATIENT + USER
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT p.*, 
           u.first_name, u.last_name, u.age, u.gender, u.address,
           u.email, u.phone_number
    FROM patients p
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("Location: index.php?error=" . urlencode("Patient not found."));
    exit;
}

/* --------------------------------------------------
   LOAD DROPDOWNS
-------------------------------------------------- */
$biting_animals = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
$anti_vaccines = $conn->query("SELECT anti_ravies_vaccine_id, brand_name, generic_name FROM anti_ravies_vaccine ORDER BY brand_name ASC");

$reports = $conn->query("
    SELECT r.report_id,
           CONCAT(u.first_name,' ',u.last_name) AS reporter_name,
           b.barangay_name
    FROM reports r
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
    ORDER BY r.report_id DESC
");

$success_msg = "";
$error_msg = "";

/* --------------------------------------------------
   PROCESS UPDATE
-------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type_of_bite = trim($_POST['type_of_bite']);
    $category_id = intval($_POST['category_id']);
    $animal_state_after_bite = trim($_POST['animal_state_after_bite']);
    $body_part = trim($_POST['body_part']);
    $biting_animal_id = intval($_POST['biting_animal_id']);
    $washing = trim($_POST['washing']);
    $is_rig = isset($_POST['is_rig']) ? 1 : 0;
    $anti_ravies_vaccine_id = intval($_POST['anti_ravies_vaccine_id']);
    $route = trim($_POST['route']);
    $remarks = trim($_POST['remarks']);
    $report_id = intval($_POST['report_id']);

    if ($type_of_bite === '' || $biting_animal_id === 0 || $category_id === 0) {
        $error_msg = "Please fill Type of bite, Animal type and Category.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE patients SET
                    type_of_bite = ?,
                    category_id = ?,
                    animal_state_after_bite = ?,
                    body_part = ?,
                    biting_animal_id = ?,
                    washing = ?,
                    is_rig = ?,
                    anti_ravies_vaccine_id = ?,
                    route = ?,
                    remarks = ?,
                    report_id = ?
                WHERE patient_id = ?
            ");

            $stmt->bind_param(
                "sissisiissii",
                $type_of_bite,
                $category_id,
                $animal_state_after_bite,
                $body_part,
                $biting_animal_id,
                $washing,
                $is_rig,
                $anti_ravies_vaccine_id,
                $route,
                $remarks,
                $report_id,
                $patient_id
            );


            $stmt->execute();
            $stmt->close();

            $success_msg = "Patient updated successfully!";

            echo "<script>
                    setTimeout(() => {
                        window.location.href='" . $assetBase . "/dashboard/patients/index.php?success=1';
                    }, 1000);
                  </script>";

        } catch (Exception $e) {
            $error_msg = "Error updating patient: " . $e->getMessage();
        }
    }
}

?>

<div class="grid grid-cols-12 gap-x-6">
    <div class="col-span-12">
        <div class="card">
            <div class="card-header flex justify-between items-center">
                <h5>Edit Patient</h5>
                <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="btn btn-outline-secondary">← Back</a>
            </div>

            <div class="card-body p-5">

                <?php if ($error_msg): ?>
                    <div class="mb-4 px-4 py-3 bg-red text-white rounded"><?= $error_msg ?></div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="mb-4 px-4 py-3 bg-success text-white rounded"><?= $success_msg ?></div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">

                    <div class="grid grid-cols-12 gap-3">

                        <!-- READONLY USER INFO -->
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>" readonly>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($patient['email']) ?>" readonly>
                        </div>

                        <div class="col-span-4">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($patient['age']) ?>" readonly>
                        </div>

                        <div class="col-span-4">
                            <label class="form-label">Gender</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($patient['gender']) ?>" readonly>
                        </div>

                        <div class="col-span-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" readonly><?= htmlspecialchars($patient['address']) ?></textarea>
                        </div>

                        <!-- MEDICAL FIELDS -->

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Type of Bite</label>
                            <select name="type_of_bite" class="form-control">
                                <option value="">Select</option>
                                <option <?= $patient['type_of_bite'] == 'Bite' ? 'selected' : '' ?>>Bite</option>
                                <option <?= $patient['type_of_bite'] == 'Scratch' ? 'selected' : '' ?>>Scratch</option>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">Select</option>
                                <?php while ($c = $categories->fetch_assoc()): ?>
                                    <option value="<?= $c['category_id'] ?>" <?= $patient['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Animal Type</label>
                            <select name="biting_animal_id" class="form-control">
                                <option value="">Select</option>
                                <?php while ($a = $biting_animals->fetch_assoc()): ?>
                                    <option value="<?= $a['biting_animal_id'] ?>" <?= $patient['biting_animal_id'] == $a['biting_animal_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['animal_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Animal State After Bite</label>
                            <select name="animal_state_after_bite" class="form-control">
                                <option value="">Select</option>
                                <?php 
                                $states = ['Healthy','Sick','Dead','Killed','Unlocated'];
                                foreach ($states as $s): ?>
                                    <option value="<?= $s ?>" <?= $patient['animal_state_after_bite'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Body Part</label>
                            <input type="text" name="body_part" class="form-control" value="<?= htmlspecialchars($patient['body_part']) ?>">
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Washing</label>
                            <select name="washing" class="form-control">
                                <option value="">Select</option>
                                <option <?= $patient['washing'] == 'Cleaned/Washed' ? 'selected' : '' ?>>Cleaned/Washed</option>
                                <option <?= $patient['washing'] == 'Non-Cleaned/Washed' ? 'selected' : '' ?>>Non-Cleaned/Washed</option>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Is RIG?</label>
                            <label class="inline-flex items-center mt-2">
                                <input type="checkbox" name="is_rig" value="1" <?= $patient['is_rig'] ? 'checked' : '' ?>>
                                <span class="ml-2">Yes</span>
                            </label>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Anti-Rabies Vaccine</label>
                            <select name="anti_ravies_vaccine_id" class="form-control">
                                <option value="0">None</option>
                                <?php while ($v = $anti_vaccines->fetch_assoc()): ?>
                                    <option value="<?= $v['anti_ravies_vaccine_id'] ?>" 
                                        <?= $patient['anti_ravies_vaccine_id'] == $v['anti_ravies_vaccine_id'] ? 'selected' : '' ?>>
                                        <?= $v['brand_name'] . " / " . $v['generic_name'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Route</label>
                            <select name="route" class="form-control">
                                <option value="">Select</option>
                                <?php 
                                $routes = ['Intramuscular','Subcutaneous','Intravenous','Intradermal'];
                                foreach ($routes as $r): ?>
                                    <option value="<?= $r ?>" <?= $patient['route'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-span-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($patient['remarks']) ?></textarea>
                        </div>

                        <div class="col-span-12">
                            <label class="form-label">Link to Report (Optional)</label>
                            <select name="report_id" class="form-control">
                                <option value="0">No linked report</option>
                                <?php while ($r = $reports->fetch_assoc()): ?>
                                    <option value="<?= $r['report_id'] ?>" <?= $patient['report_id'] == $r['report_id'] ? 'selected' : '' ?>>
                                        Report #<?= $r['report_id'] ?> — <?= htmlspecialchars($r['reporter_name']) ?> (<?= htmlspecialchars($r['barangay_name']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                    </div>

                    <div class="flex gap-3 mt-4">
                        <button class="btn btn-primary px-5">Save Changes</button>
                        <a href="<?= $assetBase ?>/dashboard/patients/schedule.php?id=<?= $patient_id ?>" class="btn btn-warning">
                            Edit Vaccination Schedule →
                        </a>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>
