<?php
// diversion_planning_map_update.php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin'){
    echo "<script>
            alert('Access Denied. Admins Only.');
            window.location.href='index.php';
          </script>";
    exit();
}

$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "vrd";
$conn = new mysqli($host,$user,$pass,$db);
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// Add New Plan
if(isset($_POST['add'])){
    $road_name = $_POST['road_name'];
    $reason = $_POST['reason'];
    $from_lat = $_POST['from_lat'];
    $from_lng = $_POST['from_lng'];
    $to_lat = $_POST['to_lat'];
    $to_lng = $_POST['to_lng'];

    $sql = "INSERT INTO diversion_plans (road_name, reason, from_lat, from_lng, to_lat, to_lng)
            VALUES ('$road_name','$reason','$from_lat','$from_lng','$to_lat','$to_lng')";
    if($conn->query($sql)){
        echo "<script>Swal.fire('Added!','Diversion plan added.','success').then(()=>{window.location='diversion_planning_map_update.php'});</script>";
    }else{
        echo "<script>Swal.fire('Error!','Failed to add.','error');</script>";
    }
}

// Delete Plan
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $sql = "DELETE FROM diversion_plans WHERE id=$id";
    if($conn->query($sql)){
        echo "<script>Swal.fire('Deleted!','Diversion plan deleted.','success').then(()=>{window.location='diversion_planning_map_update.php'});</script>";
    }
}

// Update Plan (via AJAX)
if(isset($_POST['update'])){
    $id = $_POST['id'];
    $from_lat = $_POST['from_lat'];
    $from_lng = $_POST['from_lng'];
    $to_lat = $_POST['to_lat'];
    $to_lng = $_POST['to_lng'];
    $sql = "UPDATE diversion_plans SET from_lat='$from_lat', from_lng='$from_lng', to_lat='$to_lat', to_lng='$to_lng' WHERE id=$id";
    if($conn->query($sql)){
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error']);
    }
    exit();
}

// Fetch Plans
$plans = $conn->query("SELECT * FROM diversion_plans ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Diversion Planning Map (Update)</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>#map {height: 500px;}</style>
</head>
<body class="p-4">

<div class="container">
<h2>Diversion Planning Map (Admin)</h2>

<!-- Add Form -->
<div class="card mb-3 p-3">
<form method="POST" id="diversionForm">
    <div class="row mb-2">
        <div class="col"><input type="text" name="road_name" id="road_name" class="form-control" placeholder="Road Name" required></div>
        <div class="col"><input type="text" name="reason" id="reason" class="form-control" placeholder="Reason" required></div>
    </div>
    <div class="row mb-2">
        <div class="col"><input type="text" name="from_lat" id="from_lat" class="form-control" placeholder="From Latitude" readonly required></div>
        <div class="col"><input type="text" name="from_lng" id="from_lng" class="form-control" placeholder="From Longitude" readonly required></div>
    </div>
    <div class="row mb-2">
        <div class="col"><input type="text" name="to_lat" id="to_lat" class="form-control" placeholder="To Latitude" readonly required></div>
        <div class="col"><input type="text" name="to_lng" id="to_lng" class="form-control" placeholder="To Longitude" readonly required></div>
    </div>
    <button type="submit" name="add" class="btn btn-success">Add Diversion</button>
</form>
</div>

<!-- Map -->
<div id="map"></div>

<!-- Table -->
<table class="table table-bordered mt-3">
<thead>
<tr><th>ID</th><th>Road</th><th>Reason</th><th>Action</th></tr>
</thead>
<tbody>
<?php while($row=$plans->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['road_name'] ?></td>
<td><?= $row['reason'] ?></td>
<td>
<a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this plan?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
var map = L.map('map').setView([14.6760, 121.0437], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

var markers = {}; // store markers for each plan

// Add existing plans
<?php
$plans2 = $conn->query("SELECT * FROM diversion_plans");
while($p = $plans2->fetch_assoc()){
    $id = $p['id'];
    $road = addslashes($p['road_name']);
    echo "var fromMarker$id = L.marker([".$p['from_lat'].",".$p['from_lng']."],{draggable:true}).addTo(map).bindPopup('From: $road');\n";
    echo "var toMarker$id = L.marker([".$p['to_lat'].",".$p['to_lng']."],{draggable:true}).addTo(map).bindPopup('To: $road');\n";
    echo "fromMarker$id.on('dragend', function(e){ updatePlan($id, e.target.getLatLng(), 'from'); });\n";
    echo "toMarker$id.on('dragend', function(e){ updatePlan($id, e.target.getLatLng(), 'to'); });\n";
}
?>

// Add new markers on click
var newFrom, newTo;
map.on('click', function(e){
    if(!newFrom){
        newFrom = L.marker(e.latlng, {draggable:true}).addTo(map).bindPopup('New From').openPopup();
        $('#from_lat').val(e.latlng.lat); $('#from_lng').val(e.latlng.lng);
        newFrom.on('dragend', function(ev){
            var pos = ev.target.getLatLng();
            $('#from_lat').val(pos.lat); $('#from_lng').val(pos.lng);
        });
    } else if(!newTo){
        newTo = L.marker(e.latlng, {draggable:true}).addTo(map).bindPopup('New To').openPopup();
        $('#to_lat').val(e.latlng.lat); $('#to_lng').val(e.latlng.lng);
        newTo.on('dragend', function(ev){
            var pos = ev.target.getLatLng();
            $('#to_lat').val(pos.lat); $('#to_lng').val(pos.lng);
        });
    }
});

// AJAX update function
function updatePlan(id, latlng, type){
    var data = {id:id};
    if(type=='from'){ data.from_lat=latlng.lat; data.from_lng=latlng.lng; }
    else{ data.to_lat=latlng.lat; data.to_lng=latlng.lng; }
    $.post('diversion_planning_map_update.php?update=1', data, function(res){
        var r = JSON.parse(res);
        if(r.status=='success') Swal.fire('Updated!','Diversion plan updated.','success');
        else Swal.fire('Error!','Update failed.','error');
    });
}
</script>

</body>
</html>
