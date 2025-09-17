<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "rtr";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get ID from URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM road_updates WHERE id = $id");
    $update = $result->fetch_assoc();
}

// Update record
if (isset($_POST['submit'])) {
    $condition = $_POST['road_condition'];
    $notes = $_POST['notes'];

    $sql = "UPDATE road_updates 
            SET road_condition = '$condition', notes = '$notes'
            WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Update successful!'); window.location='admin_road_updates.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Road Update</title>
</head>
<body>
    <h2>Edit Road Update</h2>
    <form method="POST">
        <p><b>District:</b> <?= $update['district'] ?></p>
        <p><b>Barangay:</b> <?= $update['barangay'] ?></p>

        <label>Condition:</label>
        <select name="road_condition">
            <option value="Open" <?= $update['road_condition']=="Open"?"selected":"" ?>>Open</option>
            <option value="Blocked" <?= $update['road_condition']=="Blocked"?"selected":"" ?>>Blocked</option>
            <option value="Maintenance" <?= $update['road_condition']=="Maintenance"?"selected":"" ?>>Maintenance</option>
        </select><br><br>

        <label>Notes:</label><br>
        <textarea name="notes" rows="3" cols="40"><?= $update['notes'] ?></textarea><br><br>

        <button type="submit" name="submit">Save Changes</button>
    </form>
</body>
</html>
