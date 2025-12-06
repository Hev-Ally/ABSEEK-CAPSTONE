

<?php include '../layouts/head.php';?>
<?php
require_once '../assets/db/db.php'; // Include DB connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success_message = '';
$error_message = '';

// Fetch user data
$stmt = $conn->prepare("SELECT phone_number FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$phone_number = $user['phone_number'];

// Handle phone number update form
if (isset($_POST['update_phone']) && !empty($_POST['new_phone'])) {
    $new_phone = $_POST['new_phone'];
    $stmt = $conn->prepare("UPDATE users SET phone_number = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_phone, $user_id);
    $stmt->execute();
    $phone_number = $new_phone;
    $success_message = "Phone number updated successfully. You can now submit a report.";
}

// Handle bite report submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['type_of_bite'])) {
    if (empty($phone_number)) {
        $error_message = "You must update your phone number before submitting a report.";
    } else {
        $type_of_bite = $_POST['type_of_bite'];
        $barangay_id = $_POST['barangay_id'];
        $description = $_POST['description'];

        // Insert into reports
        $stmt = $conn->prepare("INSERT INTO reports (user_id, type_of_bite, barangay_id) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $user_id, $type_of_bite, $barangay_id);
        $stmt->execute();
        $report_id = $stmt->insert_id;
        $stmt->close();

        // Insert into bites (no patient_id for now)
        $dummy_patient_id = 0;
        $stmt = $conn->prepare("INSERT INTO bites (patient_id, report_id, date_reported, description, barangay_id) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->bind_param("iisi", $dummy_patient_id, $report_id, $description, $barangay_id);
        $stmt->execute();
        $stmt->close();

        $success_message = "Bite report submitted successfully!";
    }
}

// Get barangays
$barangays = $conn->query("SELECT * FROM barangay");
?>
        <!-- [ Main Content ] start -->
        <div class="grid grid-cols-12 gap-x-6">
          <div class="col-span-12 xl:col-span-12 md:col-span-12">
            <div class="card table-card">
              <div class="card-header">
                <h5>Report an Animal Bite</h5>
              </div>
              <div class="card-body">
                    
                 <!-- ✅ Display Messages -->
                  <?php if ($error_message): ?>
                      <div class="px-5">
                          <div class="mb-4 p-5 bg-danger-400 bg-red-300 text-black rounded"><?= htmlspecialchars($error_message) ?></div>
                      </div>
                  <?php endif; ?>

                  <?php if ($success_message): ?>
                      <div class="px-5">
                          <div class="mb-4 p-5 bg-success-200 bg-green-400 text-black rounded"><?= htmlspecialchars($success_message) ?></div>
                      </div>
                  <?php endif; ?>

                  <!-- ✅ Phone Number Update Form (if missing) -->
                  <?php if (empty($phone_number)): ?>
                      <form method="POST" class="p-5">
                          <div class="mb-3">
                              <label class="form-label">Phone Number <small>(required before reporting)</small></label>
                              <input type="text" name="new_phone" class="form-control" required>
                          </div>
                          <button type="submit" name="update_phone" class="btn btn-primary">Update Phone Number</button>
                      </form>
                  <?php endif; ?>

                  <!-- ✅ Bite Report Form (only shown if phone number exists) -->
                  <?php if (!empty($phone_number)): ?>
                      <form method="POST" class="p-5">
                          <div class="mb-3">
                              <label class="form-label">Type of Bite</label>
                              <select name="type_of_bite" class="form-control" required>
                                  <option value="dog">Dog</option>
                                  <option value="cat">Cat</option>
                              </select>
                          </div>

                          <div class="mb-3">
                              <label class="form-label">Barangay</label>
                              <select name="barangay_id" class="form-control" required>
                                  <?php while ($row = $barangays->fetch_assoc()): ?>
                                      <option value="<?= $row['barangay_id'] ?>"><?= htmlspecialchars($row['barangay_name']) ?></option>
                                  <?php endwhile; ?>
                              </select>
                          </div>

                          <div class="mb-3">
                              <label class="form-label">Description of Incident</label>
                              <textarea name="description" rows="4" class="form-control" required></textarea>
                          </div>

                          <button type="submit" class="btn btn-success">Submit Report</button>
                      </form>
                  <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->
    <?php include '../layouts/footer-block.php';?>
 
    <!-- Required Js -->
    <script src="../assets/js/plugins/simplebar.min.js"></script>
    <script src="../assets/js/plugins/popper.min.js"></script>
    <script src="../assets/js/icon/custom-icon.js"></script>
    <script src="../assets/js/plugins/feather.min.js"></script>
    <script src="../assets/js/component.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script src="../assets/js/script.js"></script>
    <!-- Leaflet JS -->
    <script src="../assets/js/leaflet/leaflet.js"></script>
    <!-- Leaflet Heat plugin -->
    <script src="../assets/js/leaflet/leaflet-heat.js"></script>

    <div class="floting-button fixed bottom-[50px] right-[30px] z-[1030]">
    </div>

    
    <script>
      layout_change('false');
    </script>
     
    
    <script>
      layout_theme_sidebar_change('dark');
    </script>
    
     
    <script>
      change_box_container('false');
    </script>
     
    <script>
      layout_caption_change('true');
    </script>
     
    <script>
      layout_rtl_change('false');
    </script>
     
    <script>
      preset_change('preset-1');
    </script>
     
    <script>
      main_layout_change('vertical');
    </script>
    

  </body>
  <!-- [Body] end -->
</html>
