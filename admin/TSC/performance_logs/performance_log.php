<?php
// ==================== DATABASE CONNECTION ====================
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "tsc";

$conn = new mysqli($host,$user,$pass,$db);
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// ==================== ROUTING ANALYTICS CRUD ====================
if(isset($_POST['action'])){
    $action = $_POST['action'];
    
    // Routing Analytics
    if(isset($_POST['route_name'])){
        $id = $_POST['id'] ?? null;
        $route_name = $_POST['route_name'];
        $start_lat = $_POST['start_lat'];
        $start_lng = $_POST['start_lng'];
        $end_lat = $_POST['end_lat'];
        $end_lng = $_POST['end_lng'];
        $average_time = $_POST['average_time'];
        $vehicle_count = $_POST['vehicle_count'];
        $description = $_POST['description'];
        
        if($action=='add'){
            $stmt = $conn->prepare("INSERT INTO routing_analytics (route_name,start_lat,start_lng,end_lat,end_lng,average_time,vehicle_count,description) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sdddddis",$route_name,$start_lat,$start_lng,$end_lat,$end_lng,$average_time,$vehicle_count,$description);
            $stmt->execute();
            echo json_encode(['status'=>'success','id'=>$stmt->insert_id]);
            exit;
        }
        if($action=='update'){
            $stmt = $conn->prepare("UPDATE routing_analytics SET route_name=?,start_lat=?,start_lng=?,end_lat=?,end_lng=?,average_time=?,vehicle_count=?,description=? WHERE id=?");
            $stmt->bind_param("sdddddisi",$route_name,$start_lat,$start_lng,$end_lat,$end_lng,$average_time,$vehicle_count,$description,$id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
            exit;
        }
        if($action=='delete'){
            $stmt = $conn->prepare("DELETE FROM routing_analytics WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
            exit;
        }
        if($action=='drag'){
            $marker_type = $_POST['marker_type'];
            $id = $_POST['id'];
            $lat = $_POST['lat'];
            $lng = $_POST['lng'];
            if($marker_type=='start'){
                $stmt = $conn->prepare("UPDATE routing_analytics SET start_lat=?, start_lng=? WHERE id=?");
                $stmt->bind_param("ddi",$lat,$lng,$id);
            } else {
                $stmt = $conn->prepare("UPDATE routing_analytics SET end_lat=?, end_lng=? WHERE id=?");
                $stmt->bind_param("ddi",$lat,$lng,$id);
            }
            $stmt->execute();
            echo json_encode(['status'=>'success']);
            exit;
        }
    }

    // Performance Logs
    if(isset($_POST['module_name'])){
        $id = $_POST['id'] ?? null;
        $log_date = $_POST['log_date'];
        $module_name = $_POST['module_name'];
        $description = $_POST['description'];
        $status = $_POST['status'];
        $remarks = $_POST['remarks'];

        if($action=='add'){
            $stmt = $conn->prepare("INSERT INTO performance_logs (log_date,module_name,description,status,remarks) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss",$log_date,$module_name,$description,$status,$remarks);
            $stmt->execute();
            echo json_encode(['status'=>'success','id'=>$stmt->insert_id]);
            exit;
        }
        if($action=='update'){
            $stmt = $conn->prepare("UPDATE performance_logs SET log_date=?,module_name=?,description=?,status=?,remarks=? WHERE id=?");
            $stmt->bind_param("sssssi",$log_date,$module_name,$description,$status,$remarks,$id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
            exit;
        }
        if($action=='delete'){
            $stmt = $conn->prepare("DELETE FROM performance_logs WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
            exit;
        }
    }
}

// ==================== FETCH DATA ====================
$routing_result = $conn->query("SELECT * FROM routing_analytics");
$routes = [];
while($row = $routing_result->fetch_assoc()) $routes[]=$row;

$logs_result = $conn->query("SELECT * FROM performance_logs ORDER BY log_date DESC");
$logs = [];
while($row = $logs_result->fetch_assoc()) $logs[]=$row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Routing & Performance</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{font-family:Arial;padding:10px;}
#map{height:400px;margin-bottom:15px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #ccc;padding:5px;}
input, select, textarea{width:100%;padding:5px;margin:3px 0;}
button{padding:5px 10px;margin:3px;}
</style>
</head>
<body>

<h2>Admin Dashboard</h2>

<h3>Routing Analytics Map</h3>
<div id="map"></div>

<h3>Routes Form</h3>
<form id="routeForm">
<input type="hidden" id="route_id" name="id">
<input type="text" id="route_name" name="route_name" placeholder="Route Name" required>
<input type="number" step="0.000001" id="start_lat" name="start_lat" placeholder="Start Lat" required>
<input type="number" step="0.000001" id="start_lng" name="start_lng" placeholder="Start Lng" required>
<input type="number" step="0.000001" id="end_lat" name="end_lat" placeholder="End Lat" required>
<input type="number" step="0.000001" id="end_lng" name="end_lng" placeholder="End Lng" required>
<input type="number" step="0.01" id="average_time" name="average_time" placeholder="Average Time" required>
<input type="number" id="vehicle_count" name="vehicle_count" placeholder="Vehicle Count" required>
<textarea id="description" name="description" placeholder="Description"></textarea>
<button type="submit" data-action="add">Add Route</button>
<button type="submit" data-action="update">Update Route</button>
</form>

<h3>Routes Table</h3>
<table id="routesTable">
<thead>
<tr><th>ID</th><th>Name</th><th>Start</th><th>End</th><th>Avg Time</th><th>Vehicles</th><th>Description</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach($routes as $r): ?>
<tr data-id="<?= $r['id'] ?>">
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['route_name']) ?></td>
<td><?= $r['start_lat'].','.$r['start_lng'] ?></td>
<td><?= $r['end_lat'].','.$r['end_lng'] ?></td>
<td><?= $r['average_time'] ?></td>
<td><?= $r['vehicle_count'] ?></td>
<td><?= htmlspecialchars($r['description']) ?></td>
<td>
<button onclick="editRoute(<?= $r['id'] ?>,'<?= htmlspecialchars($r['route_name'],ENT_QUOTES) ?>',<?= $r['start_lat'] ?>,<?= $r['start_lng'] ?>,<?= $r['end_lat'] ?>,<?= $r['end_lng'] ?>,<?= $r['average_time'] ?>,<?= $r['vehicle_count'] ?>,'<?= htmlspecialchars($r['description'],ENT_QUOTES) ?>')">Edit</button>
<button onclick="deleteRoute(<?= $r['id'] ?>)">Delete</button>
<button onclick="logPerformanceForRoute(<?= $r['id'] ?>)">Log Performance</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h3>Analytics Chart</h3>
<canvas id="analyticsChart" width="600" height="300"></canvas>

<h3>Performance Logs Form</h3>
<form id="logForm">
<input type="hidden" id="log_id" name="id">
<input type="datetime-local" id="log_date" name="log_date" required>
<input type="text" id="module_name" name="module_name" placeholder="Module Name" required>
<textarea id="description" name="description" placeholder="Description"></textarea>
<select id="status" name="status" required>
<option value="">Select Status</option>
<option value="Good">Good</option>
<option value="Warning">Warning</option>
<option value="Critical">Critical</option>
</select>
<textarea id="remarks" name="remarks" placeholder="Remarks"></textarea>
<button type="submit" data-action="add">Add Log</button>
<button type="submit" data-action="update">Update Log</button>
</form>

<h3>Performance Logs Table</h3>
<table id="logsTable">
<thead>
<tr><th>ID</th><th>Date</th><th>Module</th><th>Description</th><th>Status</th><th>Remarks</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach($logs as $l): ?>
<tr data-id="<?= $l['id'] ?>">
<td><?= $l['id'] ?></td>
<td><?= $l['log_date'] ?></td>
<td><?= htmlspecialchars($l['module_name']) ?></td>
<td><?= htmlspecialchars($l['description']) ?></td>
<td><?= $l['status'] ?></td>
<td><?= htmlspecialchars($l['remarks']) ?></td>
<td>
<button onclick="editLog(<?= $l['id'] ?>,'<?= $l['log_date'] ?>','<?= htmlspecialchars($l['module_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($l['description'],ENT_QUOTES) ?>','<?= $l['status'] ?>','<?= htmlspecialchars($l['remarks'],ENT_QUOTES) ?>')">Edit</button>
<button onclick="deleteLog(<?= $l['id'] ?>)">Delete</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<script>
// ==================== MAP INIT ====================
var map = L.map('map').setView([14.6760,121.0437],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);

var routes = <?= json_encode($routes) ?>;
var markers = {}, polylines = {};
var selectedLine = null;

// Initialize map markers and polylines
routes.forEach(function(r){
    var startMarker = L.marker([r.start_lat,r.start_lng],{draggable:true}).addTo(map).bindPopup('Start: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
    var endMarker = L.marker([r.end_lat,r.end_lng],{draggable:true}).addTo(map).bindPopup('End: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
    var line = L.polyline([[r.start_lat,r.start_lng],[r.end_lat,r.end_lng]],{color:'blue',weight:3}).addTo(map);

    function dragUpdate(e,type){
        var pos = e.target.getLatLng();
        var fd = new FormData(); fd.append('action','drag'); fd.append('id',r.id); fd.append('marker_type',type);
        fd.append('lat',pos.lat); fd.append('lng',pos.lng);
        fetch('',{method:'POST',body:fd}).then(res=>res.json()).then(data=>{
            if(data.status=='success'){
                Swal.fire('Updated!','Marker position updated','success');
                if(type=='start'){ line.setLatLngs([[pos.lat,pos.lng],[endMarker.getLatLng().lat,endMarker.getLatLng().lng]]);}
                else{ line.setLatLngs([[startMarker.getLatLng().lat,startMarker.getLatLng().lng],[pos.lat,pos.lng]]);}
            }
        });
    }

    startMarker.on('dragend', (e)=>dragUpdate(e,'start'));
    endMarker.on('dragend', (e)=>dragUpdate(e,'end'));

    markers[r.id] = {start:startMarker,end:endMarker};
    polylines[r.id] = line;
});

// ==================== TABLE ROW CLICK ====================
document.querySelectorAll('#routesTable tbody tr').forEach(function(row){
    row.addEventListener('click', function(){
        var routeId = this.getAttribute('data-id');
        var line = polylines[routeId];
        if(selectedLine) selectedLine.setStyle({color:'blue', weight:3});
        line.setStyle({color:'red', weight:6});
        selectedLine = line;
        map.fitBounds(line.getBounds(),{padding:[50,50]});
        var r = routes.find(r=>r.id==routeId);
        Swal.fire(r.route_name,'Average Time: '+r.average_time+' min<br>Vehicles: '+r.vehicle_count,'info');
    });
});

// ==================== CHART ====================
var ctx = document.getElementById('analyticsChart').getContext('2d');
var chart = new Chart(ctx,{
    type:'bar',
    data:{
        labels: routes.map(r=>r.route_name),
        datasets:[
            {label:'Average Time (min)',data:routes.map(r=>r.average_time),backgroundColor:'rgba(54,162,235,0.7)'},
            {label:'Vehicle Count',data:routes.map(r=>r.vehicle_count),backgroundColor:'rgba(255,99,132,0.7)'}
        ]
    },
    options:{responsive:true,scales:{y:{beginAtZero:true}}}
});

// ==================== REAL-TIME VEHICLE COUNT ====================
function updateVehicleCounts(){
    routes.forEach(r=>{
        r.vehicle_count = Math.max(0,r.vehicle_count+Math.floor(Math.random()*11-5));
        var startMarker = markers[r.id].start;
        var endMarker = markers[r.id].end;
        startMarker.bindPopup('Start: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
        endMarker.bindPopup('End: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
        var row = document.querySelector('#routesTable tbody tr[data-id="'+r.id+'"]');
        if(row) row.cells[5].innerText = r.vehicle_count;
    });
    chart.data.datasets[1].data = routes.map(r=>r.vehicle_count);
    chart.update();
}
setInterval(updateVehicleCounts,5000);

// ==================== ROUTE FORM HANDLERS ====================
function editRoute(id,name,start_lat,start_lng,end_lat,end_lng,avg_time,vehicle_count,desc){
    document.getElementById('route_id').value = id;
    document.getElementById('route_name').value = name;
    document.getElementById('start_lat').value = start_lat;
    document.getElementById('start_lng').value = start_lng;
    document.getElementById('end_lat').value = end_lat;
    document.getElementById('end_lng').value = end_lng;
    document.getElementById('average_time').value = avg_time;
    document.getElementById('vehicle_count').value = vehicle_count;
    document.getElementById('description').value = desc;
    Swal.fire('Editing','Update route details','info');
}

function deleteRoute(id){
    Swal.fire({title:'Delete?',text:'This will remove the route!',icon:'warning',showCancelButton:true,confirmButtonText:'Yes'}).then(res=>{
        if(res.isConfirmed){
            var fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
            fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(()=>location.reload());
        }
    });
}

function logPerformanceForRoute(routeId){
    var r = routes.find(r=>r.id==routeId);
    if(!r) return;
    document.getElementById('log_date').value = new Date().toISOString().slice(0,16);
    document.getElementById('module_name').value = r.route_name;
    document.getElementById('description').value = '';
    document.getElementById('status').value = '';
    document.getElementById('remarks').value = '';
    Swal.fire('Ready','Logging performance for '+r.route_name,'info');
}

document.getElementById('routeForm').addEventListener('submit',function(e){
    e.preventDefault();
    var action=document.activeElement.getAttribute('data-action');
    var fd=new FormData(this); fd.append('action',action);
    fetch('',{method:'POST',body:fd}).then(res=>res.json()).then(data=>{
        if(data.status=='success') Swal.fire('Success','Action completed','success').then(()=>location.reload());
    });
});

// ==================== PERFORMANCE LOGS HANDLERS ====================
function editLog(id,date,module,desc,status,remarks){
    document.getElementById('log_id').value = id;
    document.getElementById('log_date').value = date;
    document.getElementById('module_name').value = module;
    document.getElementById('description').value = desc;
    document.getElementById('status').value = status;
    document.getElementById('remarks').value = remarks;
    Swal.fire('Editing','You can now update this log','info');
}

function deleteLog(id){
    Swal.fire({title:'Are you sure?',text:'This will delete the log!',icon:'warning',showCancelButton:true,confirmButtonText:'Yes'}).then(res=>{
        if(res.isConfirmed){
            var fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
            fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(()=>Swal.fire('Deleted','Log removed','success').then(()=>location.reload()));
        }
    });
}

document.getElementById('logForm').addEventListener('submit',function(e){
    e.preventDefault();
    var action=document.activeElement.getAttribute('data-action');
    var fd=new FormData(this); fd.append('action',action);
    fetch('',{method:'POST',body:fd}).then(res=>res.json()).then(data=>{
        if(data.status=='success') Swal.fire('Success','Action completed','success').then(()=>location.reload());
    });
});
</script>

</body>
</html>
