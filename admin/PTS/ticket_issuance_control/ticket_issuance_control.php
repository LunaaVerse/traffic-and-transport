<?php
// Database connection
$conn = new mysqli("localhost:3307", "root", "", "pts");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Helper: Update offender record
function updateOffender($conn, $name) {
    // Count tickets for this offender
    $res = $conn->query("SELECT 
                            COUNT(*) AS total, 
                            SUM(status='Unpaid') AS unpaid 
                         FROM tickets WHERE offender_name='$name'");
    $data = $res->fetch_assoc();
    $total = $data['total'];
    $unpaid = $data['unpaid'];

    // Check if offender already exists
    $check = $conn->query("SELECT * FROM offenders WHERE name='$name'");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE offenders 
                      SET total_tickets=$total, unpaid_tickets=$unpaid 
                      WHERE name='$name'");
    } else {
        $conn->query("INSERT INTO offenders (name, total_tickets, unpaid_tickets) 
                      VALUES ('$name', $total, $unpaid)");
    }
}

// Handle Create Ticket
if (isset($_POST['add'])) {
    $name = $_POST['offender_name'];
    $violation = $_POST['violation_type'];
    $fine = $_POST['fine_amount'];

    $sql = "INSERT INTO tickets (offender_name, violation_type, fine_amount) 
            VALUES ('$name', '$violation', '$fine')";
    if ($conn->query($sql) === TRUE) {
        updateOffender($conn, $name);
        echo "<script>
            Swal.fire('Issued!', 'Ticket has been created and offender database updated!', 'success');
        </script>";
    }
}

// Handle Update Ticket
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];

    $res = $conn->query("SELECT offender_name FROM tickets WHERE id=$id");
    $row = $res->fetch_assoc();
    $offender = $row['offender_name'];

    $sql = "UPDATE tickets SET status='$status' WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        updateOffender($conn, $offender);
        echo "<script>
            Swal.fire('Updated!', 'Ticket status updated & offender database refreshed!', 'success');
        </script>";
    }
}

// Handle Delete Ticket
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $res = $conn->query("SELECT offender_name FROM tickets WHERE id=$id");
    $row = $res->fetch_assoc();
    $offender = $row['offender_name'];

    $sql = "DELETE FROM tickets WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        updateOffender($conn, $offender);
        echo "<script>
            Swal.fire('Deleted!', 'Ticket removed & offender database updated!', 'success');
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Issuance Control (Admin)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4 bg-light">
    <div class="container">
        <h2 class="mb-4">🎫 Ticket Issuance Control (Admin Only)</h2>

        <!-- Add New Ticket Form -->
        <form method="POST" class="mb-4 p-3 border rounded bg-white shadow-sm">
            <h5>Issue New Ticket</h5>
            <div class="row mb-2">
                <div class="col-md-3">
                    <input type="text" name="offender_name" class="form-control" placeholder="Offender Name" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="violation_type" class="form-control" placeholder="Violation Type" required>
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.01" name="fine_amount" class="form-control" placeholder="Fine Amount" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="add" class="btn btn-primary w-100">Issue Ticket</button>
                </div>
            </div>
        </form>

        <!-- Tickets Table -->
        <h4 class="mt-4">Issued Tickets</h4>
        <table class="table table-bordered table-striped shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Offender</th>
                    <th>Violation</th>
                    <th>Fine (₱)</th>
                    <th>Status</th>
                    <th>Issued At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM tickets ORDER BY id DESC");
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['offender_name']}</td>
                        <td>{$row['violation_type']}</td>
                        <td>{$row['fine_amount']}</td>
                        <td>
                            <form method='POST' class='d-flex'>
                                <input type='hidden' name='id' value='{$row['id']}'>
                                <select name='status' class='form-select me-2'>
                                    <option " . ($row['status']=='Unpaid'?'selected':'') . ">Unpaid</option>
                                    <option " . ($row['status']=='Paid'?'selected':'') . ">Paid</option>
                                    <option " . ($row['status']=='Cancelled'?'selected':'') . ">Cancelled</option>
                                </select>
                                <button type='submit' name='update' class='btn btn-success btn-sm'>Update</button>
                            </form>
                        </td>
                        <td>{$row['issued_at']}</td>
                        <td>
                            <form method='POST' onsubmit='return confirmDelete();'>
                                <input type='hidden' name='id' value='{$row['id']}'>
                                <button type='submit' name='delete' class='btn btn-danger btn-sm'>Delete</button>
                            </form>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Offender Database Table -->
        <h4 class="mt-5">🚨 Database of Offenders</h4>
        <table class="table table-bordered table-striped shadow-sm">
            <thead class="table-secondary">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Total Tickets</th>
                    <th>Unpaid Tickets</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM offenders ORDER BY total_tickets DESC");
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['total_tickets']}</td>
                        <td>{$row['unpaid_tickets']}</td>
                        <td>{$row['last_updated']}</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
    function confirmDelete() {
        return confirm("Are you sure you want to delete this ticket?");
    }
    </script>
</body>
</html>
