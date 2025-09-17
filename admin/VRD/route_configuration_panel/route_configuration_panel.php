<?php
// Database Connection
$conn = new mysqli("localhost:3307", "root", "", "vrd");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle CRUD Operations
if (isset($_POST['add'])) {
    $route_name = $_POST['route_name'];
    $start_point = $_POST['start_point'];
    $end_point = $_POST['end_point'];
    $status = $_POST['status'];

    $conn->query("INSERT INTO route_config (route_name, start_point, end_point, status) 
                  VALUES ('$route_name','$start_point','$end_point','$status')");
    echo "<script>
        Swal.fire('Success!','Route Added Successfully!','success')
        .then(() => { window.location = 'route_config.php'; });
    </script>";
}

if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $route_name = $_POST['route_name'];
    $start_point = $_POST['start_point'];
    $end_point = $_POST['end_point'];
    $status = $_POST['status'];

    $conn->query("UPDATE route_config SET 
                    route_name='$route_name',
                    start_point='$start_point',
                    end_point='$end_point',
                    status='$status'
                  WHERE id=$id");
    echo "<script>
        Swal.fire('Updated!','Route Updated Successfully!','success')
        .then(() => { window.location = 'route_config.php'; });
    </script>";
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM route_config WHERE id=$id");
    echo "<script>
        Swal.fire('Deleted!','Route Deleted Successfully!','success')
        .then(() => { window.location = 'route_config.php'; });
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Route Configuration Panel</title>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="text-center mb-4">🚦 Route Configuration Panel (User Access)</h2>

    <!-- Add Route Form -->
    <div class="card p-3 mb-4">
        <h4>Add New Route</h4>
        <form method="POST">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="route_name" class="form-control" placeholder="Route Name" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="start_point" class="form-control" placeholder="Start Point" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="end_point" class="form-control" placeholder="End Point" required>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" name="add" class="btn btn-success">➕</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Routes Table -->
    <div class="card p-3">
        <h4>Configured Routes</h4>
        <table class="table table-bordered table-striped mt-2">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Route Name</th>
                    <th>Start Point</th>
                    <th>End Point</th>
                    <th>Status</th>
                    <th width="20%">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM route_config ORDER BY id DESC");
                while ($row = $result->fetch_assoc()) {
                    echo "
                    <tr>
                        <td>{$row['id']}</td>
                        <td>{$row['route_name']}</td>
                        <td>{$row['start_point']}</td>
                        <td>{$row['end_point']}</td>
                        <td>{$row['status']}</td>
                        <td>
                            <!-- Edit Button triggers modal -->
                            <button class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#edit{$row['id']}'>✏️ Edit</button>
                            <a href='?delete={$row['id']}' class='btn btn-danger btn-sm' 
                                onclick=\"return confirm('Are you sure you want to delete this route?');\">🗑 Delete</a>
                        </td>
                    </tr>
                    
                    <!-- Edit Modal -->
                    <div class='modal fade' id='edit{$row['id']}' tabindex='-1'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title'>Edit Route</h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                </div>
                                <div class='modal-body'>
                                    <form method='POST'>
                                        <input type='hidden' name='id' value='{$row['id']}'>
                                        <div class='mb-2'>
                                            <label>Route Name</label>
                                            <input type='text' name='route_name' class='form-control' value='{$row['route_name']}' required>
                                        </div>
                                        <div class='mb-2'>
                                            <label>Start Point</label>
                                            <input type='text' name='start_point' class='form-control' value='{$row['start_point']}' required>
                                        </div>
                                        <div class='mb-2'>
                                            <label>End Point</label>
                                            <input type='text' name='end_point' class='form-control' value='{$row['end_point']}' required>
                                        </div>
                                        <div class='mb-2'>
                                            <label>Status</label>
                                            <select name='status' class='form-control'>
                                                <option " . ($row['status']=='Active'?'selected':'') . ">Active</option>
                                                <option " . ($row['status']=='Inactive'?'selected':'') . ">Inactive</option>
                                            </select>
                                        </div>
                                        <button type='submit' name='update' class='btn btn-success'>💾 Save</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    ";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
