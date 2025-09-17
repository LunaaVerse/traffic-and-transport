<?php
// AI Rule Management - Single PHP Page with Search & Pagination
session_start();

// Restrict access to Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo "<script>
            alert('Access Denied: Admins Only');
            window.location.href='login.php';
          </script>";
    exit;
}

// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "vrd";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS ai_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle Create / Update / Delete
if (isset($_POST['action'])) {
    $rule_name = $_POST['rule_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO ai_rules (rule_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $rule_name, $description);
        $stmt->execute();
        echo "<script>
                Swal.fire('Success','Rule Added!','success').then(()=>{window.location.href='';});
              </script>";
    }

    if ($_POST['action'] == 'update') {
        $stmt = $conn->prepare("UPDATE ai_rules SET rule_name=?, description=? WHERE id=?");
        $stmt->bind_param("ssi", $rule_name, $description, $id);
        $stmt->execute();
        echo "<script>
                Swal.fire('Success','Rule Updated!','success').then(()=>{window.location.href='';});
              </script>";
    }

    if ($_POST['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM ai_rules WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>
                Swal.fire('Deleted','Rule Removed!','success').then(()=>{window.location.href='';});
              </script>";
    }
}

// Pagination setup
$limit = 5; // rules per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search setup
$search = $_GET['search'] ?? '';
$search_sql = $search ? "WHERE rule_name LIKE '%$search%' OR description LIKE '%$search%'" : "";

// Fetch rules with pagination
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM ai_rules $search_sql");
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$result = $conn->query("SELECT * FROM ai_rules $search_sql ORDER BY created_at DESC LIMIT $start, $limit");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Rule Management</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        form { margin-top: 20px; }
        input, textarea { padding: 5px; width: 100%; margin-bottom: 10px; }
        button { padding: 8px 12px; margin-right: 5px; }
        .pagination a { margin: 0 5px; text-decoration: none; }
        .pagination a.active { font-weight: bold; }
    </style>
</head>
<body>
    <h2>AI Rule Management (Admin Only)</h2>

    <!-- Search -->
    <form method="get">
        <input type="text" name="search" placeholder="Search rules..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
    </form>

    <!-- Add / Edit Form -->
    <form method="post">
        <input type="hidden" name="id" id="rule_id">
        <label>Rule Name:</label>
        <input type="text" name="rule_name" id="rule_name" required>
        <label>Description:</label>
        <textarea name="description" id="description" required></textarea>
        <button type="submit" name="action" value="add" id="addBtn">Add Rule</button>
        <button type="submit" name="action" value="update" id="updateBtn" style="display:none;">Update Rule</button>
    </form>

    <!-- Rules Table -->
    <table>
        <tr>
            <th>ID</th>
            <th>Rule Name</th>
            <th>Description</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['rule_name']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                    <button onclick="editRule(<?= $row['id'] ?>, '<?= addslashes($row['rule_name']) ?>', '<?= addslashes($row['description']) ?>')">Edit</button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" name="action" value="delete" onclick="return confirmDelete(event)">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <!-- Pagination Links -->
    <div class="pagination">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <script>
        function editRule(id, name, desc) {
            document.getElementById('rule_id').value = id;
            document.getElementById('rule_name').value = name;
            document.getElementById('description').value = desc;
            document.getElementById('addBtn').style.display = 'none';
            document.getElementById('updateBtn').style.display = 'inline';
        }

        function confirmDelete(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the rule!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    e.target.form.submit();
                }
            });
            return false;
        }
    </script>
</body>
</html>
