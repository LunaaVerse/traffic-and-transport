<?php
include 'confic/database.php';

$road_name = $_POST['road_name'];
$latitude = $_POST['latitude'];
$longitude = $_POST['longitude'];
$status = $_POST['status'];
$description = $_POST['description'] ?? null;

$sql = "INSERT INTO road_conditions (road_name, latitude, longitude, status, description) 
        VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sddss", $road_name, $latitude, $longitude, $status, $description);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}
?>
