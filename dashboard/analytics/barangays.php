<?php
// dashboard/analytics/barangays.php
ini_set('display_errors',1);
error_reporting(E_ALL);

include '../../layouts/head.php'; // loads $conn, $assetBase, session

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$from_dt = $from ? date('Y-m-d', strtotime($from)) : null;
$to_dt   = $to ? date('Y-m-d', strtotime($to . ' +1 day')) : null;

// Build date clause & params
$dateClause = '';
$params = [];
if ($from_dt && $to_dt) { $dateClause = "WHERE bt.date_reported >= ? AND bt.date_reported < ?"; $params = [$from_dt, $to_dt]; }
elseif ($from_dt) { $dateClause = "WHERE bt.date_reported >= ?"; $params = [$from_dt]; }
elseif ($to_dt) { $dateClause = "WHERE bt.date_reported < ?"; $params = [$to_dt]; }

// --- Top barangays ---
$topLimit = 10;
$topLabels = $topCounts = [];
if ($dateClause) {
    $sql = "SELECT b.barangay_name, COUNT(*) AS cnt
            FROM bites bt
            JOIN barangay b ON b.barangay_id = bt.barangay_id
            $dateClause
            GROUP BY bt.barangay_id
            ORDER BY cnt DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { /* fallback */ $stmt = $conn->prepare("SELECT b.barangay_name, COUNT(*) AS cnt FROM bites bt JOIN barangay b ON b.barangay_id = bt.barangay_id GROUP BY bt.barangay_id ORDER BY cnt DESC LIMIT ?"); $stmt->bind_param('i', $topLimit); }
    else {
        if (count($params) === 2) $stmt->bind_param('ssi', $params[0], $params[1], $topLimit);
        elseif (count($params) === 1) $stmt->bind_param('si', $params[0], $topLimit);
        else $stmt->bind_param('i', $topLimit);
    }
} else {
    $sql = "SELECT b.barangay_name, COUNT(*) AS cnt
            FROM bites bt
            JOIN barangay b ON b.barangay_id = bt.barangay_id
            GROUP BY bt.barangay_id
            ORDER BY cnt DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $topLimit);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $topLabels[] = $r['barangay_name']; $topCounts[] = (int)$r['cnt']; }
$stmt->close();

// --- Spike detection (compare recent vs previous) ---
$spikeLabels = $spikeValues = [];
try {
    if ($from_dt && $to_dt) {
        $start = strtotime($from_dt); $end = strtotime($to_dt) - 1;
        $mid = intval(($start + $end) / 2);
        $p0s = date('Y-m-d', $start); $p0e = date('Y-m-d', $mid+1);
        $p1s = date('Y-m-d', $mid+1); $p1e = date('Y-m-d', $end+2);
    } else {
        $p1s = date('Y-m-d', strtotime('-30 days'));
        $p1e = date('Y-m-d', strtotime('+1 day'));
        $p0s = date('Y-m-d', strtotime('-60 days'));
        $p0e = date('Y-m-d', strtotime('-30 days +1 day'));
    }

    $sql = "
      SELECT b.barangay_name,
        SUM(CASE WHEN bt.date_reported >= ? AND bt.date_reported < ? THEN 1 ELSE 0 END) AS p1,
        SUM(CASE WHEN bt.date_reported >= ? AND bt.date_reported < ? THEN 1 ELSE 0 END) AS p0
      FROM bites bt
      JOIN barangay b ON b.barangay_id = bt.barangay_id
      GROUP BY b.barangay_id
      HAVING (p1 > 0 OR p0 > 0)
      ORDER BY (p1 - p0) DESC
      LIMIT 10
    ";
    $s = $conn->prepare($sql);
    $s->bind_param('ssss', $p1s, $p1e, $p0s, $p0e);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) {
        $p0 = (int)$row['p0']; $p1 = (int)$row['p1'];
        $pct = ($p0 === 0) ? ($p1 > 0 ? 999 : 0) : round((($p1 - $p0)/max(1,$p0))*100,1);
        $spikeLabels[] = $row['barangay_name'] . " ({$p0}→{$p1})";
        $spikeValues[] = $pct;
    }
    $s->close();
} catch (Exception $e) { /* ignore */ }

