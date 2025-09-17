<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "rtr";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['district_id'])) {
    $district_id = intval($_GET['district_id']);
    $result = $conn->query("SELECT * FROM barangays WHERE district_id = $district_id");

    $barangays = [];
    while ($row = $result->fetch_assoc()) {
        $barangays[] = $row;
    }

    echo json_encode($barangays);
}
?>
