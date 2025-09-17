<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "rtr";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM road_updates WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Record deleted successfully!'); window.location='admin_road_updates.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
