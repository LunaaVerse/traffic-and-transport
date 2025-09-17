<?php
$conn = new mysqli("localhost:3307","root", "", "rtr");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$district = $_POST['district'];
$barangay = $_POST['barangay'];
$status = $_POST['status'];
$lat = $_POST['latitude'];
$lon = $_POST['longitude'];

$stmt = $conn->prepare("INSERT INTO road_updates (district, barangay, status, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssdd", $district, $barangay, $status, $lat, $lon);
$stmt->execute();
$stmt->close();

header("Location: admin_road_updates.php");
exit;
?>
