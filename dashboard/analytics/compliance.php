<?php
// dashboard/analytics/compliance.php
ini_set('display_errors',1);
error_reporting(E_ALL);

include __DIR__ . '/../../layouts/head.php';

// ===== Date Filter =====
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$from_dt = $from ? date('Y-m-d', strtotime($from)) : null;
$to_dt   = $to ? date('Y-m-d', strtotime($to . ' +1 day')) : null;

$dateWhere = "";
$params = [];
if ($from_dt && $to_dt) {
    $dateWhere = "WHERE p.date_appointment >= ? AND p.date_appointment < ?";
    $params = [$from_dt, $to_dt];
} elseif ($from_dt) {
    $dateWhere = "WHERE p.date_appointment >= ?";
    $params = [$from_dt];
} elseif ($to_dt) {
    $dateWhere = "WHERE p.date_appointment < ?";
    $params = [$to_dt];
}

// ===== Check if schedule fields exist =====
$hasScheduleFields = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM schedule LIKE 'd0_first_dose'");
    if ($col && $col->num_rows > 0) $hasScheduleFields = true;
} catch (Exception $e) {
    $hasScheduleFields = false;
}

$labels = [];
$values = [];
$tableRows = [];

if ($hasScheduleFields) {

    // ===== Query compliance data =====
    $sql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN s.d0_first_dose IS NOT NULL THEN 1 END) AS d0_done,
            SUM(CASE WHEN s.d3_second_dose IS NOT NULL THEN 1 END) AS d3_done,
            SUM(CASE WHEN s.d7_third_dose IS NOT NULL THEN 1 END) AS d7_done,
            SUM(CASE WHEN s.d14_if_hospitalized IS NOT NULL THEN 1 END) AS d14_done,
            SUM(CASE WHEN s.d28_klastdose IS NOT NULL THEN 1 END) AS d28_done
        FROM patients p
        LEFT JOIN schedule s ON s.schedule_id = p.schedule_id
        $dateWhere
    ";

    $stmt = $conn->prepare($sql);

    if ($dateWhere) {
        if (count($params) === 2) $stmt->bind_param("ss", $params[0], $params[1]);
        else $stmt->bind_param("s", $params[0]);
    }

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int)($res['total'] ?? 0);

    if ($total > 0) {
        $labels = ['Day 0','Day 3','Day 7','Day 14','Day 28'];

        $values = [
            round(($res['d0_done'] / $total) * 100, 1),
            round(($res['d3_done'] / $total) * 100, 1),
            round(($res['d7_done'] / $total) * 100, 1),
            round(($res['d14_done'] / $total) * 100, 1),
            round(($res['d28_done'] / $total) * 100, 1),
        ];

        $tableRows = [
            ['label'=>'Day 0','pct'=>$values[0]],
            ['label'=>'Day 3','pct'=>$values[1]],
            ['label'=>'Day 7','pct'=>$values[2]],
            ['label'=>'Day 14','pct'=>$values[3]],
            ['label'=>'Day 28','pct'=>$values[4]],
        ];
    }
}
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.min.css" />

<div class="">
    <h3 class="mb-4">💉 Vaccination Compliance</h3>

    <form method="GET" class="mb-4 flex flex-col md:flex-row gap-3 md:items-end">
        <div>
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div>
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="flex gap-3">
            <button class="btn btn-primary">Apply</button>
            <a class="btn btn-outline-secondary" href="<?= $_SERVER['PHP_SELF'] ?>">Reset</a>
        </div>
    </form>

    <div class="card p-4">
        <?php if (!$hasScheduleFields): ?>
            <div class="p-6 text-gray-500">Cannot compute compliance — schedule fields do not exist.</div>

        <?php elseif ($total == 0): ?>
            <div class="p-6 text-gray-500">No patients found in this date range.</div>

        <?php else: ?>
            <h4 class="mb-3">Completion Percentage by Dose</h4>
            <canvas id="complianceChart" style="height:320px;"></canvas>

            <div class="mt-4">
                <table class="table-auto w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="p-2 border">Dose</th>
                            <th class="p-2 border">Percent Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $tr): ?>
                            <tr>
                                <td class="p-2 border"><?= $tr['label'] ?></td>
                                <td class="p-2 border text-right"><?= $tr['pct'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/footer-block.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const values = <?= json_encode($values) ?>;

if (labels.length) {
    new Chart(document.getElementById('complianceChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: '% Completed', data: values }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
}
</script>
