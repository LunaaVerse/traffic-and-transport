<?php
session_start();
require '../../../vendor/autoload.php';
require 'config/database.php';
$conn = new mysqli("localhost:3307", "root", "", "tm");

// Access granted for all logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Daily/Weekly Reports</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { width: 90%; margin: auto; padding: 20px; }
        .card { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
        th { background: #007bff; color: white; }
        button, a.btn { padding: 8px 15px; margin: 2px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration:none; }
        button:hover, a.btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>Daily/Weekly Monitoring Reports</h1>
    <p>Welcome, <?= $_SESSION['username']; ?> | <a href="logout.php">Logout</a></p>

    <!-- Generate Report Section -->
    <div class="card">
        <h2>Generate Report</h2>
        <form method="POST">
            <label>Report Type:</label>
            <select name="report_type" required>
                <option value="Daily">Daily</option>
                <option value="Weekly">Weekly</option>
            </select>

            <label>Start Date:</label>
            <input type="date" name="start_date" required>

            <label>End Date:</label>
            <input type="date" name="end_date" required>

            <button type="submit" name="generate_report">Generate</button>
        </form>
    </div>

    <?php
    if (isset($_POST['generate_report'])) {
        $report_type = $_POST['report_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Fixed: Changed created_at to log_date
        $sql = "SELECT volume, COUNT(*) as total 
                FROM traffic_logs 
                WHERE DATE(log_date) BETWEEN '$start_date' AND '$end_date'
                GROUP BY volume";
        $result = $conn->query($sql);

        $low = $medium = $high = 0;
        while ($row = $result->fetch_assoc()) {
            if ($row['volume'] == 'Low') $low = $row['total'];
            if ($row['volume'] == 'Medium') $medium = $row['total'];
            if ($row['volume'] == 'High') $high = $row['total'];
        }

        echo "<div class='card'>";
        echo "<h2>Report Preview ($report_type)</h2>";
        echo "<p><strong>From:</strong> $start_date | <strong>To:</strong> $end_date</p>";
        echo "<table>
                <tr><th>Traffic Volume</th><th>Total Entries</th></tr>
                <tr><td>Low</td><td>$low</td></tr>
                <tr><td>Medium</td><td>$medium</td></tr>
                <tr><td>High</td><td>$high</td></tr>
              </table>";

        // Save & Export
        echo "<form method='POST'>
                <input type='hidden' name='report_type' value='$report_type'>
                <input type='hidden' name='start_date' value='$start_date'>
                <input type='hidden' name='end_date' value='$end_date'>
                <input type='hidden' name='low' value='$low'>
                <input type='hidden' name='medium' value='$medium'>
                <input type='hidden' name='high' value='$high'>
                <button type='submit' name='save_report'>Save Report</button>
              </form>";

        echo "<a href='export_pdf.php?type=$report_type&start=$start_date&end=$end_date' class='btn'>Export PDF</a>";
        echo "<a href='export_excel.php?type=$report_type&start=$start_date&end=$end_date' class='btn'>Export Excel</a>";

        echo "</div>";
    }

    // Save to DB
    if (isset($_POST['save_report'])) {
        $report_type = $_POST['report_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $low = $_POST['low'];
        $medium = $_POST['medium'];
        $high = $_POST['high'];
        
        // Fixed: Check if admin ID is set in session before using it
        $admin_id = isset($_SESSION['id']) ? $_SESSION['id'] : 0;

        $sql = "INSERT INTO monitoring_reports 
                (report_type, start_date, end_date, total_low, total_medium, total_high, generated_by) 
                VALUES ('$report_type','$start_date','$end_date','$low','$medium','$high','$admin_id')";
        if ($conn->query($sql)) {
            echo "<p style='color:green;'>Report saved successfully!</p>";
        } else {
            echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
        }
    }
    ?>

</div>
</body>
</html>