<?php
// dashboard/analytics/animals.php
ini_set('display_errors',1);
error_reporting(E_ALL);

include __DIR__ . '/../../layouts/head.php';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$from_dt = $from ? date('Y-m-d', strtotime($from)) : null;
$to_dt   = $to ? date('Y-m-d', strtotime($to.' +1 day')) : null;

$dateClause = '';
$params = [];
if ($from_dt && $to_dt) { $dateClause = "WHERE bt.date_reported >= ? AND bt.date_reported < ?"; $params = [$from_dt, $to_dt]; }
elseif ($from_dt) { $dateClause = "WHERE bt.date_reported >= ?"; $params = [$from_dt]; }
elseif ($to_dt) { $dateClause = "WHERE bt.date_reported < ?"; $params = [$to_dt]; }

// distribution
$animalLabels = $animalCounts = [];
$sql = "SELECT COALESCE(ba.animal_name,'Unknown') AS animal_name, COUNT(*) AS cnt
        FROM bites bt
        JOIN reports r ON r.report_id = bt.report_id
        LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
        " . ($dateClause ? $dateClause : "") . "
        GROUP BY r.biting_animal_id
        ORDER BY cnt DESC";
$stmt = $conn->prepare($sql);
if ($dateClause) {
  if (count($params)===2) $stmt->bind_param('ss', $params[0], $params[1]);
  else $stmt->bind_param('s', $params[0]);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $animalLabels[] = $r['animal_name']; $animalCounts[] = (int)$r['cnt']; }
$stmt->close();

// top N monthly series
$topN = 5;
$topIds = [];
$topList = [];
$sqlTop = "SELECT r.biting_animal_id, COALESCE(ba.animal_name,'Unknown') AS name, COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id ".($dateClause ? $dateClause : "")." GROUP BY r.biting_animal_id ORDER BY cnt DESC LIMIT ?";
$stmt = $conn->prepare($sqlTop);
if ($dateClause) {
  if (count($params)===2) $stmt->bind_param('ssi', $params[0], $params[1], $topN);
  else $stmt->bind_param('si', $params[0], $topN);
} else {
  $stmt->bind_param('i', $topN);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $topList[] = ['id'=>$r['biting_animal_id'],'name'=>$r['name']]; }
$stmt->close();

$months = [];
$res = $conn->query("SELECT DISTINCT DATE_FORMAT(bt.date_reported,'%Y-%m') AS ym FROM bites bt ORDER BY ym ASC");
while ($r = $res->fetch_assoc()) $months[] = $r['ym'];

$series = [];
foreach ($topList as $ta) {
  $arr = [];
  foreach ($months as $ym) {
    $start = $ym.'-01'; $end = date('Y-m-d', strtotime("$start +1 month"));
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id WHERE r.biting_animal_id = ? AND bt.date_reported >= ? AND bt.date_reported < ?");
    $s->bind_param('iss', $ta['id'], $start, $end);
    $s->execute();
    $cnt = (int)$s->get_result()->fetch_assoc()['cnt'] ?? 0;
    $s->close();
    $arr[] = $cnt;
  }
  $series[] = ['label'=>$ta['name'],'data'=>$arr];
}

// table rows (animal, count)
$tableRows = [];
$stmt = $conn->prepare("SELECT COALESCE(ba.animal_name,'Unknown') AS animal_name, COUNT(*) AS cnt FROM bites bt JOIN reports r ON r.report_id = bt.report_id LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id ".($dateClause ? $dateClause : "")." GROUP BY r.biting_animal_id ORDER BY cnt DESC");
if ($dateClause) {
  if (count($params)===2) $stmt->bind_param('ss', $params[0], $params[1]);
  else $stmt->bind_param('s', $params[0]);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $tableRows[] = $r;
$stmt->close();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js" />
<div class="">
  <h3 class="mb-4">🐾 Animal Type Trends</h3>

  <form method="GET" class="mb-4 flex flex-col md:flex-row gap-3 md:items-end">
    <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=htmlspecialchars($_GET['from']??'')?>"></div>
    <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=htmlspecialchars($_GET['to']??'')?>"></div>
    <div class="flex gap-3"><button class="btn btn-primary">Apply</button> <a class="btn btn-outline-secondary" href="<?= $_SERVER['PHP_SELF'] ?>">Reset</a></div>
  </form>

  <div class="grid grid-cols-12 gap-6">
    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4"><h4 class="mb-3">Distribution by Animal Type</h4><canvas id="animalDist" style="height:320px;"></canvas></div>
    </div>
    <div class="col-span-12 lg:col-span-6">
      <div class="card p-4"><h4 class="mb-3">Monthly Trend (Top <?=count($series)?> animals)</h4><?php if(empty($months)) echo '<div class="p-6 text-gray-500">No monthly data</div>'; else echo '<canvas id="animalMonthly" style="height:320px;"></canvas>'; ?></div>
    </div>

    <div class="col-span-12">
      <div class="card p-4">
        <h4 class="mb-3">Animal Counts</h4>
        <table class="table-auto w-full border-collapse">
          <thead><tr><th class="p-2 border">Animal</th><th class="p-2 border">Count</th></tr></thead>
          <tbody><?php foreach ($tableRows as $tr): ?><tr><td class="p-2 border"><?=htmlspecialchars($tr['animal_name'])?></td><td class="p-2 border text-right"><?=intval($tr['cnt'])?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../layouts/footer-block.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const animalLabels = <?= json_encode($animalLabels) ?>;
const animalCounts = <?= json_encode($animalCounts) ?>;
const months = <?= json_encode($months) ?>;
const series = <?= json_encode($series) ?>;

if (animalLabels.length) {
  new Chart(document.getElementById('animalDist'), { type:'pie', data:{labels:animalLabels,datasets:[{data:animalCounts}]}, options:{responsive:true} });
}
if (months.length && series.length) {
  const datasets = series.map(s=>({label:s.label,data:s.data,fill:false,tension:0.2}));
  new Chart(document.getElementById('animalMonthly'), { type:'line', data:{labels:months,datasets}, options:{responsive:true, plugins:{legend:{position:'bottom'}}} });
}
</script>
