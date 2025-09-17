<?php
// db.php - database connection
$host = 'localhost:3307';
$db   = 'rtr';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    exit("Database connection failed: ".$e->getMessage());
}

// Handle Add Road Update
if (isset($_POST['add'])) {
    $location = $_POST['location'] ?? '';
    $condition = $_POST['road_condition'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($location) && !empty($condition)) {
        $stmt = $pdo->prepare("INSERT INTO road_updates (location, road_condition, description) VALUES (?, ?, ?)");
        $stmt->execute([$location, $condition, $description]);
    }
    exit; // Exit to avoid reload during AJAX
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'] ?? 0;
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM road_updates WHERE id=?");
        $stmt->execute([$id]);
    }
    exit; // Exit to avoid reload during AJAX
}

// Handle Edit
if (isset($_POST['edit'])) {
    $id = $_POST['id'] ?? 0;
    $location = $_POST['location'] ?? '';
    $condition = $_POST['road_condition'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($id > 0 && !empty($location) && !empty($condition)) {
        $stmt = $pdo->prepare("UPDATE road_updates SET location=?, road_condition=?, description=? WHERE id=?");
        $stmt->execute([$location, $condition, $description, $id]);
    }
    exit; // Exit to avoid reload during AJAX
}

// Function to fetch updates (for AJAX)
function fetch_updates($pdo) {
    try {
        // Check if the table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'road_updates'")->rowCount() > 0;
        
        if (!$tableExists) {
            return '<tr><td colspan="6">Table does not exist. Please run the setup script.</td></tr>';
        }
        
        // Check if the updated_at column exists before using it in ORDER BY
        $columns = $pdo->query("SHOW COLUMNS FROM road_updates")->fetchAll();
        $has_updated_at = false;
        foreach ($columns as $column) {
            if ($column['Field'] == 'updated_at') {
                $has_updated_at = true;
                break;
            }
        }
        
        // Use appropriate ORDER BY clause
        $order_by = $has_updated_at ? "updated_at DESC" : "id DESC";
        $updates = $pdo->query("SELECT * FROM road_updates ORDER BY $order_by")->fetchAll();
        
        if (empty($updates)) {
            return '<tr><td colspan="6">No road updates found.</td></tr>';
        }
        
        $html = '';
        foreach ($updates as $update) {
            $id = $update['id'] ?? 0;
            $location = $update['location'] ?? '';
            $road_condition = $update['road_condition'] ?? '';
            $description = $update['description'] ?? '';
            $updated_at = $has_updated_at ? ($update['updated_at'] ?? 'N/A') : 'N/A';
            
            $html .= '<tr class="'.$road_condition.'">
                <td>'.$id.'</td>
                <td>'.htmlspecialchars($location).'</td>
                <td>'.$road_condition.'</td>
                <td>'.htmlspecialchars($description).'</td>
                <td>'.$updated_at.'</td>
                <td>
                    <button onclick="showEditModal('.$id.')" class="btn btn-edit">Edit</button>
                    <button onclick="confirmDelete('.$id.')" class="btn btn-delete">Delete</button>
                    <div id="editModal'.$id.'" class="editModal">
                        <form onsubmit="editUpdate(event, '.$id.')" class="editForm">
                            <h3>Edit Road Update</h3>
                            <input type="text" name="location" value="'.htmlspecialchars($location).'" required>
                            <select name="road_condition" required>
                                <option value="Open" '.($road_condition=='Open'?'selected':'').'>Open</option>
                                <option value="Blocked" '.($road_condition=='Blocked'?'selected':'').'>Blocked</option>
                                <option value="Maintenance" '.($road_condition=='Maintenance'?'selected':'').'>Maintenance</option>
                            </select>
                            <input type="text" name="description" value="'.htmlspecialchars($description).'">
                            <button type="submit" class="btn btn-edit">Save</button>
                            <button type="button" onclick="hideEditModal('.$id.')" class="btn btn-delete">Cancel</button>
                        </form>
                    </div>
                </td>
            </tr>';
        }
        return $html;
    } catch (Exception $e) {
        return '<tr><td colspan="6">Error fetching data: '.$e->getMessage().'</td></tr>';
    }
}

// If AJAX request to fetch updates
if (isset($_GET['fetch'])) {
    echo fetch_updates($pdo);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Road Updates</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
        .Open { background-color: #d4edda; }       
        .Blocked { background-color: #f8d7da; }    
        .Maintenance { background-color: #fff3cd; } 
        .btn { padding: 5px 10px; border: none; cursor: pointer; border-radius: 3px; }
        .btn-edit { background-color: #007bff; color: #fff; }
        .btn-delete { background-color: #dc3545; color: #fff; }
        .editModal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); }
        .editForm { background:#fff; padding:20px; margin:50px auto; width:300px; border-radius:10px; }
    </style>
</head>
<body>
    <h2>Real-Time Road Updates - Admin Panel</h2>

    <!-- Add Road Update Form -->
    <form onsubmit="addUpdate(event)">
        <input type="text" name="location" placeholder="Location" required>
        <select name="road_condition" required>
            <option value="Open">Open</option>
            <option value="Blocked">Blocked</option>
            <option value="Maintenance">Maintenance</option>
        </select>
        <input type="text" name="description" placeholder="Description">
        <button type="submit" class="btn btn-edit">Add Update</button>
    </form>

    <!-- Road Updates Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Description</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="updatesTable">
            <?php echo fetch_updates($pdo); ?>
        </tbody>
    </table>

<script>
function fetchUpdates() {
    fetch('admin_road_updates.php?fetch=1')
        .then(response => response.text())
        .then(data => document.getElementById('updatesTable').innerHTML = data);
}

// Auto-refresh every 5 seconds
setInterval(fetchUpdates, 5000);

// Add update via AJAX
function addUpdate(e) {
    e.preventDefault();
    let form = e.target;
    let data = new FormData(form);
    data.append('add', '1');
    fetch('admin_road_updates.php', { method:'POST', body:data })
        .then(() => { form.reset(); fetchUpdates(); });
}

// Delete with confirmation
function confirmDelete(id) {
    if(confirm("Are you sure you want to delete this update?")) {
        fetch('admin_road_updates.php?delete='+id).then(fetchUpdates);
    }
}

// Show/hide edit modal
function showEditModal(id) { 
    document.getElementById('editModal'+id).style.display='block'; 
}
function hideEditModal(id) { 
    document.getElementById('editModal'+id).style.display='none'; 
}

// Edit via AJAX
function editUpdate(e, id) {
    e.preventDefault();
    let form = e.target;
    let data = new FormData(form);
    data.append('edit', '1');
    data.append('id', id);
    fetch('admin_road_updates.php', { method:'POST', body:data })
        .then(() => { hideEditModal(id); fetchUpdates(); });
}
</script>
</body>
</html>