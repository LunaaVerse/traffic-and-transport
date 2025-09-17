<?php
// Database Connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "avr"; // change to your DB name
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// CREATE
if (isset($_POST['add'])) {
    $report_id   = $_POST['report_id'];
    $assigned_to = $_POST['assigned_to'];
    $priority    = $_POST['priority'];
    $status      = $_POST['status'];
    $remarks     = $_POST['remarks'];

    $conn->query("INSERT INTO escalation_assignment 
        (report_id, assigned_to, priority, status, remarks) 
        VALUES ('$report_id','$assigned_to','$priority','$status','$remarks')");
    echo "<script>window.location='admin_escalation_assignment_modal.php?msg=added';</script>";
}

// UPDATE
if (isset($_POST['update'])) {
    $id          = $_POST['id'];
    $report_id   = $_POST['report_id'];
    $assigned_to = $_POST['assigned_to'];
    $priority    = $_POST['priority'];
    $status      = $_POST['status'];
    $remarks     = $_POST['remarks'];

    $conn->query("UPDATE escalation_assignment 
        SET report_id='$report_id', assigned_to='$assigned_to',
            priority='$priority', status='$status', remarks='$remarks'
        WHERE id=$id");
    echo "<script>window.location='admin_escalation_assignment_modal.php?msg=updated';</script>";
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM escalation_assignment WHERE id=$id");
    echo "<script>window.location='admin_escalation_assignment_modal.php?msg=deleted';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Escalation & Assignment - Admin Modal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light p-4">

<div class="container">
    <h2 class="mb-4 text-center">Escalation & Assignment - Admin Panel</h2>

    <!-- Add Button -->
    <div class="mb-3">
        <button id="addRecord" class="btn btn-success">Add Escalation/Assignment</button>
    </div>

    <!-- Records Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">Escalation & Assignment Records</div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Report ID</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM escalation_assignment ORDER BY id DESC");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['report_id'] ?></td>
                        <td><?= $row['assigned_to'] ?></td>
                        <td><?= $row['priority'] ?></td>
                        <td><?= $row['status'] ?></td>
                        <td><?= $row['remarks'] ?></td>
                        <td>
                            <!-- Update Button -->
                            <button class="btn btn-warning btn-sm update-btn"
                                data-id="<?= $row['id'] ?>"
                                data-report="<?= $row['report_id'] ?>"
                                data-assigned="<?= $row['assigned_to'] ?>"
                                data-priority="<?= $row['priority'] ?>"
                                data-status="<?= $row['status'] ?>"
                                data-remarks="<?= htmlspecialchars($row['remarks'], ENT_QUOTES) ?>"
                            >Update</button>

                            <!-- Delete Button -->
                            <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $row['id'] ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// SweetAlert for Add
document.getElementById('addRecord').addEventListener('click', () => {
    Swal.fire({
        title: 'Add Escalation/Assignment',
        html:
            `<form id="addForm" method="POST">
                <input class="swal2-input" name="report_id" placeholder="Report ID" required>
                <input class="swal2-input" name="assigned_to" placeholder="Assigned To" required>
                <select class="swal2-select" name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
                <select class="swal2-select" name="status" required>
                    <option value="Open">Open</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Escalated">Escalated</option>
                </select>
                <textarea class="swal2-textarea" name="remarks" placeholder="Remarks"></textarea>
            </form>`,
        focusConfirm: false,
        showCancelButton: true,
        preConfirm: () => {
            document.getElementById('addForm').submit();
        }
    });
});

// SweetAlert for Update
document.querySelectorAll('.update-btn').forEach(button => {
    button.addEventListener('click', function() {
        let id = this.dataset.id;
        let report = this.dataset.report;
        let assigned = this.dataset.assigned;
        let priority = this.dataset.priority;
        let status = this.dataset.status;
        let remarks = this.dataset.remarks;

        Swal.fire({
            title: 'Update Record',
            html:
                `<form id="updateForm" method="POST">
                    <input type="hidden" name="id" value="${id}">
                    <input class="swal2-input" name="report_id" value="${report}" placeholder="Report ID" required>
                    <input class="swal2-input" name="assigned_to" value="${assigned}" placeholder="Assigned To" required>
                    <select class="swal2-select" name="priority" required>
                        <option value="Low" ${priority==='Low'?'selected':''}>Low</option>
                        <option value="Medium" ${priority==='Medium'?'selected':''}>Medium</option>
                        <option value="High" ${priority==='High'?'selected':''}>High</option>
                        <option value="Critical" ${priority==='Critical'?'selected':''}>Critical</option>
                    </select>
                    <select class="swal2-select" name="status" required>
                        <option value="Open" ${status==='Open'?'selected':''}>Open</option>
                        <option value="In Progress" ${status==='In Progress'?'selected':''}>In Progress</option>
                        <option value="Resolved" ${status==='Resolved'?'selected':''}>Resolved</option>
                        <option value="Escalated" ${status==='Escalated'?'selected':''}>Escalated</option>
                    </select>
                    <textarea class="swal2-textarea" name="remarks" placeholder="Remarks">${remarks}</textarea>
                </form>`,
            focusConfirm: false,
            showCancelButton: true,
            preConfirm: () => {
                document.getElementById('updateForm').submit();
            }
        });
    });
});

// SweetAlert for Delete
document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function() {
        let id = this.dataset.id;
        Swal.fire({
            title: "Are you sure?",
            text: "This record will be permanently deleted.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = "admin_escalation_assignment_modal.php?delete=" + id;
            }
        });
    });
});

// SweetAlert for Success Messages
<?php if (isset($_GET['msg'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Record <?= $_GET['msg'] ?> successfully!'
    });
<?php endif; ?>
</script>

</body>
</html>
