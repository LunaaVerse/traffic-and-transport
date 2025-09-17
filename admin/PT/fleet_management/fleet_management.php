<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "pt";

// Database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Handle CRUD Operations ---
$action = $_POST['action'] ?? '';

if ($action == 'add') {
    $name = $_POST['vehicle_name'];
    $type = $_POST['vehicle_type'];
    $plate = $_POST['plate_number'];
    $status = $_POST['status'];
    $service = $_POST['last_service_date'];

    $stmt = $conn->prepare("INSERT INTO fleet_management (vehicle_name, vehicle_type, plate_number, status, last_service_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $type, $plate, $status, $service);
    if ($stmt->execute()) {
        echo "<script>Swal.fire('Success','Vehicle added successfully','success').then(()=>{window.location='fleet_management.php'})</script>";
    } else {
        echo "<script>Swal.fire('Error','Failed to add vehicle','error')</script>";
    }
}

if ($action == 'update') {
    $id = $_POST['id'];
    $name = $_POST['vehicle_name'];
    $type = $_POST['vehicle_type'];
    $plate = $_POST['plate_number'];
    $status = $_POST['status'];
    $service = $_POST['last_service_date'];

    $stmt = $conn->prepare("UPDATE fleet_management SET vehicle_name=?, vehicle_type=?, plate_number=?, status=?, last_service_date=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $type, $plate, $status, $service, $id);
    if ($stmt->execute()) {
        echo "<script>Swal.fire('Success','Vehicle updated successfully','success').then(()=>{window.location='fleet_management.php'})</script>";
    } else {
        echo "<script>Swal.fire('Error','Failed to update vehicle','error')</script>";
    }
}

if ($action == 'delete') {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM fleet_management WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>Swal.fire('Deleted','Vehicle removed','success').then(()=>{window.location='fleet_management.php'})</script>";
    } else {
        echo "<script>Swal.fire('Error','Failed to delete vehicle','error')</script>";
    }
}

// --- Fetch data ---
$result = $conn->query("SELECT * FROM fleet_management");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fleet Management</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
table {width:100%; border-collapse: collapse;}
th, td {padding: 8px; text-align: left; border: 1px solid #ccc;}
form {margin-bottom:20px;}
input, select {padding:5px; margin:5px;}
button {padding:5px 10px; margin:5px;}
</style>
</head>
<body>
<h2>Admin Fleet Management</h2>

<!-- Add Vehicle Form -->
<form method="POST">
    <input type="hidden" name="action" value="add">
    <input type="text" name="vehicle_name" placeholder="Vehicle Name" required>
    <input type="text" name="vehicle_type" placeholder="Vehicle Type" required>
    <input type="text" name="plate_number" placeholder="Plate Number" required>
    <select name="status">
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="Maintenance">Maintenance</option>
    </select>
    <input type="date" name="last_service_date">
    <button type="submit">Add Vehicle</button>
</form>

<!-- Vehicle Table -->
<table>
    <tr>
        <th>ID</th>
        <th>Vehicle Name</th>
        <th>Type</th>
        <th>Plate</th>
        <th>Status</th>
        <th>Last Service</th>
        <th>Actions</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <form method="POST">
            <td><?= $row['id'] ?></td>
            <td><input type="text" name="vehicle_name" value="<?= $row['vehicle_name'] ?>"></td>
            <td><input type="text" name="vehicle_type" value="<?= $row['vehicle_type'] ?>"></td>
            <td><input type="text" name="plate_number" value="<?= $row['plate_number'] ?>"></td>
            <td>
                <select name="status">
                    <option <?= $row['status']=='Active'?'selected':'' ?> value="Active">Active</option>
                    <option <?= $row['status']=='Inactive'?'selected':'' ?> value="Inactive">Inactive</option>
                    <option <?= $row['status']=='Maintenance'?'selected':'' ?> value="Maintenance">Maintenance</option>
                </select>
            </td>
            <td><input type="date" name="last_service_date" value="<?= $row['last_service_date'] ?>"></td>
            <td>
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <button type="submit" name="action" value="update">Update</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Delete this vehicle?')">Delete</button>
            </td>
        </form>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
