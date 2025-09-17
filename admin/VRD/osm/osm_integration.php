<?php
// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "vrd";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle AJAX CRUD actions
if(isset($_POST['action'])){
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? null;
    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;
    $desc = $_POST['description'] ?? null;

    if($action == 'add') {
        $stmt = $conn->prepare("INSERT INTO osm_locations (name, latitude, longitude, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdds", $name, $lat, $lng, $desc);
        $stmt->execute();
        echo json_encode([
            'status'=>'success',
            'id' => $stmt->insert_id,
            'name' => $name,
            'latitude' => $lat,
            'longitude' => $lng,
            'description' => $desc
        ]);
        exit;
    }

    if($action == 'update') {
        $stmt = $conn->prepare("UPDATE osm_locations SET name=?, latitude=?, longitude=?, description=? WHERE id=?");
        $stmt->bind_param("sddsi", $name, $lat, $lng, $desc, $id);
        $stmt->execute();
        echo json_encode(['status'=>'success']);
        exit;
    }

    if($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM osm_locations WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['status'=>'success']);
        exit;
    }
}

// Fetch all locations
$result = $conn->query("SELECT * FROM osm_locations");
$locations = [];
while($row = $result->fetch_assoc()){
    $locations[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OSM Leaflet Admin</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    #map { height: 500px; margin-bottom: 20px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
</style>
</head>
<body>

<h2>OSM Leaflet Integration - Admin Access Only</h2>

<div id="map"></div>

<h3>Add / Update Location</h3>
<form id="locationForm">
    <input type="hidden" name="id" id="loc_id">
    <input type="text" name="name" id="name" placeholder="Location Name" required>
    <input type="text" name="latitude" id="latitude" placeholder="Latitude" required>
    <input type="text" name="longitude" id="longitude" placeholder="Longitude" required>
    <input type="text" name="description" id="description" placeholder="Description">
    <button type="submit" data-action="add">Add Location</button>
    <button type="submit" data-action="update">Update Location</button>
</form>

<h3>Existing Locations</h3>
<table id="locationsTable">
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Latitude</th><th>Longitude</th><th>Description</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($locations as $loc): ?>
        <tr data-id="<?= $loc['id'] ?>">
            <td><?= $loc['id'] ?></td>
            <td><?= htmlspecialchars($loc['name']) ?></td>
            <td><?= $loc['latitude'] ?></td>
            <td><?= $loc['longitude'] ?></td>
            <td><?= htmlspecialchars($loc['description']) ?></td>
            <td>
                <button onclick="editLocation(<?= $loc['id'] ?>,'<?= htmlspecialchars($loc['name']) ?>',<?= $loc['latitude'] ?>,<?= $loc['longitude'] ?>,'<?= htmlspecialchars($loc['description']) ?>')">Edit</button>
                <button onclick="deleteLocation(<?= $loc['id'] ?>)">Delete</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
var map = L.map('map').setView([14.6760, 121.0437], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

var markers = {};
var locations = <?= json_encode($locations) ?>;

// Add existing markers
locations.forEach(function(loc){
    var marker = L.marker([loc.latitude, loc.longitude], {draggable:true}).addTo(map)
        .bindPopup('<b>' + loc.name + '</b><br>' + loc.description);
    marker.on('dragend', function(e){
        var pos = e.target.getLatLng();
        document.getElementById('latitude').value = pos.lat.toFixed(7);
        document.getElementById('longitude').value = pos.lng.toFixed(7);
        document.getElementById('loc_id').value = loc.id;
        document.getElementById('name').value = loc.name;
        document.getElementById('description').value = loc.description;
        Swal.fire('Marker Dragged', 'Latitude/Longitude updated in form', 'info');
    });
    markers[loc.id] = marker;
});

// Map click to set new location
map.on('click', function(e){
    document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
    document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
    Swal.fire('New Location', 'Click "Add Location" to save this point', 'info');
});

// Form submission with AJAX
document.getElementById('locationForm').addEventListener('submit', function(e){
    e.preventDefault();
    var action = e.submitter.dataset.action;
    var formData = new FormData(this);
    formData.append('action', action);

    fetch('', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status == 'success'){
            if(action === 'add'){
                // Add marker immediately
                var marker = L.marker([data.latitude, data.longitude], {draggable:true}).addTo(map)
                    .bindPopup('<b>'+data.name+'</b><br>'+data.description);
                marker.on('dragend', function(ev){
                    var pos = ev.target.getLatLng();
                    document.getElementById('latitude').value = pos.lat.toFixed(7);
                    document.getElementById('longitude').value = pos.lng.toFixed(7);
                    document.getElementById('loc_id').value = data.id;
                    document.getElementById('name').value = data.name;
                    document.getElementById('description').value = data.description;
                    Swal.fire('Marker Dragged', 'Latitude/Longitude updated in form', 'info');
                });
                markers[data.id] = marker;

                // Add to table
                var table = document.getElementById('locationsTable').querySelector('tbody');
                var row = table.insertRow();
                row.dataset.id = data.id;
                row.innerHTML = `<td>${data.id}</td><td>${data.name}</td><td>${data.latitude}</td><td>${data.longitude}</td><td>${data.description}</td>
                    <td>
                        <button onclick="editLocation(${data.id},'${data.name}',${data.latitude},${data.longitude},'${data.description}')">Edit</button>
                        <button onclick="deleteLocation(${data.id})">Delete</button>
                    </td>`;
                Swal.fire('Added!', 'Location added and map updated', 'success');
            } else {
                Swal.fire('Updated!', 'Location updated successfully', 'success').then(()=>location.reload());
            }
        }
    });
});

// Edit location from table
function editLocation(id, name, lat, lng, desc){
    document.getElementById('loc_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('description').value = desc;
    Swal.fire('Editing', 'You are now editing this location', 'info');
}

// Delete location
function deleteLocation(id){
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the location permanently!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
    }).then((result)=>{
        if(result.isConfirmed){
            var formData = new FormData();
            formData.append('action','delete');
            formData.append('id', id);
            fetch('', {method:'POST', body:formData})
            .then(res=>res.json())
            .then(data=>{
                if(data.status=='success'){
                    // Remove marker and table row
                    map.removeLayer(markers[id]);
                    document.querySelector('tr[data-id="'+id+'"]').remove();
                    Swal.fire('Deleted!', 'Location removed successfully', 'success');
                }
            });
        }
    });
}
</script>

</body>
</html>
