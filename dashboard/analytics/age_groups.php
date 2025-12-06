<?php
// age_groups.php — Age Group Trends (users.age used)
ini_set('display_errors', 1);
error_reporting(E_ALL);


require_once '../../assets/db/db.php';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$from_dt = ($from) ? date('Y-m-d', strtotime($from)) : null;
$to_dt   = ($to)   ? date('Y-m-d', strtotime($to)) : null;
if ($to_dt) $to_dt = date('Y-m-d', strtotime($to_dt . ' +1 day'));

$dateFilter = "";
$params = [];
$types = "";
if ($from_dt && $to_dt) {
  $dateFilter = "AND bt.date_reported >= ? AND bt.date_reported < ?";
  $params = [$from_dt, $to_dt];
  $types = "ss";
} elseif ($from_dt) {
  $dateFilter = "AND bt.date_reported >= ?";
  $params = [$from_dt];
  $types = "s";
} elseif ($to_dt) {
  $dateFilter = "AND bt.date_reported < ?";
  $params = [$to_dt];
  $types = "s";
}

// Age buckets: Children (0–12), Teens (13–17), Adults (18–59), Seniors (60+)
$groups = [
  'Children (0-12)' => 'users.age BETWEEN 0 AND 12',
  'Teens (13-17)'   => 'users.age BETWEEN 13 AND 17',
  'Adults (18-59)'  => 'users.age BETWEEN 18 AND 59',
  'Seniors (60+)'   => 'users.age >= 60'
];

$labels = array_keys($groups);
$counts = array_fill(0, count($groups), 0);

// total per group
try {
  // build unioned query to count per bucket
  $sqlParts = [];
  $binds = [];
  $bindTypes = "";
  foreach ($groups as $label => $cond) {
    $sqlParts[] = "SELECT '{$label}' as label, COUNT(*) as cnt
      FROM bites bt
      JOIN reports r ON r.report_id = bt.report_id
      JOIN users ON users.user_id = r.user_id
      WHERE {$cond} {$dateFilter}";
  }
  $sql = implode(" UNION ALL ", $sqlParts);
  $stmt = $conn->prepare($sql);
  if ($types) {
    // bind each part's date params repeated for each unioned SELECT
    $repeat = substr_count($sql, $dateFilter);
    $args = [];
    $allTypes = "";
    for ($i = 0; $i < $repeat; $i++) {
      $allTypes .= $types;
      foreach ($params as $p) $args[] = $p;
    }
    // bind via call_user_func_array
    if ($allTypes) {
      $bind_names[] = & $allTypes;
      foreach ($args as $i => $v) $bind_names[] = & $args[$i];
      call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $idx = array_search($r['label'], $labels);
    if ($idx !== false) $counts[$idx] = (int)$r['cnt'];
  }
  $stmt->close();
} catch (Exception $e) {
  // fallback zeroes
  $counts = array_fill(0, count($groups), 0);
}

// Monthly time-series per age-group (last 12 months or all-time aggregated by month)
$seriesMonths = [];
$seriesData = []; // ageGroupIndex => [counts per month]

try {
  // fetch distinct year-months sorted
  $sqlMonths = "SELECT DISTINCT DATE_FORMAT(bt.date_reported, '%Y-%m') AS ym
                FROM bites bt
                JOIN reports r ON r.report_id = bt.report_id
                JOIN users ON users.user_id = r.user_id
                " . ($dateFilter ? "WHERE bt.date_reported >= ? AND bt.date_reported < ?" : "") . "
                ORDER BY ym ASC";
  $stmt = $conn->prepare($sqlMonths);
  if ($types) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $seriesMonths[] = $r['ym'];
  $stmt->close();

  if (count($seriesMonths) > 0) {
    // initialize seriesData
    foreach ($labels as $i => $lbl) $seriesData[$i] = array_fill(0, count($seriesMonths), 0);

    // for each month, compute counts per group
    foreach ($seriesMonths as $midx => $ym) {
      $start = $ym . "-01";
      $end = date('Y-m-d', strtotime("$start +1 month"));
      foreach ($groups as $i_label => $cond) {
        // find index
        $idx = array_search($i_label, $labels);
        $sqlCount = "SELECT COUNT(*) AS cnt
                     FROM bites bt
                     JOIN reports r ON r.report_id = bt.report_id
                     JOIN users ON users.user_id = r.user_id
                     WHERE {$cond} AND bt.date_reported >= ? AND bt.date_reported < ?";
        $s = $conn->prepare($sqlCount);
        $s->bind_param('ss', $start, $end);
        $s->execute();
        $c = (int)$s->get_result()->fetch_assoc()['cnt'] ?? 0;
        $s->close();
        $seriesData[$idx][$midx] = $c;
      }
    }
  }
} catch (Exception $e) {
  $seriesMonths = [];
  $seriesData = [];
}
include __DIR__ . '/../../layouts/head.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js" />
<div class="">
  <h3 class="mb-4">👩‍👧‍👦 Age Group Trends</h3>

  <form method="GET" class="mb-4 flex flex-col md:flex-row gap-3 md:items-end">
    <div>
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    </div>
    <div>
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    </div>
    <div class="flex" style="gap:20px;">
      <button class="btn btn-primary">Apply</button>
      <a class="btn btn-outline-secondary" href="<?= $_SERVER['PHP_SELF'] ?>">Reset</a>
    </div>
  </form>

  <div class="grid grid-cols-12 gap-6">
    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4">
        <h4 class="mb-3">Total by Age Group</h4>
        <canvas id="ageGroupChart" style="height:320px;"></canvas>
      </div>
    </div>

    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4">
        <h4 class="mb-3">Monthly Trend by Age Group</h4>
        <?php if (count($seriesMonths) === 0): ?>
          <div class="text-gray-500 p-6">No monthly time series available for selected range.</div>
        <?php else: ?>
          <canvas id="ageMonthlyChart" style="height:320px;"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../layouts/footer-block.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ageLabels = <?= json_encode($labels) ?>;
const ageCounts = <?= json_encode($counts) ?>;
const months = <?= json_encode($seriesMonths) ?>;
const seriesData = <?= json_encode(array_values($seriesData)) ?>;

// Age group chart
if (ageLabels.length) {
  new Chart(document.getElementById('ageGroupChart'), {
    type: 'bar',
    data: { labels: ageLabels, datasets: [{ label: 'Cases', data: ageCounts, borderWidth:1 }] },
    options: { responsive:true, plugins:{legend:{display:false}} }
  });
}

// Monthly stacked lines for each age group if available
if (months && months.length) {
  const datasets = seriesData.map((arr, i) => ({
    label: ageLabels[i],
    data: arr,
    fill: false,
    tension: 0.2
  }));
  new Chart(document.getElementById('ageMonthlyChart'), {
    type: 'line',
    data: { labels: months, datasets },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
  });
}
</script>
