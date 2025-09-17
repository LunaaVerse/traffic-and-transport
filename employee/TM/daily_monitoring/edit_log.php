<?php
// --- DB CONNECTION ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "traffic_system";  // change to your DB
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FILTER (Daily or Weekly) ---
$report_type = $_GET['type'] ?? 'daily';
$selected_date = $_GET['date'] ?? date('Y-m-d');

if ($report_type == 'daily') {
    $query = "SELECT district, volume_status, COUNT(*) as total
              FROM traffic_volume
              WHERE log_date = '$selected_date'
              GROUP BY district, volume_status";
} else { // weekly
    $start_date = date('Y-m-d', strtotime("last Sunday", strtotime($selected_date)));
    $end_date   = date('Y-m-d', strtotime("next Saturday", strtotime($selected_date)));

    $query = "SELECT district, volume_status, COUNT(*) as total
              FROM traffic_volume
              WHERE log_date BETWEEN '$start_date' AND '$end_date'
              GROUP BY district, volume_status";
}

$result = $conn->query($query);

// prepare data for chart
$districts = [];
$low = [];
$medium = [];
$high = [];

while ($row = $result->fetch_assoc()) {
    $d = $row['district'];
    if (!in_array($d, $districts)) $districts[] = $d;

    $index = array_search($d, $districts);
    $low[$index] = $low[$index] ?? 0;
    $medium[$index] = $medium[$index] ?? 0;
    $high[$index] = $high[$index] ?? 0;

    if ($row['volume_status'] == 'Low') $low[$index] = $row['total'];
    if ($row['volume_status'] == 'Medium') $medium[$index] = $row['total'];
    if ($row['volume_status'] == 'High') $high[$index] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Traffic Monitoring Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2 class="mb-4 text-center">📑 Traffic Monitoring Reports (Admin)</h2>

  <!-- Filter Form -->
  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">Report Type</label>
      <select name="type" class="form-select">
        <option value="daily" <?= $report_type=='daily'?'selected':'' ?>>Daily</option>
        <option value="weekly" <?= $report_type=='weekly'?'selected':'' ?>>Weekly</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Select Date</label>
      <input type="date" name="date" class="form-control" value="<?= $selected_date ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Generate</button>
    </div>
  </form>

  <!-- Chart -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title">Traffic Volume Summary (<?= ucfirst($report_type) ?>)</h5>
      <canvas id="reportChart" height="100"></canvas>
    </div>
  </div>

  <!-- Table -->
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Detailed Report</h5>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>District</th>
            <th>Low</th>
            <th>Medium</th>
            <th>High</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i=0; $i<count($districts); $i++): ?>
          <tr>
            <td><?= $districts[$i] ?></td>
            <td><?= $low[$i] ?? 0 ?></td>
            <td><?= $medium[$i] ?? 0 ?></td>
            <td><?= $high[$i] ?? 0 ?></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Export Buttons -->
  <div class="mt-3">
    <a href="export_report.php?type=<?= $report_type ?>&date=<?= $selected_date ?>&format=pdf" class="btn btn-danger">Export PDF</a>
    <a href="export_report.php?type=<?= $report_type ?>&date=<?= $selected_date ?>&format=excel" class="btn btn-success">Export Excel</a>
  </div>
</div>

<script>
const ctx = document.getElementById('reportChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($districts) ?>,
    datasets: [
      { label: 'Low', data: <?= json_encode($low) ?>, backgroundColor: '#198754' },
      { label: 'Medium', data: <?= json_encode($medium) ?>, backgroundColor: '#ffc107' },
      { label: 'High', data: <?= json_encode($high) ?>, backgroundColor: '#dc3545' }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      title: { display: true, text: 'Traffic Volume Report by District' }
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});
</script>
</body>
</html>
