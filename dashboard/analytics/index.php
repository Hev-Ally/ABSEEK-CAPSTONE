<?php
// dashboard/analytics/index.php
// Single-page analytics (AI cards + Barangay + Trends + Animals + Age Groups + Compliance)
// Version: Cleaned & responsive (desktop multi-col, mobile single-col)

// load head (session + $conn + assetBase etc)
include realpath(__DIR__ . '/../../layouts/head.php');

// Security
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}

// --- small CSS fallback to guarantee responsive columns even when Tailwind isn't loaded ---
?>
<style>
/* Lightweight grid fallback (won't conflict with Tailwind if present) */
.analytics-row { display:flex; flex-wrap:wrap; gap:1rem; margin:0 -0.5rem; }
.analytics-col { padding:0 0.5rem; box-sizing:border-box; width:100%; }
.card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.06); padding:1rem; }
@media(min-width:768px){
  .md-col-4 { width:33.3333%; }
  .md-col-6 { width:50%; }
  .md-col-12 { width:100%; }
}
.small-muted { font-size:0.85rem; color:#6b7280; }
.kpi { font-size:1.5rem; font-weight:700; margin-top:.5rem; }
.kpi-lg { font-size:2.25rem; font-weight:800; margin-top:.25rem; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { border:1px solid #e5e7eb; padding:.5rem; text-align:left; }
</style>
<?php

// ---------------------------
// Helper: fetch single row safely
// ---------------------------
function fetchOne($conn, $sql, $types = "", $params = []) {
    if ($stmt = $conn->prepare($sql)) {
        if ($params && $types !== "") {
            // bind dynamically
            $bind_names[] = $types;
            for ($i=0; $i<count($params); $i++) {
                $bind_names[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?? [];
    }
    return [];
}

// ---------------------------
// 1) AI Prediction Cards
// ---------------------------

// top barangay last 30 days
$topBarangay = fetchOne($conn, "
    SELECT b.barangay_name, COUNT(*) AS total
    FROM bites bt
    LEFT JOIN barangay b ON b.barangay_id = bt.barangay_id
    WHERE bt.date_reported >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY bt.barangay_id
    ORDER BY total DESC
    LIMIT 1
");

// YOY predicted next year
$yearNow = date("Y");
$thisYear = fetchOne($conn, "SELECT COUNT(*) AS total FROM bites WHERE YEAR(date_reported) = ?", "s", [$yearNow]);
$lastYear = fetchOne($conn, "SELECT COUNT(*) AS total FROM bites WHERE YEAR(date_reported) = ?", "s", [strval($yearNow - 1)]);
$tTotal = intval($thisYear['total'] ?? 0);
$lTotal = intval($lastYear['total'] ?? 0);
$growthRate = ($lTotal > 0) ? ($tTotal - $lTotal) / max(1,$lTotal) : 0;
$predictedNextYear = round($tTotal * (1 + $growthRate));

// predicted barangay next month (3-month average)
$predictionBarangay = fetchOne($conn, "
    SELECT b.barangay_name, AVG(cnt) AS avg_cases FROM (
        SELECT barangay_id, COUNT(*) AS cnt
        FROM bites
        WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY barangay_id, MONTH(date_reported)
    ) t
    LEFT JOIN barangay b ON b.barangay_id = t.barangay_id
    ORDER BY avg_cases DESC LIMIT 1
");

// fastest rising animal (30 days)
$fastAnimal = fetchOne($conn, "
    SELECT COALESCE(ba.animal_name, 'Unknown') AS animal_name, COUNT(*) AS total
    FROM bites bt
    LEFT JOIN reports r ON r.report_id = bt.report_id
    LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
    WHERE bt.date_reported >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY ba.animal_name
    ORDER BY total DESC LIMIT 1
");

// peak day
$peakDay = fetchOne($conn, "
    SELECT DAYNAME(date_reported) AS day, COUNT(*) AS total
    FROM bites
    GROUP BY DAYNAME(date_reported)
    ORDER BY total DESC LIMIT 1
");

// most at-risk age group last 60 days
$ageGroup = fetchOne($conn, "
    SELECT age_group, total FROM (
      SELECT 
        CASE 
          WHEN u.age BETWEEN 0 AND 12 THEN 'Children'
          WHEN u.age BETWEEN 13 AND 17 THEN 'Teens'
          WHEN u.age BETWEEN 18 AND 59 THEN 'Adults'
          ELSE 'Seniors'
        END AS age_group,
        COUNT(*) AS total
      FROM bites bt
      LEFT JOIN reports r ON r.report_id = bt.report_id
      LEFT JOIN users u ON u.user_id = r.user_id
      WHERE bt.date_reported >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
      GROUP BY age_group
      ORDER BY total DESC
    ) t LIMIT 1
");

// ---------------------------
// 2) Date filter parsing (used by some sections)
// ---------------------------
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$from_dt = $from ? date('Y-m-d', strtotime($from)) : null;
$to_dt   = $to ? date('Y-m-d', strtotime($to . ' +1 day')) : null;

// helper for building date clause
$dateClause = "";
$dateParams = [];
$dateTypes = "";
if ($from_dt && $to_dt) {
    $dateClause = "WHERE bt.date_reported >= ? AND bt.date_reported < ?";
    $dateParams = [$from_dt, $to_dt];
    $dateTypes = "ss";
} elseif ($from_dt) {
    $dateClause = "WHERE bt.date_reported >= ?";
    $dateParams = [$from_dt];
    $dateTypes = "s";
} elseif ($to_dt) {
    $dateClause = "WHERE bt.date_reported < ?";
    $dateParams = [$to_dt];
    $dateTypes = "s";
}

// ---------------------------
// Barangay analytics (top + table + zeros + spikes)
// ---------------------------
$topLimit = 10;
$topLabels = $topCounts = [];
$sqlTop = "
    SELECT b.barangay_name, COUNT(*) AS cnt
    FROM bites bt
    JOIN barangay b ON b.barangay_id = bt.barangay_id
    {$dateClause}
    GROUP BY bt.barangay_id
    ORDER BY cnt DESC
    LIMIT ?
";
$stmt = $conn->prepare($sqlTop);
if ($stmt) {
    // bind date params + limit
    if ($dateParams) {
        // build types: dateTypes + 'i'
        $types = $dateTypes . 'i';
        $bind = array_merge($dateParams, [$topLimit]);
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($bind); $i++) $bind_names[] = &$bind[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    } else {
        $stmt->bind_param('i', $topLimit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $topLabels[] = $r['barangay_name']; $topCounts[] = (int)$r['cnt']; }
    $stmt->close();
}

// table rows all barangays counts (respect date filter)
$tableRows = [];
$sqlTable = "SELECT b.barangay_name, COUNT(*) AS cnt FROM bites bt JOIN barangay b ON b.barangay_id = bt.barangay_id " . ($dateClause ? $dateClause : "") . " GROUP BY b.barangay_id ORDER BY cnt DESC";
$stmt = $conn->prepare($sqlTable);
if ($stmt) {
    if ($dateParams) {
        $bind_names = [];
        $bind_names[] = $dateTypes;
        for ($i=0;$i<count($dateParams);$i++) $bind_names[] = &$dateParams[$i];
        call_user_func_array([$stmt,'bind_param'],$bind_names);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $tableRows[] = $r;
    $stmt->close();
}

// barangays with zero cases (in selected range or all-time)
$zeroBarangays = [];
if ($dateClause) {
    $sqlZero = "SELECT b.barangay_name FROM barangay b
                LEFT JOIN (
                  SELECT barangay_id, COUNT(*) AS cnt FROM bites bt {$dateClause} GROUP BY barangay_id
                ) sub ON sub.barangay_id = b.barangay_id
                WHERE sub.cnt IS NULL OR sub.cnt = 0
                ORDER BY b.barangay_name ASC";
    $stmt = $conn->prepare($sqlZero);
    if ($stmt) {
        if ($dateParams) {
            $bind_names = [];
            $bind_names[] = $dateTypes;
            for ($i=0;$i<count($dateParams);$i++) $bind_names[] = &$dateParams[$i];
            call_user_func_array([$stmt,'bind_param'],$bind_names);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $zeroBarangays[] = $r['barangay_name'];
        $stmt->close();
    }
} else {
    $res = $conn->query("SELECT b.barangay_name FROM barangay b LEFT JOIN bites bt ON bt.barangay_id = b.barangay_id WHERE bt.barangay_id IS NULL ORDER BY b.barangay_name ASC");
    while ($r = $res->fetch_assoc()) $zeroBarangays[] = $r['barangay_name'];
}

// ---------------------------
// Monthly & Yearly trends
// ---------------------------
$months = $monthCounts = [];
$res = $conn->query("
    SELECT DATE_FORMAT(date_reported, '%Y-%m') AS ym, COUNT(*) AS total
    FROM bites
    WHERE date_reported IS NOT NULL
    GROUP BY ym
    ORDER BY ym ASC
");
while ($r = $res->fetch_assoc()) { $months[] = $r['ym']; $monthCounts[] = (int)$r['total']; }

$years = $yearCounts = [];
$res = $conn->query("
    SELECT YEAR(date_reported) AS yr, COUNT(*) AS total
    FROM bites
    GROUP BY yr
    ORDER BY yr ASC
");
while ($r = $res->fetch_assoc()) { $years[] = $r['yr']; $yearCounts[] = (int)$r['total']; }

// ---------------------------
// Animal analytics
// ---------------------------
$animalLabels = $animalCounts = $animalMonths = $animalSeries = [];
$res = $conn->query("SELECT COALESCE(ba.animal_name,'Unknown') AS animal_name, COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id GROUP BY r.biting_animal_id ORDER BY cnt DESC");
while ($r = $res->fetch_assoc()) { $animalLabels[] = $r['animal_name']; $animalCounts[] = (int)$r['cnt']; }
$res = $conn->query("SELECT DISTINCT DATE_FORMAT(date_reported,'%Y-%m') AS ym FROM bites ORDER BY ym ASC");
while ($r = $res->fetch_assoc()) $animalMonths[] = $r['ym'];

// top 5 animals series
$topRes = $conn->query("SELECT r.biting_animal_id, COALESCE(ba.animal_name,'Unknown') AS name FROM bites bt JOIN reports r ON r.report_id = bt.report_id LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id GROUP BY r.biting_animal_id ORDER BY COUNT(*) DESC LIMIT 5");
$topList = [];
while ($t = $topRes->fetch_assoc()) $topList[] = $t;
foreach ($topList as $ta) {
    $series = [];
    foreach ($animalMonths as $ym) {
        $start = $ym . '-01';
        $end = date('Y-m-d', strtotime("$start +1 month"));
        $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id WHERE r.biting_animal_id = ? AND bt.date_reported >= ? AND bt.date_reported < ?");
        $s->bind_param('iss', $ta['biting_animal_id'], $start, $end);
        $s->execute();
        $cnt = (int)$s->get_result()->fetch_assoc()['cnt'] ?? 0;
        $s->close();
        $series[] = $cnt;
    }
    $animalSeries[] = ['label'=>$ta['name'],'data'=>$series];
}

// ---------------------------
// Age group analytics
// ---------------------------
$ageGroups = [
    'Children (0-12)' => 'u.age BETWEEN 0 AND 12',
    'Teens (13-17)'   => 'u.age BETWEEN 13 AND 17',
    'Adults (18-59)'  => 'u.age BETWEEN 18 AND 59',
    'Seniors (60+)'   => 'u.age >= 60'
];
$ageLabels = array_keys($ageGroups);
$ageCounts = array_fill(0, count($ageLabels), 0);
$ageMonths = $ageSeries = [];

foreach ($ageGroups as $label => $cond) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id JOIN users u ON u.user_id = r.user_id WHERE {$cond}");
    $stmt->execute();
    $ageCounts[array_search($label, $ageLabels)] = (int)$stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}
$res = $conn->query("SELECT DISTINCT DATE_FORMAT(bt.date_reported,'%Y-%m') AS ym FROM bites bt ORDER BY ym ASC");
while ($r = $res->fetch_assoc()) $ageMonths[] = $r['ym'];
foreach ($ageGroups as $label => $cond) {
    $arr = [];
    foreach ($ageMonths as $ym) {
        $start = $ym . '-01';
        $end = date('Y-m-d', strtotime("$start +1 month"));
        $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id JOIN users u ON u.user_id = r.user_id WHERE {$cond} AND bt.date_reported >= ? AND bt.date_reported < ?");
        $s->bind_param('ss', $start, $end);
        $s->execute();
        $arr[] = (int)$s->get_result()->fetch_assoc()['cnt'] ?? 0;
        $s->close();
    }
    $ageSeries[] = $arr;
}

// ---------------------------
// Compliance (if schedule fields exist)
// ---------------------------
$hasScheduleFields = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM schedule LIKE 'd0_first_dose'");
    if ($col && $col->num_rows > 0) $hasScheduleFields = true;
} catch (Exception $e) { $hasScheduleFields = false; }

$comLabels = $comValues = $comTable = [];
$totalPatients = 0;
if ($hasScheduleFields) {
    $r = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN s.d0_first_dose IS NOT NULL THEN 1 ELSE 0 END) AS d0_done, SUM(CASE WHEN s.d3_second_dose IS NOT NULL THEN 1 ELSE 0 END) AS d3_done, SUM(CASE WHEN s.d7_third_dose IS NOT NULL THEN 1 ELSE 0 END) AS d7_done, SUM(CASE WHEN s.d14_if_hospitalized IS NOT NULL THEN 1 ELSE 0 END) AS d14_done, SUM(CASE WHEN s.d28_klastdose IS NOT NULL THEN 1 ELSE 0 END) AS d28_done FROM patients p LEFT JOIN schedule s ON s.schedule_id = p.schedule_id")->fetch_assoc();
    $totalPatients = intval($r['total'] ?? 0);
    if ($totalPatients > 0) {
        $comLabels = ['Day 0','Day 3','Day 7','Day 14','Day 28'];
        $comValues = [
            round(($r['d0_done'] / $totalPatients) * 100, 1),
            round(($r['d3_done'] / $totalPatients) * 100, 1),
            round(($r['d7_done'] / $totalPatients) * 100, 1),
            round(($r['d14_done'] / $totalPatients) * 100, 1),
            round(($r['d28_done'] / $totalPatients) * 100, 1),
        ];
        foreach ($comLabels as $i=>$lbl) $comTable[] = ['label'=>$lbl,'pct'=>$comValues[$i]];
    }
}

// ---------------------------
// START OUTPUT (HTML)
// ---------------------------
?>
<div style="padding:1rem;">
  <!-- AI Prediction Cards -->
  <div class="grid grid-cols-12 gap-6" style="margin-bottom:1.5rem;">
    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div class="small-muted">AI Prediction · Generated</div>
        <div style="margin-top:.4rem; font-weight:700;">Highest Predictive Cases Next Month</div>
        <div class="kpi-lg"><?= htmlspecialchars($topBarangay['barangay_name'] ?? 'No data') ?></div>
        <div class="small-muted" style="margin-top:.25rem;">Based on last 30 days</div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div class="small-muted">AI Prediction · Generated</div>
        <div style="margin-top:.4rem; font-weight:700;">Total Cases Next Year (projection)</div>
        <div class="kpi-lg"><?= number_format($predictedNextYear) ?> cases</div>
        <div class="small-muted">Projected for <?= $yearNow + 1 ?> (YOY growth)</div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div class="small-muted">AI Prediction · Generated</div>
        <div style="margin-top:.4rem; font-weight:700;">Hotspot Next Month (barangay)</div>
        <div class="kpi"><?= htmlspecialchars($predictionBarangay['barangay_name'] ?? 'No data') ?></div>
        <div class="small-muted">3-month average</div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div style="font-weight:700;">Fastest Rising Animal Bite</div>
        <div class="kpi"><?= htmlspecialchars($fastAnimal['animal_name'] ?? 'No data') ?></div>
        <div class="small-muted">Last 30 days</div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div style="font-weight:700;">Peak Danger Day</div>
        <div class="kpi"><?= htmlspecialchars($peakDay['day'] ?? 'No data') ?></div>
        <div class="small-muted">All-time peak day of week</div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-4">
      <div class="card">
        <div style="font-weight:700;">Most At-Risk Age Group</div>
        <div class="kpi"><?= htmlspecialchars($ageGroup['age_group'] ?? 'No data') ?></div>
        <div class="small-muted">Last 60 days</div>
      </div>
    </div>
  </div>

  <!-- Barangay Analytics + Filter -->
  <div style="margin-bottom:1.5rem;">
    <h3 style="margin:0 0 .5rem 0;">🏘 Barangay-Level Analytics</h3>

    <form method="GET" class="mb-4 flex gap-3 items-end">
      <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=htmlspecialchars($_GET['from']??'')?>"></div>
      <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=htmlspecialchars($_GET['to']??'')?>"></div>
      <div class="flex gap-3"><button class="btn btn-primary">Apply</button> <a class="btn btn-outline-secondary" href="<?= $_SERVER['PHP_SELF'] ?>">Reset</a></div>
    </form>

    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Top <?= $topLimit ?> Barangays</h4>
          <canvas id="topBarangaysChart" height="280"></canvas>
        </div>
      </div>
      <div class="col-span-12 md:col-span-6">
          <div class="card">
            <h4 style="margin:.25rem 0 .5rem 0;">Barangays With Zero Cases</h4>
            <?php if (empty($zeroBarangays)): ?>
              <div class="small-muted">None — every barangay has recorded at least one case in the selected range.</div>
            <?php else: ?>
              <ul style="margin:.5rem 0 0 1rem;">
                <?php foreach ($zeroBarangays as $z): ?><li><?= htmlspecialchars($z) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
      </div>
      <div class="col-span-12 md:col-span-12">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Barangay Counts (table)</h4>
          <div style="max-height:360px; overflow:auto;">
            <table class="table">
              <thead><tr><th>Barangay</th><th style="text-align:right">Cases</th></tr></thead>
              <tbody>
                <?php if (empty($tableRows)): ?>
                  <tr><td colspan="2">No data found.</td></tr>
                <?php else: foreach ($tableRows as $tr): ?>
                  <tr><td><?= htmlspecialchars($tr['barangay_name']) ?></td><td style="text-align:right"><?= intval($tr['cnt']) ?></td></tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      
    </div>
  </div>

  <!-- Trends (Monthly / Yearly) -->
  <div style="margin-bottom:1.5rem;">
    <h3 style="margin:0 0 .5rem 0;">📈 Monthly & Yearly Trends</h3>
    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Monthly Bite Trend</h4>
          <?php if (empty($months)): ?>
            <div class="small-muted">No monthly data available.</div>
          <?php else: ?>
            <canvas id="monthlyTrendChart" height="260"></canvas>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Yearly Trend Comparison</h4>
          <?php if (empty($years)): ?>
            <div class="small-muted">No yearly data available.</div>
          <?php else: ?>
            <canvas id="yearlyTrendChart" height="260"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Animal Analytics -->
  <div style="margin-bottom:1.5rem;">
    <h3 style="margin:0 0 .5rem 0;">🐾 Animal Type Analytics</h3>
    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Distribution by Animal Type</h4>
          <?php if (empty($animalLabels)): ?>
            <div class="small-muted">No data.</div>
          <?php else: ?>
            <canvas id="animalDist" height="260"></canvas>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Monthly Trend (Top animals)</h4>
          <?php if (empty($animalMonths) || empty($animalSeries)): ?>
            <div class="small-muted">No time series available.</div>
          <?php else: ?>
            <canvas id="animalMonthly" height="260"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Age Groups -->
  <div style="margin-bottom:1.5rem;">
    <h3 style="margin:0 0 .5rem 0;">👨‍👩‍👧 Age Group Trends</h3>
    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Total by Age Group</h4>
          <canvas id="ageGroupChart" height="260"></canvas>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6">
        <div class="card">
          <h4 style="margin:.25rem 0 .75rem 0;">Monthly Trend by Age Group</h4>
          <?php if (empty($ageMonths)): ?>
            <div class="small-muted">No monthly data for age groups.</div>
          <?php else: ?>
            <canvas id="ageMonthlyChart" height="260"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Compliance -->
  <div style="margin-bottom:2rem;">
    <h3 style="margin:0 0 .5rem 0;">💉 Vaccination Compliance</h3>
    <div class="card">
      <?php if (!$hasScheduleFields): ?>
        <div class="small-muted">Schedule fields missing — cannot compute compliance.</div>
      <?php elseif ($totalPatients == 0): ?>
        <div class="small-muted">No patients found.</div>
      <?php else: ?>
        <h4 style="margin:.25rem 0 .5rem 0;">Completion Percentage by Dose</h4>
        <canvas id="complianceChart" height="160"></canvas>
        <div style="margin-top:.75rem;">
          <table class="table">
            <thead><tr><th>Dose</th><th style="text-align:right">Percent Completed</th></tr></thead>
            <tbody>
              <?php foreach ($comTable as $row): ?>
                <tr><td><?= htmlspecialchars($row['label']) ?></td><td style="text-align:right"><?= htmlspecialchars($row['pct']) ?>%</td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Chart.js (reliable CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// --- Barangay chart
const topLabels = <?= json_encode($topLabels) ?>;
const topCounts = <?= json_encode($topCounts) ?>;
if (topLabels && topLabels.length) {
  new Chart(document.getElementById('topBarangaysChart'), {
    type: 'bar',
    data: { labels: topLabels, datasets: [{ label: 'Cases', data: topCounts, backgroundColor: 'rgba(59,130,246,0.7)' }] },
    options: { responsive:true, plugins:{legend:{display:false}} }
  });
}

// --- Monthly & Yearly
const monthlyLabels = <?= json_encode($months) ?>;
const monthlyData = <?= json_encode($monthCounts) ?>;
if (monthlyLabels.length) {
  new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: { labels: monthlyLabels, datasets: [{ label:'Bite Incidents', data: monthlyData, fill:true, tension:0.25, backgroundColor:'rgba(99,102,241,0.12)', borderColor:'rgba(99,102,241,1)'}] },
    options: { responsive:true }
  });
}

const yearlyLabels = <?= json_encode($years) ?>;
const yearlyData = <?= json_encode($yearCounts) ?>;
if (yearlyLabels.length) {
  new Chart(document.getElementById('yearlyTrendChart'), {
    type: 'bar',
    data: { labels: yearlyLabels, datasets: [{ label:'Yearly Total', data: yearlyData, backgroundColor:'rgba(16,185,129,0.8)'}] },
    options: { responsive:true }
  });
}

// --- Animal charts
const animalLabels = <?= json_encode($animalLabels) ?>;
const animalCounts = <?= json_encode($animalCounts) ?>;
if (animalLabels.length) {
  new Chart(document.getElementById('animalDist'), {
    type: 'pie',
    data: { labels: animalLabels, datasets: [{ data: animalCounts }] },
    options: { responsive:true }
  });
}

const animalMonths = <?= json_encode($animalMonths) ?>;
const animalSeries = <?= json_encode($animalSeries) ?>;
if (animalMonths.length && animalSeries.length) {
  const datasets = animalSeries.map(s => ({ label: s.label, data: s.data, fill:false, tension:0.2 }));
  new Chart(document.getElementById('animalMonthly'), { type:'line', data:{ labels: animalMonths, datasets }, options:{ responsive:true, plugins:{legend:{position:'bottom'}} }});
}

// --- Age group charts
const ageLabels = <?= json_encode($ageLabels) ?>;
const ageCounts = <?= json_encode($ageCounts) ?>;
if (ageLabels.length) {
  new Chart(document.getElementById('ageGroupChart'), { type:'bar', data:{ labels: ageLabels, datasets:[{ label:'Cases', data: ageCounts }] }, options:{ responsive:true, plugins:{legend:{display:false}} }});
}

const ageMonths = <?= json_encode($ageMonths) ?>;
const ageSeries = <?= json_encode($ageSeries) ?>;
if (ageMonths.length && ageSeries.length) {
  const datasets = ageSeries.map((arr,i) => ({ label: ageLabels[i], data: arr, fill:false, tension:0.2 }));
  new Chart(document.getElementById('ageMonthlyChart'), { type:'line', data:{ labels: ageMonths, datasets }, options:{ responsive:true, plugins:{legend:{position:'bottom'}} }});
}

// --- Compliance
const comLabels = <?= json_encode($comLabels) ?>;
const comValues = <?= json_encode($comValues) ?>;
if (comLabels.length) {
  new Chart(document.getElementById('complianceChart'), { type:'bar', data:{ labels: comLabels, datasets:[{ label:'% Completed', data: comValues, backgroundColor:'rgba(234,88,12,0.8)'}] }, options:{ responsive:true, scales:{ y:{ beginAtZero:true, max:100 } } }});
}
</script>

<?php
// include footer (scripts, closing tags)
include realpath(__DIR__ . '/../../layouts/footer-block.php');
