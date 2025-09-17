<?php
// --- DB CONNECTION ---
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "chat";  // change to your database name
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- CREATE (Insert new record) ---
if (isset($_POST['add'])) {
    $date = $_POST['log_date'];
    $district = $_POST['district'];
    $barangay = $_POST['barangay'];
    $status = $_POST['volume_status'];
    $sql = "INSERT INTO traffic_volume (log_date, district, barangay, volume_status)
            VALUES ('$date','$district','$barangay','$status')";
    $conn->query($sql);
}

// --- UPDATE ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $date = $_POST['log_date'];
    $district = $_POST['district'];
    $barangay = $_POST['barangay'];
    $status = $_POST['volume_status'];
    $sql = "UPDATE traffic_volume 
            SET log_date='$date', district='$district', barangay='$barangay', volume_status='$status'
            WHERE id=$id";
    $conn->query($sql);
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM traffic_volume WHERE id=$id";
    $conn->query($sql);
}

// --- READ (Fetch all logs) ---
$result = $conn->query("SELECT * FROM traffic_volume ORDER BY log_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Traffic Volume Dashboard - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2 class="mb-4 text-center">🚦 Traffic Volume Dashboard (Admin)</h2>

  <!-- Add New Log Form -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title">Add Traffic Volume Log</h5>
      <form method="POST" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Date</label>
          <input type="date" name="log_date" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">District</label>
          <select name="district" class="form-select" required>
            <option value="">-- Select District --</option>
            <option>District I</option>
            <option>District II</option>
            <option>District III</option>
            <option>District IV</option>
            <option>District V</option>
            <option>District VI</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Barangay</label>
          <input type="text" name="barangay" class="form-control" placeholder="Enter Barangay" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Volume Status</label>
          <select name="volume_status" class="form-select" required>
            <option>Low</option>
            <option>Medium</option>
            <option>High</option>
          </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="submit" name="add" class="btn btn-success w-100">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Traffic Volume Table -->
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Traffic Volume Records</h5>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>District</th>
            <th>Barangay</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $row['log_date'] ?></td>
            <td><?= $row['district'] ?></td>
            <td><?= $row['barangay'] ?></td>
            <td>
              <span class="badge bg-<?= 
                $row['volume_status']=='High'?'danger':($row['volume_status']=='Medium'?'warning':'success') ?>">
                <?= $row['volume_status'] ?>
              </span>
            </td>
            <td>
              <!-- Edit Button (modal trigger) -->
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit<?= $row['id'] ?>">Edit</button>
              <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?');">Delete</a>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="edit<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Traffic Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="mb-3">
                      <label>Date</label>
                      <input type="date" name="log_date" value="<?= $row['log_date'] ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                      <label>District</label>
                      <input type="text" name="district" value="<?= $row['district'] ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                      <label>Barangay</label>
                      <input type="text" name="barangay" value="<?= $row['barangay'] ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                      <label>Status</label>
                      <select name="volume_status" class="form-select">
                        <option <?= $row['volume_status']=='Low'?'selected':'' ?>>Low</option>
                        <option <?= $row['volume_status']=='Medium'?'selected':'' ?>>Medium</option>
                        <option <?= $row['volume_status']=='High'?'selected':'' ?>>High</option>
                      </select>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
