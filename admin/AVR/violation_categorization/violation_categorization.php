<?php
// Database Connection
$mysqli = new mysqli("localhost:3307", "root", "", "avr");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle Create
if (isset($_POST['add'])) {
    $category_name = $_POST['category_name'];
    $description = $_POST['description'];

    $stmt = $mysqli->prepare("INSERT INTO violation_categories (category_name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $category_name, $description);
    $stmt->execute();
    echo "<script>Swal.fire('Success', 'Category Added!', 'success');</script>";
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $category_name = $_POST['category_name'];
    $description = $_POST['description'];

    $stmt = $mysqli->prepare("UPDATE violation_categories SET category_name=?, description=? WHERE id=?");
    $stmt->bind_param("ssi", $category_name, $description, $id);
    $stmt->execute();
    echo "<script>Swal.fire('Updated', 'Category Updated!', 'success');</script>";
}

// Handle Delete
if (isset($_POST['delete'])) {
    $id = $_POST['id'];

    $stmt = $mysqli->prepare("DELETE FROM violation_categories WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "<script>Swal.fire('Deleted', 'Category Removed!', 'success');</script>";
}

// Fetch Categories
$result = $mysqli->query("SELECT * FROM violation_categories ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Violation Categorization</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #333; color: #fff; }
        form { margin-top: 20px; }
        input, textarea { padding: 8px; width: 100%; margin-bottom: 10px; }
        button { padding: 8px 15px; border: none; cursor: pointer; }
        .add { background: green; color: white; }
        .edit { background: orange; color: white; }
        .delete { background: red; color: white; }
    </style>
</head>
<body>
    <h2>Violation Categorization (Admin Only)</h2>

    <!-- Add Category Form -->
    <form method="POST">
        <input type="text" name="category_name" placeholder="Category Name" required>
        <textarea name="description" placeholder="Description"></textarea>
        <button type="submit" name="add" class="add">Add Category</button>
    </form>

    <!-- Categories Table -->
    <table>
        <tr>
            <th>ID</th>
            <th>Category</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= htmlspecialchars($row['category_name']); ?></td>
            <td><?= htmlspecialchars($row['description']); ?></td>
            <td>
                <!-- Edit Form -->
                <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                    <input type="text" name="category_name" value="<?= htmlspecialchars($row['category_name']); ?>" required>
                    <input type="text" name="description" value="<?= htmlspecialchars($row['description']); ?>">
                    <button type="submit" name="update" class="edit">Update</button>
                </form>

                <!-- Delete Form -->
                <form method="POST" style="display:inline-block;" onsubmit="return confirmDelete(this);">
                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                    <button type="submit" name="delete" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </table>

    <script>
        function confirmDelete(form) {
            event.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the category.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            return false;
        }
    </script>
</body>
</html>
