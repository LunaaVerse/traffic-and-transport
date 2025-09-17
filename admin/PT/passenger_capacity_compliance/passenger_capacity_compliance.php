<?php
// passenger_capacity_compliance.php

// Database connection
$conn = new mysqli("localhost:3307", "root", "", "pt");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle CRUD Actions safely
if (isset($_POST['action'])) {

    // Safely get POST values
    $id = $_POST['id'] ?? null;
    $vehicle_plate = $_POST['vehicle_plate'] ?? '';
    $route = $_POST['route'] ?? '';
    $max_capacity = $_POST['max_capacity'] ?? 0;
    $current_passengers = $_POST['current_passengers'] ?? 0;

    // Determine status
    $status = ($current_passengers <= $max_capacity) ? 'Compliant' : 'Overloaded';

    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO passenger_capacity (vehicle_plate, route, max_capacity, current_passengers, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiis", $vehicle_plate, $route, $max_capacity, $current_passengers, $status);
        $stmt->execute();
        echo "<script>Swal.fire('Added!', 'Passenger capacity record added.', 'success');</script>";
    }

    if ($_POST['action'] == 'edit') {
        $stmt = $conn->prepare("UPDATE passenger_capacity SET vehicle_plate=?, route=?, max_capacity=?, current_passengers=?, status=? WHERE id=?");
        $stmt->bind_param("ssiisi", $vehicle_plate, $route, $max_capacity, $current_passengers, $status, $id);
        $stmt->execute();
        echo "<script>Swal.fire('Updated!', 'Passenger capacity record updated.', 'success');</script>";
    }

    if ($_POST['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM passenger_capacity WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>Swal.fire('Deleted!', 'Passenger capacity record deleted.', 'success');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Passenger Capacity Compliance</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #4CAF50; color: white; }
        input, select { padding: 5px; margin: 5px; }
        button { padding: 5px 10px; margin: 2px; cursor: pointer; }
        .compliant { background-color: #d4edda; }
        .overloaded { background-color: #f8d7da; }
    </style>
</head>
<body>

<h2>Passenger Capacity Compliance (Admin Only)</h2>

<!-- Form -->
<form method="POST" id="capacityForm">
    <input type="hidden" name="id" id="id">
    <input type="text" name="vehicle_plate" id="vehicle_plate" placeholder="Vehicle Plate" required>
    <input type="text" name="route" id="route" placeholder="Route" required>
    <input type="number" name="max_capacity" id="max_capacity" placeholder="Max Capacity" required>
    <input type="number" name="current_passengers" id="current_passengers" placeholder="Current Passengers" required>
    <input type="hidden" name="action" id="action" value="add">
    <button type="submit">Save</button>
</form>

<hr>

<!-- Data Table -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Vehicle Plate</th>
            <th>Route</th>
            <th>Max Capacity</th>
            <th>Current Passengers</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT * FROM passenger_capacity ORDER BY id DESC");
        while ($row = $result->fetch_assoc()) {
            $row_class = $row['status'] == 'Compliant' ? 'compliant' : 'overloaded';
            echo "<tr class='{$row_class}'>
                    <td>{$row['id']}</td>
                    <td>{$row['vehicle_plate']}</td>
                    <td>{$row['route']}</td>
                    <td>{$row['max_capacity']}</td>
                    <td>{$row['current_passengers']}</td>
                    <td>{$row['status']}</td>
                    <td>
                        <button type='button' onclick='editRecord(".json_encode($row).")'>Edit</button>
                        <button type='button' onclick='deleteRecord({$row['id']})'>Delete</button>
                    </td>
                  </tr>";
        }
        ?>
    </tbody>
</table>

<script>
function editRecord(data) {
    document.getElementById('id').value = data.id;
    document.getElementById('vehicle_plate').value = data.vehicle_plate;
    document.getElementById('route').value = data.route;
    document.getElementById('max_capacity').value = data.max_capacity;
    document.getElementById('current_passengers').value = data.current_passengers;
    document.getElementById('action').value = 'edit';
}

function deleteRecord(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the record!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
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
