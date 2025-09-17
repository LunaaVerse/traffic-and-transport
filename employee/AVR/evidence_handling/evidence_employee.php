<?php
$mysqli = new mysqli("localhost:3307", "root", "", "avr");
if ($mysqli->connect_error) { die("Connection failed: " . $mysqli->connect_error); }

$role = "Employee";

// Upload
if (isset($_POST['upload'])) {
    $report_id = $_POST['report_id'];
    if (!empty($_FILES['evidence_file']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) { mkdir($targetDir, 0777, true); }
        $fileName = time() . "_" . basename($_FILES['evidence_file']['name']);
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $targetFilePath)) {
            $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
            $stmt = $mysqli->prepare("INSERT INTO evidence (report_id, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $report_id, $targetFilePath, $fileType, $role);
            $stmt->execute();
            echo "<script>Swal.fire('Success','Evidence Uploaded!','success');</script>";
        }
    }
}

$reports = $mysqli->query("SELECT * FROM reports ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
<title>Employee - Evidence Handling</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{font-family:Arial;margin:20px;}
table{width:100%;border-collapse:collapse;margin-bottom:20px;}
th,td{border:1px solid #ccc;padding:10px;}
th{background:#333;color:#fff;}
.btn{padding:6px 12px;border:none;cursor:pointer;}
.upload{background:green;color:white;}
.view{background:blue;color:white;}
.hidden{display:none;}
</style>
</head>
<body>
<h2>Evidence Handling (Employee Access)</h2>
<table>
<tr><th>ID</th><th>Type</th><th>Location</th><th>Status</th><th>Action</th></tr>
<?php while($r=$reports->fetch_assoc()){ ?>
<tr>
<td><?= $r['id'];?></td>
<td><?= $r['report_type'];?></td>
<td><?= $r['location'];?></td>
<td><?= $r['status'];?></td>
<td><button class="btn view" onclick="toggleEvidence(<?= $r['id'];?>)">Manage Evidence</button></td>
</tr>
<tr id="evidence-<?= $r['id'];?>" class="hidden">
<td colspan="5">
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="report_id" value="<?= $r['id'];?>">
<input type="file" name="evidence_file" required>
<button type="submit" name="upload" class="btn upload">Upload</button>
</form>
<h4>Evidence List</h4>
<table>
<tr><th>ID</th><th>File</th><th>By</th><th>At</th><th>Action</th></tr>
<?php $ev=$mysqli->query("SELECT * FROM evidence WHERE report_id=".$r['id']);
while($e=$ev->fetch_assoc()){ ?>
<tr>
<td><?= $e['id'];?></td>
<td><a href="<?= $e['file_path'];?>" target="_blank">View</a></td>
<td><?= $e['uploaded_by'];?></td>
<td><?= $e['uploaded_at'];?></td>
<td><em>No Delete Permission</em></td>
</tr>
<?php } ?>
</table>
</td></tr>
<?php } ?>
</table>
<script>
function toggleEvidence(id){document.getElementById("evidence-"+id).classList.toggle("hidden");}
</script>
</body>
</html>
