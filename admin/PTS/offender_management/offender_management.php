<?php
// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "pts"; // adjust DB name
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// CREATE
if (isset($_POST['add_offender'])) {
    $name = $_POST['name'];
    $violation = $_POST['violation'];
    $fine_amount = $_POST['fine_amount'];

    $conn->query("INSERT INTO offenders (name, violation, fine_amount) 
                  VALUES ('$name','$violation','$fine_amount')");
    echo "<script>Swal.fire('Success','Offender Added Successfully','success');</script>";
}

// UPDATE
if (isset($_POST['update_offender'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $violation = $_POST['violation'];
    $fine_amount = $_POST['fine_amount'];
    $status = $_POST['status'];

    $conn->query("UPDATE offenders SET 
                    name='$name',
                    violation='$violation',
                    fine_amount='$fine_amount',
                    status='$status'
                  WHERE id='$id'");
    echo "<script>Swal.fire('Updated','Offender Updated Successfully','success');</script>";
}

// DELETE
if (isset($_POST['delete_offender'])) {
    $id = $_POST['id'];
    $conn->query("DELETE FROM offenders WHERE id='$id'");
    echo "<script>Swal.fire('Deleted','Offender Removed Successfully','success');</script>";
}

// Summary stats
$total_offenders = $conn->query("SELECT COUNT(*) AS total FROM offenders")->fetch_assoc()['total'];
$total_unpaid = $conn->query("SELECT SUM(fine_amount) AS total FROM offenders WHERE status='Unpaid'")->fetch_assoc()['total'] ?? 0;
$total_paid = $conn->query("SELECT SUM(fine_amount) AS total FROM offenders WHERE status='Paid'")->fetch_assoc()['total'] ?? 0;

// Search & Filter
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$query = "SELECT * FROM offenders WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (name LIKE '%$search%' OR violation LIKE '%$search%')";
}
if (!empty($filter)) {
    $query .= " AND status='$filter'";
}
$query .= " ORDER BY id DESC";
$res = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Offender Management</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

<h2>🚨 Offender Management (Admin Only)</h2>

<!-- Dashboard Summary -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">👥 Total Offenders</h5>
                <p class="card-text fs-4"><?=$total_offenders?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title">❌ Unpaid Fines</h5>
                <p class="card-text fs-4">₱<?=number_format($total_unpaid,2)?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">✅ Collected Fines</h5>
                <p class="card-text fs-4">₱<?=number_format($total_paid,2)?></p>
            </div>
        </div>
    </div>
</div>

<!-- Add Offender Form -->
<form method="POST" class="mb-4">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="text" name="name" class="form-control" placeholder="Offender Name" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="violation" class="form-control" placeholder="Violation" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="fine_amount" class="form-control" placeholder="Fine Amount" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_offender" class="btn btn-primary">➕ Add Offender</button>
        </div>
    </div>
</form>

<!-- Search & Filter -->
<form method="GET" class="mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by Name or Violation" value="<?=$search?>">
        </div>
        <div class="col-md-3">
            <select name="filter" class="form-control">
                <option value="">-- Filter by Status --</option>
                <option value="Unpaid" <?=$filter=='Unpaid'?'selected':''?>>Unpaid</option>
                <option value="Paid" <?=$filter=='Paid'?'selected':''?>>Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-secondary">🔍 Search</button>
        </div>
        <div class="col-md-2">
            <a href="offender_management.php" class="btn btn-outline-dark">⟳ Reset</a>
        </div>
    </div>
</form>

<!-- Offenders Table -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Violation</th>
            <th>Fine</th>
            <th>Status</th>
            <th>Issued Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $res->fetch_assoc()) { ?>
        <tr>
            <td><?=$row['id']?></td>
            <td><?=$row['name']?></td>
            <td><?=$row['violation']?></td>
            <td>₱<?=$row['fine_amount']?></td>
            <td><?=$row['status']?></td>
            <td><?=$row['issued_date']?></td>
            <td>
                <!-- Edit -->
                <form method="POST" style="display:inline-block">
                    <input type="hidden" name="id" value="<?=$row['id']?>">
                    <input type="hidden" name="name" value="<?=$row['name']?>">
                    <input type="hidden" name="violation" value="<?=$row['violation']?>">
                    <input type="hidden" name="fine_amount" value="<?=$row['fine_amount']?>">
                    <input type="hidden" name="status" value="<?=$row['status']?>">
                    <button type="submit" name="edit_form" class="btn btn-warning btn-sm">✏️ Edit</button>
                </form>

                <!-- Delete -->
                <form method="POST" style="display:inline-block">
                    <input type="hidden" name="id" value="<?=$row['id']?>">
                    <button type="submit" name="delete_offender" class="btn btn-danger btn-sm">🗑 Delete</button>
                </form>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<?php if (isset($_POST['edit_form'])) { ?>
<hr>
<h4>✏️ Update Offender</h4>
<form method="POST" class="mb-4">
    <input type="hidden" name="id" value="<?=$_POST['id']?>">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="text" name="name" class="form-control" value="<?=$_POST['name']?>" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="violation" class="form-control" value="<?=$_POST['violation']?>" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="fine_amount" class="form-control" value="<?=$_POST['fine_amount']?>" required>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-control">
                <option value="Unpaid" <?=$_POST['status']=='Unpaid'?'selected':''?>>Unpaid</option>
                <option value="Paid" <?=$_POST['status']=='Paid'?'selected':''?>>Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="update_offender" class="btn btn-success">💾 Save</button>
        </div>
    </div>
</form>
<?php } ?>

</body>
</html>
