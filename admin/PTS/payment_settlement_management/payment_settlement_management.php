<?php
// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "pts";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require __DIR__ . '/../../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// Handle CRUD for payments
if (isset($_POST['add_payment'])) {
    $ticket_id = $_POST['ticket_id'];
    $amount = $_POST['amount_paid'];
    $date = date("Y-m-d");
    
    // Get offender_id from the ticket
    $ticket_query = $conn->query("SELECT offender_id, fine_amount FROM tickets WHERE id='$ticket_id'");
    if ($ticket_query->num_rows > 0) {
        $ticket = $ticket_query->fetch_assoc();
        $offender_id = $ticket['offender_id'];
        $fine_amount = $ticket['fine_amount'];
        
        // Check if payment exceeds the fine amount
        if ($amount > $fine_amount) {
            echo "<script>
                Swal.fire('Error','Payment amount cannot exceed the fine amount (₱$fine_amount)','error');
            </script>";
        } else {
            // Insert payment with offender_id
            $conn->query("INSERT INTO payments (ticket_id, offender_id, amount_paid, payment_date) VALUES ('$ticket_id', '$offender_id', '$amount', '$date')");
            
            // Update ticket status if fully paid
            $paid_total = $conn->query("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE ticket_id='$ticket_id'")->fetch_assoc()['total_paid'] ?? 0;
            if ($paid_total >= $fine_amount) {
                $conn->query("UPDATE tickets SET status='Paid' WHERE id='$ticket_id'");
                $conn->query("UPDATE offenders SET unpaid_tickets = unpaid_tickets - 1 WHERE id='$offender_id'");
            }
            
            echo "<script>
                Swal.fire('Success','Payment Added','success');
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire('Error','Invalid Ticket ID','error');
        </script>";
    }
}

if (isset($_POST['delete_payment'])) {
    $id = $_POST['id'];
    
    // Get payment details before deletion
    $payment_query = $conn->query("SELECT ticket_id, offender_id, amount_paid FROM payments WHERE id='$id'");
    if ($payment_query->num_rows > 0) {
        $payment = $payment_query->fetch_assoc();
        $ticket_id = $payment['ticket_id'];
        $offender_id = $payment['offender_id'];
        $amount_paid = $payment['amount_paid'];
        
        // Delete the payment
        $conn->query("DELETE FROM payments WHERE id='$id'");
        
        // Update ticket status and offender's unpaid count
        $ticket_query = $conn->query("SELECT fine_amount FROM tickets WHERE id='$ticket_id'");
        if ($ticket_query->num_rows > 0) {
            $ticket = $ticket_query->fetch_assoc();
            $fine_amount = $ticket['fine_amount'];
            
            $paid_total = $conn->query("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE ticket_id='$ticket_id'")->fetch_assoc()['total_paid'] ?? 0;
            
            if ($paid_total < $fine_amount) {
                $conn->query("UPDATE tickets SET status='Unpaid' WHERE id='$ticket_id'");
                $conn->query("UPDATE offenders SET unpaid_tickets = unpaid_tickets + 1 WHERE id='$offender_id'");
            }
        }
    }
    
    echo "<script>
        Swal.fire('Deleted','Payment Removed','success');
    </script>";
}

// Export to Excel
if (isset($_POST['export_excel'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Offender');
    $sheet->setCellValue('B1', 'Total Tickets');
    $sheet->setCellValue('C1', 'Unpaid Tickets');
    $sheet->setCellValue('D1', 'Total Fine');
    $sheet->setCellValue('E1', 'Total Paid');

    $row = 2;
    $offenders = $conn->query("SELECT * FROM offenders ORDER BY name ASC");
    while ($o = $offenders->fetch_assoc()) {
        $fine = $conn->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE offender_id='{$o['id']}'")->fetch_assoc()['total'] ?? 0;
        $paid = $conn->query("SELECT SUM(p.amount_paid) AS total FROM payments p JOIN tickets t ON p.ticket_id=t.id WHERE t.offender_id='{$o['id']}'")->fetch_assoc()['total'] ?? 0;

        $sheet->setCellValue("A$row", $o['name']);
        $sheet->setCellValue("B$row", $o['total_tickets']);
        $sheet->setCellValue("C$row", $o['unpaid_tickets']);
        $sheet->setCellValue("D$row", $fine);
        $sheet->setCellValue("E$row", $paid);

        $row++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="settlement_report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// Export to PDF
if (isset($_POST['export_pdf'])) {
    // Make sure to include TCPDF if not already included
    if (!class_exists('TCPDF')) {
        require_once(__DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php');
    }
    
    $pdf = new TCPDF();
    $pdf->AddPage();

    $html = "<h2>Settlement Summary Report</h2>
             <table border='1' cellpadding='5'>
                <tr>
                    <th>Offender</th>
                    <th>Total Tickets</th>
                    <th>Unpaid Tickets</th>
                    <th>Total Fine</th>
                    <th>Total Paid</th>
                </tr>";

    $offenders = $conn->query("SELECT * FROM offenders ORDER BY name ASC");
    while ($o = $offenders->fetch_assoc()) {
        $fine = $conn->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE offender_id='{$o['id']}'")->fetch_assoc()['total'] ?? 0;
        $paid = $conn->query("SELECT SUM(p.amount_paid) AS total FROM payments p JOIN tickets t ON p.ticket_id=t.id WHERE t.offender_id='{$o['id']}'")->fetch_assoc()['total'] ?? 0;

        $html .= "<tr>
                    <td>{$o['name']}</td>
                    <td>{$o['total_tickets']}</td>
                    <td>{$o['unpaid_tickets']}</td>
                    <td>₱$fine</td>
                    <td>₱$paid</td>
                  </tr>";
    }
    $html .= "</table>";

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('settlement_report.pdf', 'D');
    exit();
}

// Data for charts
$total_fine = $conn->query("SELECT SUM(fine_amount) AS total FROM tickets")->fetch_assoc()['total'] ?? 0;
$total_paid = $conn->query("SELECT SUM(amount_paid) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
$total_unpaid = $total_fine - $total_paid;

$offenderData = [];
$offenders = $conn->query("SELECT * FROM offenders ORDER BY name ASC");
while ($o = $offenders->fetch_assoc()) {
    $fine = $conn->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE offender_id='{$o['id']}'")->fetch_assoc()['total'] ?? 0;
    $offenderData[] = ["name" => $o['name'], "fine" => (int)$fine];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment & Settlement Management</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h2>💰 Payment & Settlement Management</h2>

<!-- Add Payment Form -->
<form method="POST" class="mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="number" name="ticket_id" class="form-control" placeholder="Ticket ID" required>
        </div>
        <div class="col-md-3">
            <input type="number" name="amount_paid" class="form-control" placeholder="Amount Paid" step="0.01" min="0" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_payment" class="btn btn-primary">Add Payment</button>
        </div>
    </div>
</form>

<!-- Payments Table -->
<table class="table table-bordered">
    <tr>
        <th>ID</th>
        <th>Ticket ID</th>
        <th>Offender ID</th>
        <th>Amount Paid</th>
        <th>Date</th>
        <th>Action</th>
    </tr>
    <?php
    $res = $conn->query("SELECT * FROM payments ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['ticket_id']}</td>
                <td>{$row['offender_id']}</td>
                <td>₱{$row['amount_paid']}</td>
                <td>{$row['payment_date']}</td>
                <td>
                    <form method='POST' style='display:inline'>
                        <input type='hidden' name='id' value='{$row['id']}'>
                        <button type='submit' name='delete_payment' class='btn btn-danger btn-sm'>Delete</button>
                    </form>
                </td>
              </tr>";
    }
    ?>
</table>

<!-- Export Buttons -->
<div class="mt-3 mb-4">
    <form method="POST" style="display:inline;">
        <button type="submit" name="export_excel" class="btn btn-success">📥 Export to Excel</button>
    </form>
    <form method="POST" style="display:inline;">
        <button type="submit" name="export_pdf" class="btn btn-danger">📥 Export to PDF</button>
    </form>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-md-6">
        <h4>📊 Paid vs Unpaid Fines</h4>
        <canvas id="pieChart"></canvas>
    </div>
    <div class="col-md-6">
        <h4>📈 Offender Fines</h4>
        <canvas id="barChart"></canvas>
    </div>
</div>

<script>
// Pie Chart
new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
            data: [<?= $total_paid ?>, <?= $total_unpaid ?>],
            backgroundColor: ['#4CAF50', '#F44336']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});

// Bar Chart
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($offenderData, 'name')) ?>,
        datasets: [{
            label: 'Total Fine Amount',
            data: <?= json_encode(array_column($offenderData, 'fine')) ?>,
            backgroundColor: '#2196F3'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>