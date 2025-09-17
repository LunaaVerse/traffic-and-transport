<?php
// user_road_condition_map.php
session_start();

// Example role check (replace with your login system)
// For user access, we allow multiple roles
$allowed_roles = ["Admin", "User", "Citizen"];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("Access Denied. Please log in with appropriate credentials.");
}

// DB connection
$conn = new mysqli("localhost:3307", "root", "", "rtr");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// FETCH DATA
$result = $conn->query("SELECT * FROM condition_map ORDER BY updated_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Road Condition Map - User Access</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    #map { height: 500px; }
    .legend { 
      padding: 10px; 
      background: white; 
      background: rgba(255,255,255,0.8);
      box-shadow: 0 0 15px rgba(0,0,0,0.2);
      border-radius: 5px;
      line-height: 1.5;
    }
    .legend i { 
      width: 18px; 
      height: 18px; 
      float: left; 
      margin-right: 8px; 
      opacity: 0.7; 
    }
    .route-info {
      position: absolute;
      bottom: 20px;
      right: 10px;
      z-index: 1000;
      background: white;
      padding: 10px;
      border-radius: 5px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body class="bg-light">

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>🗺️ Road Condition Map</h2>
    <div>
      <span class="badge bg-secondary me-2">Logged in as: <?= $_SESSION['role'] ?? 'User' ?></span>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>

  <!-- Map Display -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Interactive Map</span>
      <button class="btn btn-sm btn-outline-primary" onclick="locateUser()">Locate Me</button>
    </div>
    <div class="card-body p-0">
      <div id="map"></div>
    </div>
  </div>

  <!-- Route Directions Panel -->
  <div class="card mb-4 d-none" id="routePanel">
    <div class="card-header">Route Directions</div>
    <div class="card-body">
      <div id="routeInstructions" class="mb-3" style="max-height: 200px; overflow-y: auto;"></div>
      <button class="btn btn-sm btn-secondary" onclick="clearRoute()">Clear Route</button>
    </div>
  </div>

  <!-- Overlay List -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Road Condition Reports</span>
      <small class="text-muted">Last updated: <?= date('Y-m-d H:i:s') ?></small>
    </div>
    <div class="card-body">
      <table class="table table-bordered table-hover">
        <thead class="table-dark">
          <tr>
            <th>Location</th>
            <th>Status</th>
            <th>Description</th>
            <th>Coordinates</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): 
          $badge_color = ($row['status']=="Open") ? "success" : (($row['status']=="Blocked") ? "danger" : "warning");
        ?>
          <tr>
            <td><?= htmlspecialchars($row['location_name']); ?></td>
            <td><span class="badge bg-<?= $badge_color ?>"><?= htmlspecialchars($row['status']); ?></span></td>
            <td><?= htmlspecialchars($row['description']); ?></td>
            <td><?= htmlspecialchars($row['latitude']) . ", " . htmlspecialchars($row['longitude']); ?></td>
            <td>
              <button class="btn btn-sm btn-info" onclick="focusOnMarker(<?= $row['latitude']; ?>, <?= $row['longitude']; ?>)">View</button>
              <button class="btn btn-sm btn-primary" onclick="setDestination(<?= $row['latitude']; ?>, <?= $row['longitude']; ?>, '<?= addslashes($row['location_name']); ?>')">Get Directions</button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Leaflet map setup
var map = L.map('map').setView([14.6760, 121.0437], 12); // Default: QC
var userLocation = null;
var routingControl = null;
var destinationMarker = null;

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

// Add legend
var legend = L.control({position: 'bottomright'});
legend.onAdd = function (map) {
    var div = L.DomUtil.create('div', 'legend');
    div.innerHTML = '<h6>Status Legend</h6>' +
        '<i style="background: green"></i> Open<br>' +
        '<i style="background: red"></i> Blocked<br>' +
        '<i style="background: orange"></i> Maintenance';
    return div;
};
legend.addTo(map);

// Create markers array to reference later
var markers = [];

// Markers from DB
<?php
$res = $conn->query("SELECT * FROM condition_map");
while($r = $res->fetch_assoc()) {
    $color = ($r['status']=="Open") ? "green" : (($r['status']=="Blocked") ? "red" : "orange");
    $icon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-' + $color + '.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    echo "var marker = L.marker([" . $r['latitude'] . ", " . $r['longitude'] . "], {icon: icon, title: \"" . addslashes($r['location_name']) . "\"})\n";
    echo ".bindPopup(\"<b>" . $r['location_name'] . "</b><br>Status: <strong>" . $r['status'] . "</strong><br>" . $r['description'] . "\")\n";
    echo ".addTo(map);\n\n";
    echo "markers.push(marker);\n";
}
?>

// Click to set destination
map.on('click', function(e) {
    setDestination(e.latlng.lat, e.latlng.lng, "Selected Location");
});

// Locate user function
function locateUser() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLocation = [position.coords.latitude, position.coords.longitude];
                map.setView(userLocation, 15);
                
                // Add user location marker if not exists
                if (window.userMarker) {
                    map.removeLayer(window.userMarker);
                }
                
                window.userMarker = L.marker(userLocation, {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(map).bindPopup("Your Location").openPopup();
            },
            function(error) {
                Swal.fire("Location Error", "Unable to get your location: " + error.message, "error");
            }
        );
    } else {
        Swal.fire("Not Supported", "Geolocation is not supported by this browser.", "error");
    }
}

