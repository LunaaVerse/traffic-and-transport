<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "pt";

// Connect to DB
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle CRUD actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == "add") {
        $route_name = $_POST['route_name'];
        $start_point = $_POST['start_point'];
        $end_point = $_POST['end_point'];
        $schedule_time = $_POST['schedule_time'];
        $vehicle_id = $_POST['vehicle_id'];

        $stmt = $conn->prepare("INSERT INTO route_schedule (route_name, start_point, end_point, schedule_time, vehicle_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $route_name, $start_point, $end_point, $schedule_time, $vehicle_id);
        $stmt->execute();
        echo "<script>
                Swal.fire('Added!', 'Route schedule added successfully.', 'success')
                .then(() => { window.location.href=''; });
              </script>";
        exit();
    }

    if ($action == "edit") {
        $id = $_POST['id'];
        $route_name = $_POST['route_name'];
        $start_point = $_POST['start_point'];
        $end_point = $_POST['end_point'];
        $schedule_time = $_POST['schedule_time'];
        $vehicle_id = $_POST['vehicle_id'];

        $stmt = $conn->prepare("UPDATE route_schedule SET route_name=?, start_point=?, end_point=?, schedule_time=?, vehicle_id=? WHERE id=?");
        $stmt->bind_param("sssssi", $route_name, $start_point, $end_point, $schedule_time, $vehicle_id, $id);
        $stmt->execute();
        echo "<script>
                Swal.fire('Updated!', 'Route schedule updated successfully.', 'success')
                .then(() => { window.location.href=''; });
              </script>";
        exit();
    }

    if ($action == "delete") {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM route_schedule WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>
                Swal.fire('Deleted!', 'Route schedule deleted successfully.', 'success')
                .then(() => { window.location.href=''; });
              </script>";
        exit();
    }
}

// Fetch all routes
$result = $conn->query("SELECT * FROM route_schedule ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Route & Schedule Management</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
table, th, td { border: 1px solid #000; padding: 8px; text-align: center; }
form { margin-top: 20px; }
input, select { padding: 5px; margin: 5px; }
button { padding: 5px 10px; }
</style>
</head>
<body>
<h2>Route & Schedule Management (Admin Only)</h2>

<!-- Add / Edit Form -->
<form method="POST" id="routeForm">
    <input type="hidden" name="id" id="route_id">
    <input type="text" name="route_name" id="route_name" placeholder="Route Name" required>
    <input type="text" name="start_point" id="start_point" placeholder="Start Point" required>
    <input type="text" name="end_point" id="end_point" placeholder="End Point" required>
    <input type="time" name="schedule_time" id="schedule_time" required>
    <input type="text" name="vehicle_id" id="vehicle_id" placeholder="Vehicle ID" required>
    <button type="submit" onclick="setAction('add')">Add</button>
    <button type="submit" onclick="setAction('edit')">Update</button>
    <input type="hidden" name="action" id="action">
</form>

<!-- Table Display -->
<table>
<tr>
    <th>ID</th>
    <th>Route Name</th>
    <th>Start Point</th>
    <th>End Point</th>
    <th>Schedule Time</th>
    <th>Vehicle ID</th>
    <th>Actions</th>
</tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= $row['route_name'] ?></td>
    <td><?= $row['start_point'] ?></td>
    <td><?= $row['end_point'] ?></td>
    <td><?= $row['schedule_time'] ?></td>
    <td><?= $row['vehicle_id'] ?></td>
    <td>
        <button onclick="editRoute(<?= $row['id'] ?>,'<?= $row['route_name'] ?>','<?= $row['start_point'] ?>','<?= $row['end_point'] ?>','<?= $row['schedule_time'] ?>','<?= $row['vehicle_id'] ?>')">Edit</button>
        <button onclick="deleteRoute(<?= $row['id'] ?>)">Delete</button>
    </td>
</tr>
<?php endwhile; ?>
</table>

<script>
// Set action for form
function setAction(act) {
    document.getElementById('action').value = act;
}

// Fill form for edit
function editRoute(id, route, start, end, time, vehicle) {
    document.getElementById('route_id').value = id;
    document.getElementById('route_name').value = route;
    document.getElementById('start_point').value = start;
    document.getElementById('end_point').value = end;
    document.getElementById('schedule_time').value = time;
    document.getElementById('vehicle_id').value = vehicle;
    document.getElementById('action').value = 'edit';
}

// Delete confirmation
function deleteRoute(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the route schedule!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="id" value="${id}">
                              <input type="hidden" name="action" value="delete">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
</body>
</html>
