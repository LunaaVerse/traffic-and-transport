<?php
$conn = new mysqli("localhost:3307","root", "", "rtr");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$district = $_POST['district'];
$barangay = $_POST['barangay'];
$status = $_POST['status'];
$lat = $_POST['latitude'];
$lon = $_POST['longitude'];

// Get district_id from road_districts table (note the 's' at the end)
$district_query = $conn->prepare("SELECT district_id FROM road_districts WHERE district_name = ? AND barangay = ?");
$district_query->bind_param("ss", $district, $barangay);
$district_query->execute();
$district_result = $district_query->get_result();

if ($district_result->num_rows > 0) {
    $district_row = $district_result->fetch_assoc();
    $district_id = $district_row['district_id'];
    
    // Get status_id from road_status_types table
    $status_query = $conn->prepare("SELECT status_id FROM road_status_types WHERE name LIKE ?");
    $status_search = "%" . $status . "%";
    $status_query->bind_param("s", $status_search);
    $status_query->execute();
    $status_result = $status_query->get_result();
    
    $status_id = 1; // Default to Clear
    if ($status_result->num_rows > 0) {
        $status_row = $status_result->fetch_assoc();
        $status_id = $status_row['status_id'];
    }
    
    // Insert into road_updates with the correct structure
    $stmt = $conn->prepare("INSERT INTO road_updates (district_id, status_id, title, description) VALUES (?, ?, ?, ?)");
    $title = "Road Update for " . $barangay;
    $description = "Status: " . $status . " | Coordinates: " . $lat . ", " . $lon;
    $stmt->bind_param("iiss", $district_id, $status_id, $title, $description);
    $stmt->execute();
    $stmt->close();
    
    // Also insert into road_conditions table for mapping
    $stmt2 = $conn->prepare("INSERT INTO road_conditions (road_name, latitude, longitude, status, description) VALUES (?, ?, ?, ?, ?)");
    $road_name = $barangay . ", " . $district;
    $stmt2->bind_param("sddss", $road_name, $lat, $lon, $status, $description);
    $stmt2->execute();
    $stmt2->close();
} else {
    // Handle case where district/barangay combination is not found
    die("Error: District/Barangay combination not found in database.");
}

$district_query->close();
$status_query->close();

header("Location: admin_road_updates.php");
exit;
?>