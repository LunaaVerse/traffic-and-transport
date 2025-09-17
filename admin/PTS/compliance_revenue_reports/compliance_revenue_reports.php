<?php
// Admin access only
session_start();
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "pts";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ==== CRUD FUNCTIONS ====

// Delete report
if (isset($_POST['delete_report'])) {
    $id = $_POST['id'];
    $conn->query("DELETE FROM reports WHERE id='$id'");
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>Swal.fire('Deleted','Report has been deleted','success');</script>";
}

// ==== FETCH STATS ====

// Compliance stats
$total_offenders = $conn->query("SELECT COUNT(*) as c FROM offenders")->fetch_assoc()['c'] ?? 0;
$paid = $conn->query("SELECT COUNT(*) as c FROM offenders WHERE status='Paid'")->fetch_assoc()['c'] ?? 0;
$unpaid = $total_offenders - $paid;

// Revenue stats
$total_fines = $conn->query("SELECT SUM(fine_amount) as t FROM offenders")->fetch_assoc()['t'] ?? 0;
$total_collected = $conn->query("SELECT SUM(amount_paid) as t FROM payments")->fetch_assoc()['t'] ?? 0;
$total_unpaid = $total_fines - $total_collected;

// Reports list
$reports = $conn->query("SELECT * FROM reports ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Compliance & Revenue Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

<h2>📑 Compliance & Revenue Reports (Admin Only)</h2>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Violations</h6>
                <p class="fs-4"><?=$total_offenders?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Paid</h6>
                <p class="fs-4"><?=$paid?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6>Unpaid</h6>
                <p class="fs-4"><?=$unpaid?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6>Total Fines</h6>
                <p class="fs-4">₱<?=number_format($total_fines,2)?></p>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-md-6">
        <h5>Compliance Rate</h5>
        <canvas id="complianceChart"></canvas>
    </div>
    <div class="col-md-6">
        <h5>Revenue Summary</h5>
        <canvas id="revenueChart"></canvas>
    </div>
</div>

<!-- Reports Table -->
<h4>📊 Generated Reports</h4>
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Report Name</th>
            <th>Type</th>
            <th>Date</th>
            <th>File</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $reports->fetch_assoc()) { ?>
        <tr>
            <td><?=$row['id']?></td>
            <td><?=$row['report_name']?></td>
            <td><?=$row['report_type']?></td>
            <td><?=$row['generated_at']?></td>
            <td>
                <?php if ($row['file_path']) { ?>
                    <a href="<?=$row['file_path']?>" target="_blank" class="btn btn-info btn-sm">📄 Download</a>
                <?php } else { ?>
                    <span class="text-muted">N/A</span>
                <?php } ?>
            </td>
            <td>
                <form method="POST" style="display:inline-block">
                    <input type="hidden" name="id" value="<?=$row['id']?>">
                    <button type="submit" name="delete_report" class="btn btn-danger btn-sm">🗑 Delete</button>
                </form>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<script>
// Compliance Chart
new Chart(document.getElementById("complianceChart"), {
    type: "pie",
    data: {
        labels: ["Paid","Unpaid"],
        datasets: [{
            data: [<?=$paid?>, <?=$unpaid?>],
            backgroundColor: ["#28a745","#dc3545"]
        }]
    }
});

// Revenue Chart
new Chart(document.getElementById("revenueChart"), {
    type: "bar",
    data: {
        labels: ["Collected","Unpaid"],
        datasets: [{
            label: "₱ Amount",
            data: [<?=$total_collected?>, <?=$total_unpaid?>],
            backgroundColor: ["#007bff","#ffc107"]
        }]
    }
});
</script>

</body>
</html>
