<?php
$conn = new mysqli("localhost:3307", "root", "", "chat");
$result = $conn->query("
  SELECT a.alert_id, a.alert_message, a.alert_time, f.location 
  FROM cctv_alerts a
  JOIN cctv_feeds f ON a.cctv_id = f.cctv_id
  ORDER BY a.alert_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CCTV Alert History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-4">
    <h2 class="mb-3">📜 CCTV Alert History</h2>
    <table class="table table-bordered table-hover bg-white">
      <thead class="table-danger">
        <tr>
          <th>Alert ID</th>
          <th>Location</th>
          <th>Message</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()) { ?>
          <tr>
            <td><?= $row['alert_id'] ?></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><?= htmlspecialchars($row['alert_message']) ?></td>
            <td><?= $row['alert_time'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <a href="admin_cctv.php" class="btn btn-secondary">⬅ Back to CCTV Dashboard</a>
  </div>
</body>
</html>