// --- Barangays with zero cases in the selected range (or all-time) ---
$zeroBarangays = [];
if ($dateClause) {
    $sql = "SELECT b.barangay_name FROM barangay b
            LEFT JOIN (
                SELECT barangay_id, COUNT(*) AS cnt FROM bites bt $dateClause GROUP BY barangay_id
            ) sub ON sub.barangay_id = b.barangay_id
            WHERE sub.cnt IS NULL OR sub.cnt = 0
            ORDER BY b.barangay_name ASC";
    $stmt = $conn->prepare($sql);
    if (count($params) === 2) $stmt->bind_param('ss', $params[0], $params[1]);
    elseif (count($params) === 1) $stmt->bind_param('s', $params[0]);
    $stmt->execute();
    $rr = $stmt->get_result();
    while ($z = $rr->fetch_assoc()) $zeroBarangays[] = $z['barangay_name'];
    $stmt->close();
} else {
    $res = $conn->query("SELECT b.barangay_name FROM barangay b LEFT JOIN bites bt ON bt.barangay_id = b.barangay_id WHERE bt.barangay_id IS NULL ORDER BY b.barangay_name ASC");
    while ($r = $res->fetch_assoc()) $zeroBarangays[] = $r['barangay_name'];
}

// --- Data table: all barangays counts ---
$tableRows = [];
$sqlTable = "SELECT b.barangay_name, COUNT(*) AS cnt FROM bites bt JOIN barangay b ON b.barangay_id = bt.barangay_id " . ($dateClause ? $dateClause : "") . " GROUP BY b.barangay_id ORDER BY cnt DESC";
$stmt = $conn->prepare($sqlTable);
if ($dateClause) {
    if (count($params)===2) $stmt->bind_param('ss', $params[0], $params[1]);
    else $stmt->bind_param('s', $params[0]);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $tableRows[] = $r;
$stmt->close();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.2/chart.umd.min.js" integrity="sha512-qXb2NtI9b5kMZCF9YyAzS/fWcGkeWCwrVd34XIU8LTmCeiMe7IwNcR7nWqwFUEp6yP64LhDHF4MN0tE1P7vD4w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<div class="">
  <h3 class="mb-4">🏘 Barangay-Level Trends</h3>

  <form method="GET" class="mb-4 flex flex-col md:flex-row gap-3 md:items-end">
    <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=htmlspecialchars($_GET['from']??'')?>"></div>
    <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=htmlspecialchars($_GET['to']??'')?>"></div>
    <div class="flex gap-3"><button class="btn btn-primary">Apply</button> <a class="btn btn-outline-secondary" href="<?= $_SERVER['PHP_SELF'] ?>">Reset</a></div>
  </form>

  <div class="grid grid-cols-12 gap-6">
    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4"><h4 class="mb-3">Top <?= $topLimit ?> Barangays</h4><canvas id="topBarangaysChart" style="height:320px;"></canvas></div>
    </div>

    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4"><h4 class="mb-3">Recent Spikes</h4><canvas id="spikeChart" style="height:320px;"></canvas></div>
    </div>

    <div class="col-span-12">
      <div class="card p-4">
        <h4 class="mb-3">All Barangays (table)</h4>
        <div class="overflow-auto">
          <table class="table-auto w-full border-collapse">
            <thead><tr><th class="p-2 border">Barangay</th><th class="p-2 border">Cases</th></tr></thead>
            <tbody>
            <?php foreach ($tableRows as $r): ?>
              <tr><td class="p-2 border"><?=htmlspecialchars($r['barangay_name'])?></td><td class="p-2 border text-right"><?=intval($r['cnt'])?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card p-4">
        <h4 class="mb-3">Barangays With Zero Cases</h4>
        <?php if (empty($zeroBarangays)): ?>
          <div class="text-gray-500">None — every barangay has recorded at least one case in the selected range.</div>
        <?php else: ?>
          <ul class="list-disc ml-25 pl-5" style="padding-left:20px;"><?php foreach ($zeroBarangays as $z): ?><li><?=htmlspecialchars($z)?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../layouts/footer-block.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const topLabels = <?= json_encode($topLabels) ?>;
const topCounts = <?= json_encode($topCounts) ?>;
const spikeLabels = <?= json_encode($spikeLabels) ?>;
const spikeValues = <?= json_encode($spikeValues) ?>;

if (topLabels.length) {
  new Chart(document.getElementById('topBarangaysChart'), {
    type: 'bar',
    data: { labels: topLabels, datasets: [{ label: 'Cases', data: topCounts, borderWidth: 1 }] },
    options: { responsive: true, plugins:{legend:{display:false}} }
  });
} else {
  document.getElementById('topBarangaysChart').parentNode.innerHTML = '<div class="text-gray-500 p-6">No data for selected range.</div>';
}

if (spikeLabels.length) {
  new Chart(document.getElementById('spikeChart'), {
    type: 'bar',
    data: { labels: spikeLabels, datasets: [{ label: 'Percent change (approx)', data: spikeValues, borderWidth: 1 }] },
    options: { responsive: true, plugins:{legend:{display:false}} }
  });
} else {
  document.getElementById('spikeChart').parentNode.innerHTML = '<div class="text-gray-500 p-6">No spike data available.</div>';
}
</script>
