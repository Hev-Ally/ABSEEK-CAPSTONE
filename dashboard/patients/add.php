<?php
// /dashboard/patients/add.php

// Make sure DB loads even during AJAX
require_once '../../assets/db/db.php';

$PatientsBase = 'dashboard/patients';
$action = $_GET['action'] ?? null;

/*
|--------------------------------------------------------------------------
| AJAX MODE — NO LAYOUT, NO MENU, NO FOOTER
|--------------------------------------------------------------------------
*/
if ($action === 'search_user' || $action === 'get_user') {
    header('Content-Type: application/json; charset=utf-8');

    /* SEARCH USERS */
    if ($action === 'search_user') {
        $q = trim($_GET['q'] ?? '');
        $out = [];

        if ($q !== '') {
            $like = "%{$q}%";
            $stmt = $conn->prepare("
                SELECT user_id, first_name, last_name, age, gender, address, email, phone_number
                FROM users
                WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone_number LIKE ?
                ORDER BY first_name ASC, last_name ASC
                LIMIT 15
            ");
            $stmt->bind_param('ssss', $like, $like, $like, $like);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($r = $res->fetch_assoc()) {
                $r['label'] = trim($r['first_name'] . ' ' . $r['last_name']);
                $out[] = $r;
            }
            $stmt->close();
        }

        echo json_encode($out);
        exit;
    }

    /* GET USER DETAILS */
    if ($action === 'get_user') {
        $uid = intval($_GET['id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT user_id, first_name, last_name, age, gender, address, email, phone_number
            FROM users WHERE user_id = ?
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        echo json_encode($user);
        exit;
    }

    echo json_encode([]);
    exit;
}

/*
|--------------------------------------------------------------------------
| NORMAL PAGE MODE — FULL LAYOUT
|--------------------------------------------------------------------------
*/
include '../../layouts/head.php';

// Access control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    echo "<script>location.href='" . $assetBase . "/pages/login.php';</script>";
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD DROPDOWN DATA
|--------------------------------------------------------------------------
*/
$biting_animals = $conn->query("SELECT biting_animal_id, animal_name FROM biting_animal ORDER BY animal_name ASC");
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
$anti_vaccines = $conn->query("SELECT anti_ravies_vaccine_id, generic_name, brand_name FROM anti_ravies_vaccine ORDER BY brand_name ASC");

$reports = $conn->query("
  SELECT r.report_id,
    CONCAT(u.first_name,' ',u.last_name) as reporter_name,
    br.barangay_name
  FROM reports r
  LEFT JOIN users u ON u.user_id = r.user_id
  LEFT JOIN barangay br ON br.barangay_id = r.barangay_id
  ORDER BY r.report_id DESC
");

$success_message = '';
$error_message = '';

/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = intval($_POST['user_id'] ?? 0);
    $type_of_bite = trim($_POST['type_of_bite'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $biting_animal_id = intval($_POST['biting_animal_id'] ?? 0);

    $animal_state_after_bite = trim($_POST['animal_state_after_bite'] ?? '');
    $body_part = trim($_POST['body_part'] ?? '');
    $washing = trim($_POST['washing'] ?? '');
    $is_rig = isset($_POST['is_rig']) ? 1 : 0;
    $anti_ravies_vaccine_id = intval($_POST['anti_ravies_vaccine_id'] ?? 0);
    $route = trim($_POST['route'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $report_id = intval($_POST['report_id'] ?? 0);

    // Required validations
    if ($user_id <= 0 || $type_of_bite === '' || $category_id === 0 || $biting_animal_id === 0) {
        $error_message = "Please select a user and fill Type of bite, Animal type and Category.";
    } else {
        try {
            $conn->begin_transaction();

            // Auto generated schedule dates
            $today = new DateTimeImmutable('today');

            $d0_sched  = $today->format('Y-m-d');
            $d3_sched  = $today->modify('+3 days')->format('Y-m-d');
            $d7_sched  = $today->modify('+7 days')->format('Y-m-d');
            $d14_sched = $today->modify('+14 days')->format('Y-m-d');
            $d28_sched = $today->modify('+28 days')->format('Y-m-d');

            $d0_actual = $today->format('Y-m-d');

            // Insert schedule
            $stmt = $conn->prepare("
                INSERT INTO schedule
                    (d0_first_dose_sched, d3_second_dose_sched, d7_third_dose_sched,
                     d14_if_hospitalized_sched, d28_klastdose_sched,
                     d0_first_dose, d3_second_dose, d7_third_dose, d14_if_hospitalized, d28_klastdose)
                VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)
            ");
            $stmt->bind_param('ssssss',
                $d0_sched, $d3_sched, $d7_sched, $d14_sched, $d28_sched,
                $d0_actual
            );
            $stmt->execute();
            $schedule_id = $conn->insert_id;
            $stmt->close();

            // Insert patient
            $stmt = $conn->prepare("
                INSERT INTO patients
                    (user_id, type_of_bite, category_id,
                    animal_state_after_bite, body_part,
                    biting_animal_id, washing, is_rig,
                    anti_ravies_vaccine_id, route,
                    schedule_id, remarks, report_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'isissisissisi',
                $user_id,
                $type_of_bite,
                $category_id,
                $animal_state_after_bite,
                $body_part,
                $biting_animal_id,
                $washing,
                $is_rig,
                $anti_ravies_vaccine_id,
                $route,
                $schedule_id,
                $remarks,
                $report_id
            );


            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success_message = "Patient added successfully!";

            echo "<script>
                    setTimeout(() => {
                        window.location.href = '" . $assetBase . "/dashboard/patients/index.php';
                    }, 900);
                  </script>";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<style>
#userSuggestions div { border-bottom: 1px solid #eee }
#userSuggestions div:last-child { border-bottom: none }
</style>

<div class="grid grid-cols-12 gap-x-6">
  <div class="col-span-12">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h5>Add Patient</h5>
        <a href="<?= $assetBase ?>/dashboard/patients/index.php" class="btn btn-outline-secondary">← Back</a>
      </div>

      <div class="card-body p-5">

        <?php if ($error_message): ?>
          <div class="mb-4 px-4 py-3 bg-red text-white rounded-lg"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
          <div class="mb-4 px-4 py-3 bg-success text-white rounded-lg"><?= $success_message ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
          <div class="grid grid-cols-12 gap-3">

            <!-- USER SEARCH -->
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Search User</label>
              <input type="text" id="userSearch" class="form-control" placeholder="Type name, phone, or email..." autocomplete="off">
              <input type="hidden" name="user_id" id="user_id">
              <div id="userSuggestions" class="bg-white border mt-1 rounded shadow hidden w-full max-h-52 overflow-auto absolute z-[9999]"></div>
            </div>

            <!-- READONLY USER DETAILS -->
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Full Name</label>
              <input type="text" id="full_name_display" class="form-control" readonly>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Phone</label>
              <input type="text" id="phone_display" class="form-control" readonly>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Email</label>
              <input type="text" id="email_display" class="form-control" readonly>
            </div>

            <div class="col-span-4">
              <label class="form-label">Age</label>
              <input type="text" id="age_display" class="form-control" readonly>
            </div>

            <div class="col-span-4">
              <label class="form-label">Gender</label>
              <input type="text" id="gender_display" class="form-control" readonly>
            </div>

            <div class="col-span-12">
              <label class="form-label">Address</label>
              <textarea id="address_display" class="form-control" rows="2" readonly></textarea>
            </div>

            <!-- MEDICAL FIELDS -->
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Type of Bite</label>
              <select name="type_of_bite" class="form-control" required>
                <option value="">Select</option>
                <option value="Bite">Bite</option>
                <option value="Scratch">Scratch</option>
              </select>
            </div>

            <div class="col-span-12 md:col-span-4">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-control" required>
                <option value="">Select</option>
                <?php while($c = $categories->fetch_assoc()): ?>
                  <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Animal Type</label>
              <select name="biting_animal_id" class="form-control" required>
                <option value="">Select</option>
                <?php while($a = $biting_animals->fetch_assoc()): ?>
                  <option value="<?= $a['biting_animal_id'] ?>"><?= htmlspecialchars($a['animal_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Animal State After Bite</label>
              <select name="animal_state_after_bite" class="form-control">
                <option value="">Select</option>
                <option>Healthy</option>
                <option>Sick</option>
                <option>Dead</option>
                <option>Killed</option>
                <option>Unlocated</option>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Body Part</label>
              <input type="text" name="body_part" class="form-control">
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Washing</label>
              <select name="washing" class="form-control">
                <option value="">Select</option>
                <option>Cleaned/Washed</option>
                <option>Non-Cleaned/Washed</option>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Is RIG?</label>
              <label class="inline-flex items-center mt-2">
                <input type="checkbox" name="is_rig" value="1">
                <span class="ml-2">Yes</span>
              </label>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Anti-Rabies Vaccine</label>
              <select name="anti_ravies_vaccine_id" class="form-control">
                <option value="0">Select</option>
                <?php while($v = $anti_vaccines->fetch_assoc()): ?>
                  <option value="<?= $v['anti_ravies_vaccine_id'] ?>">
                    <?= htmlspecialchars($v['brand_name'] . ' / ' . $v['generic_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Route</label>
              <select name="route" class="form-control">
                <option value="">Select</option>
                <option>Intramuscular</option>
                <option>Subcutaneous</option>
                <option>Intravenous</option>
                <option>Intradermal</option>
              </select>
            </div>

            <div class="col-span-12">
              <label class="form-label">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>

            <div class="col-span-12">
              <label class="form-label">Link to Report (Optional)</label>
              <select name="report_id" class="form-control">
                <option value="0">No linked report</option>
                <?php while($r = $reports->fetch_assoc()): ?>
                  <option value="<?= $r['report_id'] ?>">
                    Report #<?= $r['report_id'] ?> — <?= htmlspecialchars($r['reporter_name']) ?> (<?= htmlspecialchars($r['barangay_name']) ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

          </div>

          <button class="btn btn-primary mt-4 px-5">Save Patient</button>

        </form>

      </div>
    </div>
  </div>
</div>

<?php include '../../layouts/footer-block.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const BASE = "<?= $assetBase ?>/dashboard/patients/add.php";

  const userSearch = document.getElementById('userSearch');
  const suggestions = document.getElementById('userSuggestions');
  const userIdInput = document.getElementById('user_id');

  const fullName = document.getElementById('full_name_display');
  const phone = document.getElementById('phone_display');
  const email = document.getElementById('email_display');
  const age = document.getElementById('age_display');
  const gender = document.getElementById('gender_display');
  const address = document.getElementById('address_display');

  let debounce = null;

  function escapeHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  userSearch.addEventListener('input', () => {
    const q = userSearch.value.trim();
    userIdInput.value = '';

    if (debounce) clearTimeout(debounce);

    if (q.length < 2) {
      suggestions.classList.add('hidden');
      return;
    }

    debounce = setTimeout(async () => {
      try {
        const res = await fetch(`${BASE}?action=search_user&q=${encodeURIComponent(q)}`);
        const data = await res.json();

        suggestions.innerHTML = data.map(u => `
          <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 user-suggestion" data-id="${u.user_id}">
            ${escapeHtml(u.label)}
            <div class="text-xs text-gray-500">${escapeHtml(u.email)} • ${escapeHtml(u.phone_number)}</div>
          </div>
        `).join('');

        suggestions.classList.remove('hidden');

      } catch (err) {
        console.error("Autocomplete error", err);
      }

    }, 250);
  });

  suggestions.addEventListener('click', async e => {
    const el = e.target.closest('.user-suggestion');
    if (!el) return;

    const id = el.dataset.id;

    try {
      const res = await fetch(`${BASE}?action=get_user&id=${id}`);
      const u = await res.json();

      userIdInput.value = u.user_id;
      userSearch.value = `${u.first_name} ${u.last_name}`;

      fullName.value = `${u.first_name} ${u.last_name}`;
      phone.value = u.phone_number || '';
      email.value = u.email || '';
      age.value = u.age || '';
      gender.value = u.gender || '';
      address.value = u.address || '';

      suggestions.classList.add('hidden');

    } catch (err) {
      console.error("User load error", err);
    }
  });

});
</script>
