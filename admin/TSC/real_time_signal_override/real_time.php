<?php
// real_time_signal_override_dashboard.php
session_start();
// Access granted for all logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "tsc";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS traffic_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    signal_name VARCHAR(255) NOT NULL,
    status ENUM('Red','Yellow','Green') DEFAULT 'Red',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle AJAX requests for override
if (isset($_POST['override'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE traffic_signals SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    echo json_encode(['success'=>true,'status'=>$status]);
    exit;
}

// Fetch signals for AJAX refresh
if (isset($_GET['fetch']) && $_GET['fetch']==1) {
    $result = $conn->query("SELECT * FROM traffic_signals ORDER BY updated_at DESC");
    $rows = [];
    while($r = $result->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Real-Time Signal Override Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #4CAF50; color: white; }
button { padding: 5px 10px; margin-right: 5px; margin-top: 2px; }
.status-red { color: red; font-weight: bold; }
.status-yellow { color: orange; font-weight: bold; }
.status-green { color: green; font-weight: bold; }
</style>
</head>
<body>
<h2>Real-Time Signal Override Dashboard (Admin Only)</h2>

<table id="signalTable">
<tr>
    <th>ID</th>
    <th>Signal Name</th>
    <th>Status</th>
    <th>Last Updated</th>
    <th>Override</th>
</tr>
</table>

<script>
// AJAX to update signal
function overrideSignal(id, status) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'override=1&id='+id+'&status='+status
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            Swal.fire({
                position: 'top-end',
                icon: 'success',
                title: 'Signal updated to '+data.status,
                showConfirmButton: false,
                timer: 1000
            });
            refreshTable();
        }
    });
}

// Refresh table every 5 seconds
function refreshTable(){
    fetch('?fetch=1')
    .then(res=>res.json())
    .then(data=>{
        let table = document.getElementById('signalTable');
        table.innerHTML = '<tr><th>ID</th><th>Signal Name</th><th>Status</th><th>Last Updated</th><th>Override</th></tr>';
        data.forEach(r=>{
            let row = table.insertRow();
            row.insertCell(0).innerText = r.id;
            row.insertCell(1).innerText = r.signal_name;
            let statusCell = row.insertCell(2);
            statusCell.innerText = r.status;
            statusCell.className = 'status-'+r.status.toLowerCase();
            row.insertCell(3).innerText = r.updated_at;
            row.insertCell(4).innerHTML = 
                '<button style="background:red;color:white;" onclick="overrideSignal('+r.id+',\'Red\')">Red</button>'+
                '<button style="background:orange;color:white;" onclick="overrideSignal('+r.id+',\'Yellow\')">Yellow</button>'+
                '<button style="background:green;color:white;" onclick="overrideSignal('+r.id+',\'Green\')">Green</button>';
        });
    });
}

window.onload = refreshTable;
setInterval(refreshTable, 5000);
</script>
</body>
</html>
