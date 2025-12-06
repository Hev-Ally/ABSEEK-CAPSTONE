<?php
// trends.php — Monthly & Yearly Trends
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include __DIR__ . '/../../layouts/head.php'; // includes db + session
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js" />

<div class="">
    <h2 class="text-xl font-bold mb-4">📈 Monthly & Yearly Trends</h2>

    <div class="grid grid-cols-12 gap-6">

        <!-- Monthly Trend Card -->
        <div class="col-span-12 lg:col-span-6">
            <div class="card p-4">
                <h3 class="text-lg font-semibold mb-3">📊 Monthly Bite Trend</h3>
                <canvas id="monthlyTrendChart" style="height:300px;"></canvas>
            </div>
        </div>

        <!-- Yearly Trend Card -->
        <div class="col-span-12 lg:col-span-6">
            <div class="card p-4">
                <h3 class="text-lg font-semibold mb-3">📉 Yearly Trend Comparison</h3>
                <canvas id="yearlyTrendChart" style="height:300px;"></canvas>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../layouts/footer-block.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ===============================
// FETCH MONTHLY DATA (PHP -> JS)
// ===============================
<?php
// Fetch monthly counts (last 12 months)
$monthly = $conn->query("
    SELECT 
        DATE_FORMAT(date_reported, '%Y-%m') AS ym,
        COUNT(*) AS total
    FROM bites
    WHERE date_reported IS NOT NULL
    GROUP BY ym
    ORDER BY ym ASC
");

$months = [];
$monthCounts = [];

while ($row = $monthly->fetch_assoc()) {
    $months[] = $row['ym'];
    $monthCounts[] = (int)$row['total'];
}

// Fetch yearly totals
$yearly = $conn->query("
    SELECT 
        YEAR(date_reported) AS yr,
        COUNT(*) AS total
    FROM bites
    GROUP BY yr
    ORDER BY yr ASC
");

$years = [];
$yearCounts = [];

while ($row = $yearly->fetch_assoc()) {
    $years[] = $row['yr'];
    $yearCounts[] = (int)$row['total'];
}
?>

const monthlyLabels = <?= json_encode($months) ?>;
const monthlyData = <?= json_encode($monthCounts) ?>;

const yearlyLabels = <?= json_encode($years) ?>;
const yearlyData = <?= json_encode($yearCounts) ?>;

// =========================
// MONTHLY TREND CHART
// =========================
new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Bite Incidents',
            data: monthlyData,
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        }
    }
});

// =========================
// YEARLY TREND CHART
// =========================
new Chart(document.getElementById('yearlyTrendChart'), {
    type: 'bar',
    data: {
        labels: yearlyLabels,
        datasets: [{
            label: 'Yearly Total',
            data: yearlyData,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        }
    }
});
</script>
