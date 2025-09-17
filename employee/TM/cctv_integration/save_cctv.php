<?php
$conn = new mysqli("localhost:3307", "root", "", "chat");
if (isset($_POST['add_cctv'])) {
    $location = $_POST['location'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $stream_url = $_POST['stream_url'];
    $status = $_POST['status'];

    $conn->query("INSERT INTO cctv_feeds (location, latitude, longitude, stream_url, status)
                  VALUES ('$location', '$lat', '$lng', '$stream_url', '$status')");
}
header("Location: admin_cctv.php");
?>
