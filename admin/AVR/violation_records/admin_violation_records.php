<?php
// Database Connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "avr"; // change this to your DB name
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// CREATE
if (isset($_POST['add'])) {
    $name = $_POST['violator_name'];
    $type = $_POST['violation_type'];
    $date = $_POST['date'];
    $location = $_POST['location'];
    $penalty = $_POST['penalty'];
    $status = $_POST['status'];

    $conn->query("INSERT INTO violation_records 
        (violator_name, violation_type, date, location, penalty, status) 
        VALUES ('$name','$type','$date','$location','$penalty','$status')");
    echo "<script>window.location='admin_violation_records.php?msg=added';</script>";
}

// UPDATE
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['violator_name'];
    $type = $_POST['violation_type'];
    $date = $_POST['date'];
    $location = $_POST['location'];
    $penalty = $_POST['penalty'];
    $status = $_POST['status'];

    $conn->query("UPDATE violation_records 
        SET violator_name='$name', violation_type='$type', date='$date',
            location='$location', penalty='$penalty', status='$status'
        WHERE id=$id");
    echo "<script>window.location='admin_violation_records.php?msg=updated';</script>";
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM violation_records WHERE id=$id");
    echo "<script>window.location='admin_violation_records.php?msg=deleted';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Violation Record Database (Admin Only)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light p-4">

<div class="container">
    <h2 class="mb-4 text-center">Violation Record Database - Admin Panel</h2>

    <!-- Add Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Add New Record</div>
        <div class="card-body">
            <form method="POST">
                <div class="row mb-2">
                    <div class="col"><input type="text" name="violator_name" class="form-control" placeholder="Violator Name" required></div>
                    <div class="col"><input type="text" name="violation_type" class="form-control" placeholder="Violation Type" required></div>
                </div>
                <div class="row mb-2">
                    <div class="col"><input type="date" name="date" class="form-control" required></div>
                    <div class="col"><input type="text" name="location" class="form-control" placeholder="Location" required></div>
                </div>
                <div class="row mb-2">
                    <div class="col"><input type="number" step="0.01" name="penalty" class="form-control" placeholder="Penalty Amount" required></div>
                    <div class="col">
                        <select name="status" class="form-control">
                            <option value="Pending">Pending</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add" class="btn btn-success">Add Record</button>
            </form>
        </div>
    </div>

    <!-- Records Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">Violation Records</div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Violator</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Penalty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM violation_records ORDER BY id DESC");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['violator_name'] ?></td>
                        <td><?= $row['violation_type'] ?></td>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['location'] ?></td>
                        <td>₱<?= number_format($row['penalty'],2) ?></td>
                        <td><?= $row['status'] ?></td>
                        <td>
                            <!-- Edit Form Inline -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="violator_name" value="<?= $row['violator_name'] ?>">
                                <input type="hidden" name="violation_type" value="<?= $row['violation_type'] ?>">
                                <input type="hidden" name="date" value="<?= $row['date'] ?>">
                                <input type="hidden" name="location" value="<?= $row['location'] ?>">
                                <input type="hidden" name="penalty" value="<?= $row['penalty'] ?>">
                                <input type="hidden" name="status" value="<?= $row['status'] ?>">
                                <button type="submit" name="update" class="btn btn-warning btn-sm">Update</button>
                            </form>

                            <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $row['id'] ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// SweetAlert for Delete
document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function() {
        let id = this.dataset.id;
        Swal.fire({
            title: "Are you sure?",
            text: "This record will be permanently deleted.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = "admin_violation_records.php?delete=" + id;
            }
        });
    });
});

// SweetAlert for Success Messages
<?php if (isset($_GET['msg'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Record <?= $_GET['msg'] ?> successfully!'
    });
<?php endif; ?>
</script>

</body>
</html>
