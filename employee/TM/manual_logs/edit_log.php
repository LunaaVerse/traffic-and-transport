<?php
$host = "localhost:3307"; 
$user = "root"; 
$pass = ""; 
$dbname = "chat";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM traffic_logs WHERE id=$id");
$row = $result->fetch_assoc();

if (isset($_POST['update'])) {
  $location = $_POST['location'];
  $volume = $_POST['volume'];
  $remarks = $_POST['remarks'];

  $stmt = $conn->prepare("UPDATE traffic_logs SET location=?, volume=?, remarks=? WHERE id=?");
  $stmt->bind_param("sssi", $location, $volume, $remarks, $id);
  $stmt->execute();
  header("Location: admin_traffic_log.php");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Traffic Log</title>
</head>
<body>
  <h2>Edit Traffic Log</h2>
  <form method="POST">
    <label>Location:</label>
    <input type="text" name="location" value="<?= $row['location'] ?>" required><br><br>
    <label>Volume:</label>
    <select name="volume" required>
      <option <?= $row['volume']=='Low'?'selected':'' ?>>Low</option>
      <option <?= $row['volume']=='Medium'?'selected':'' ?>>Medium</option>
      <option <?= $row['volume']=='High'?'selected':'' ?>>High</option>
    </select><br><br>
    <label>Remarks:</label>
    <textarea name="remarks"><?= $row['remarks'] ?></textarea><br><br>
    <button type="submit" name="update">Update</button>
  </form>
</body>
</html>