// Focus on a specific marker
function focusOnMarker(lat, lng) {
    map.setView([lat, lng], 15);
    
    // Find and open the marker's popup
    markers.forEach(function(marker) {
        var markerLatLng = marker.getLatLng();
        if (markerLatLng.lat === lat && markerLatLng.lng === lng) {
            marker.openPopup();
        }
    });
}

// Set destination and calculate route
function setDestination(lat, lng, locationName) {
    if (!userLocation) {
        Swal.fire({
            title: 'Need Your Location',
            text: 'Please allow location access to get directions',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Locate Me',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                locateUser();
                // Store destination for later use
                window.pendingDestination = {lat: lat, lng: lng, name: locationName};
            }
        });
        return;
    }
    
    calculateRoute(userLocation, [lat, lng], locationName);
}

// Calculate route using OSRM API
function calculateRoute(start, end, locationName) {
    // Clear previous route if any
    if (routingControl) {
        map.removeControl(routingControl);
    }
    
    // Remove previous destination marker if any
    if (destinationMarker) {
        map.removeLayer(destinationMarker);
    }
    
    // Add destination marker
    destinationMarker = L.marker(end, {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-gold.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map).bindPopup("Destination: " + locationName).openPopup();
    
    // Use OSRM API to get route
    fetch(`https://router.project-osrm.org/route/v1/driving/${start[1]},${start[0]};${end[1]},${end[0]}?overview=full&geometries=geojson`)
        .then(response => response.json())
        .then(data => {
            if (data.code !== 'Ok') {
                throw new Error('Unable to calculate route');
            }
            
            // Extract route geometry
            var routeGeometry = data.routes[0].geometry;
            
            // Create a Leaflet GeoJSON layer for the route
            var routeLayer = L.geoJSON(routeGeometry, {
                style: {
                    color: '#3388ff',
                    weight: 5,
                    opacity: 0.7
                }
            }).addTo(map);
            
            // Store reference to remove later
            routingControl = routeLayer;
            
            // Display route instructions
            displayRouteInstructions(data.routes[0], locationName);
            
            // Fit map to show the entire route
            map.fitBounds(routeLayer.getBounds());
            
            // Show route panel
            document.getElementById('routePanel').classList.remove('d-none');
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire("Routing Error", "Could not calculate route. Please try again.", "error");
        });
}

// Display route instructions
function displayRouteInstructions(route, locationName) {
    var instructionsDiv = document.getElementById('routeInstructions');
    var distance = (route.distance / 1000).toFixed(1); // Convert to km
    var duration = Math.floor(route.duration / 60); // Convert to minutes
    
    instructionsDiv.innerHTML = `
        <h6>Route to ${locationName}</h6>
        <p>Distance: ${distance} km<br>
        Estimated time: ${duration} minutes</p>
        <p class="fst-italic">Note: Route may be affected by current road conditions</p>
    `;
}

// Clear the current route
function clearRoute() {
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
    }
    
    if (destinationMarker) {
        map.removeLayer(destinationMarker);
        destinationMarker = null;
    }
    
    document.getElementById('routePanel').classList.add('d-none');
}

// Check if we have a pending destination after location access
if (window.pendingDestination) {
    calculateRoute(userLocation, [window.pendingDestination.lat, window.pendingDestination.lng], window.pendingDestination.name);
    window.pendingDestination = null;
}

// Welcome message
Swal.fire({
    title: "Welcome to Road Condition Map",
    text: "Click on any marker or use the 'Get Directions' button for navigation",
    icon: "info",
    timer: 3000,
    showConfirmButton: false
});
</script>

</body>
</html>