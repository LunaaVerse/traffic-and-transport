<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Daily/Weekly Monitoring Reports</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
    th { background-color: #007bff; color: white; }
    .btn { padding: 10px 15px; margin: 5px; cursor: pointer; }
    .btn-generate { background-color: green; color: white; border: none; }
    .btn-export { background-color: blue; color: white; border: none; }
  </style>
</head>
<body>

  <h2>📊 Daily & Weekly Monitoring Reports</h2>

  <!-- Buttons -->
  <form method="POST">
    <button type="submit" name="generate_daily" class="btn btn-generate">Generate Daily Report</button>
    <button type="submit" name="generate_weekly" class="btn btn-generate">Generate Weekly Report</button>
  </form>

  <?php
  // db connection
  $conn = new mysqli("localhost:3307", "root", "", "chat");

  if (isset($_POST['generate_daily'])) {
      $sql = "SELECT 
                SUM(volume_status='Low') AS total_low,
                SUM(volume_status='Medium') AS total_medium,
                SUM(volume_status='High') AS total_high
              FROM traffic_volume
              WHERE log_date = CURDATE()";
      $result = $conn->query($sql);
      $row = $result->fetch_assoc();

      $conn->query("INSERT INTO traffic_reports (report_type, start_date, end_date, total_low, total_medium, total_high)
                    VALUES ('Daily', CURDATE(), CURDATE(), {$row['total_low']}, {$row['total_medium']}, {$row['total_high']})");

      echo "<h3>✅ Daily Report Generated!</h3>";
  }

  if (isset($_POST['generate_weekly'])) {
      $sql = "SELECT 
                SUM(volume_status='Low') AS total_low,
                SUM(volume_status='Medium') AS total_medium,
                SUM(volume_status='High') AS total_high
              FROM traffic_volume
              WHERE YEARWEEK(log_date, 1) = YEARWEEK(CURDATE(), 1)";
      $result = $conn->query($sql);
      $row = $result->fetch_assoc();

      $weekStart = date('Y-m-d', strtotime('monday this week'));
      $weekEnd = date('Y-m-d', strtotime('sunday this week'));

      $conn->query("INSERT INTO traffic_reports (report_type, start_date, end_date, total_low, total_medium, total_high)
                    VALUES ('Weekly', '$weekStart', '$weekEnd', {$row['total_low']}, {$row['total_medium']}, {$row['total_high']})");

      echo "<h3>✅ Weekly Report Generated!</h3>";
  }

  // Show all reports
  $reports = $conn->query("SELECT * FROM traffic_reports ORDER BY created_at DESC");

  echo "<form method='POST'>";
  echo "<table><tr><th>ID</th><th>Type</th><th>Start</th><th>End</th><th>Low</th><th>Medium</th><th>High</th><th>Date Created</th><th>Action</th></tr>";
  while ($r = $reports->fetch_assoc()) {
      echo "<tr>
              <td>{$r['report_id']}</td>
              <td>{$r['report_type']}</td>
              <td>{$r['start_date']}</td>
              <td>{$r['end_date']}</td>
              <td>{$r['total_low']}</td>
              <td>{$r['total_medium']}</td>
              <td>{$r['total_high']}</td>
              <td>{$r['created_at']}</td>
              <td>
                <button type='submit' name='export_pdf' value='{$r['report_id']}' class='btn btn-export'>PDF</button>
                <button type='submit' name='export_excel' value='{$r['report_id']}' class='btn btn-export'>Excel</button>
              </td>
            </tr>";
  }
  echo "</table>";
  echo "</form>";

  // EXPORT HANDLERS
  if (isset($_POST['export_pdf'])) {
      $id = intval($_POST['export_pdf']);
      $r = $conn->query("SELECT * FROM traffic_reports WHERE report_id=$id")->fetch_assoc();

      header("Content-Type: application/pdf");
      header("Content-Disposition: attachment; filename=report_$id.pdf");

      echo "Traffic Report\n\n";
      echo "Report Type: {$r['report_type']}\n";
      echo "Start Date: {$r['start_date']}\n";
      echo "End Date: {$r['end_date']}\n\n";
      echo "Low: {$r['total_low']}\n";
      echo "Medium: {$r['total_medium']}\n";
      echo "High: {$r['total_high']}\n";
      exit;
  }

  if (isset($_POST['export_excel'])) {
      $id = intval($_POST['export_excel']);
      $r = $conn->query("SELECT * FROM traffic_reports WHERE report_id=$id")->fetch_assoc();

      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=report_$id.xls");

      echo "Report Type\tStart Date\tEnd Date\tLow\tMedium\tHigh\n";
      echo "{$r['report_type']}\t{$r['start_date']}\t{$r['end_date']}\t{$r['total_low']}\t{$r['total_medium']}\t{$r['total_high']}\n";
      exit;
  }

  $conn->close();
  ?>

</body>
</html>
