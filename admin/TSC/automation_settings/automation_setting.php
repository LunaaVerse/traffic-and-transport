<?php
// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "tsc";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Add Setting
if(isset($_POST['add'])) {
    $name = $_POST['setting_name'];
    $value = $_POST['value'];
    $desc = $_POST['description'];
    $sql = "INSERT INTO automation_settings (setting_name, value, description) VALUES ('$name','$value','$desc')";
    if($conn->query($sql)){
        echo "<script>
            Swal.fire('Success', 'Setting added successfully!', 'success').then(()=>{window.location='automation_settings.php'})
        </script>";
    }
}

// Handle Update Setting
if(isset($_POST['update'])){
    $id = $_POST['id'];
    $name = $_POST['setting_name'];
    $value = $_POST['value'];
    $desc = $_POST['description'];
    $sql = "UPDATE automation_settings SET setting_name='$name', value='$value', description='$desc' WHERE id=$id";
    if($conn->query($sql)){
        echo "<script>
            Swal.fire('Updated', 'Setting updated successfully!', 'success').then(()=>{window.location='automation_settings.php'})
        </script>";
    }
}

// Handle Delete Setting
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $sql = "DELETE FROM automation_settings WHERE id=$id";
    if($conn->query($sql)){
        echo "<script>
            Swal.fire('Deleted', 'Setting deleted successfully!', 'success').then(()=>{window.location='automation_settings.php'})
        </script>";
    }
}

// Fetch all settings
$result = $conn->query("SELECT * FROM automation_settings");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Automation Settings</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; }
    th { background-color: #f2f2f2; }
    form { margin-bottom: 20px; }
</style>
</head>
<body>
<h2>Automation Settings (Admin Only)</h2>

<!-- Add / Update Form -->
<form method="POST">
    <input type="hidden" name="id" id="id">
    <input type="text" name="setting_name" id="setting_name" placeholder="Setting Name" required>
    <input type="text" name="value" id="value" placeholder="Value" required>
    <input type="text" name="description" id="description" placeholder="Description">
    <button type="submit" name="add" id="addBtn">Add</button>
    <button type="submit" name="update" id="updateBtn" style="display:none;">Update</button>
    <button type="button" id="cancelBtn" style="display:none;">Cancel</button>
</form>

<!-- Settings Table -->
<table>
    <tr>
        <th>ID</th>
        <th>Setting Name</th>
        <th>Value</th>
        <th>Description</th>
        <th>Actions</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['setting_name']; ?></td>
        <td><?php echo $row['value']; ?></td>
        <td><?php echo $row['description']; ?></td>
        <td>
            <button onclick="editSetting('<?php echo $row['id']; ?>','<?php echo $row['setting_name']; ?>','<?php echo $row['value']; ?>','<?php echo $row['description']; ?>')">Edit</button>
            <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<script>
function editSetting(id, name, value, desc){
    document.getElementById('id').value = id;
    document.getElementById('setting_name').value = name;
    document.getElementById('value').value = value;
    document.getElementById('description').value = desc;
    document.getElementById('addBtn').style.display = 'none';
    document.getElementById('updateBtn').style.display = 'inline';
    document.getElementById('cancelBtn').style.display = 'inline';
}

document.getElementById('cancelBtn').onclick = function(){
    document.getElementById('id').value = '';
    document.getElementById('setting_name').value = '';
    document.getElementById('value').value = '';
    document.getElementById('description').value = '';
    document.getElementById('addBtn').style.display = 'inline';
    document.getElementById('updateBtn').style.display = 'none';
    document.getElementById('cancelBtn').style.display = 'none';
}
</script>
</body>
</html>
