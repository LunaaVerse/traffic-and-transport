<?php
session_start();
// Access granted for all logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// --------- DATABASE CONNECTION ---------
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "pts";

$conn = new mysqli($host, $user, $pass, $db);
if($conn->connect_error){
    die("Database connection failed: ".$conn->connect_error);
}

// --------- HANDLE CRUD ACTIONS ---------
if(isset($_POST['action'])){
    $action = $_POST['action'];

    if($action == 'create'){
        $name = $_POST['applicant_name'];
        $type = $_POST['permit_type'];
        $purpose = $_POST['purpose'];

        $stmt = $conn->prepare("INSERT INTO permit_applications (applicant_name, permit_type, purpose) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $type, $purpose);
        $stmt->execute();
        echo "<script>Swal.fire('Success','Application Added','success');</script>";

    } elseif($action == 'update'){
        $id = $_POST['id'];
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE permit_applications SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        echo "<script>Swal.fire('Updated','Status Updated','success');</script>";

    } elseif($action == 'delete'){
        $id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM permit_applications WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>Swal.fire('Deleted','Application Removed','success');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permit Application Processing - Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        input, select, textarea { padding: 5px; width: 100%; }
        button { padding: 5px 10px; margin-top: 5px; cursor: pointer; }
    </style>
</head>
<body>

<h2>Permit Application Processing (Admin)</h2>

<!-- CREATE NEW APPLICATION -->
<h3>Add New Application</h3>
<form method="POST">
    <input type="hidden" name="action" value="create">
    <label>Name:</label><br>
    <input type="text" name="applicant_name" required><br>
    <label>Permit Type:</label><br>
    <input type="text" name="permit_type" required><br>
    <label>Purpose:</label><br>
    <textarea name="purpose" required></textarea><br>
    <button type="submit">Add Application</button>
</form>

<!-- DISPLAY APPLICATIONS -->
<h3>All Applications</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Permit Type</th>
        <th>Purpose</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php
    $result = $conn->query("SELECT * FROM permit_applications ORDER BY created_at DESC");
    while($row = $result->fetch_assoc()){
        echo "<tr>";
        echo "<td>".$row['id']."</td>";
        echo "<td>".$row['applicant_name']."</td>";
        echo "<td>".$row['permit_type']."</td>";
        echo "<td>".$row['purpose']."</td>";
        echo "<td>
                <form method='POST' style='display:inline'>
                    <input type='hidden' name='action' value='update'>
                    <input type='hidden' name='id' value='".$row['id']."'>
                    <select name='status' onchange='this.form.submit()'>
                        <option ".($row['status']=='Pending'?'selected':'').">Pending</option>
                        <option ".($row['status']=='Approved'?'selected':'').">Approved</option>
                        <option ".($row['status']=='Rejected'?'selected':'').">Rejected</option>
                    </select>
                </form>
              </td>";
        echo "<td>
                <form method='POST' style='display:inline'>
                    <input type='hidden' name='action' value='delete'>
                    <input type='hidden' name='id' value='".$row['id']."'>
                    <button type='submit'>Delete</button>
                </form>
              </td>";
        echo "</tr>";
    }
    ?>
</table>

</body>
</html>
