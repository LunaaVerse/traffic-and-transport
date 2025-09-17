<?php
require 'vendor/autoload.php';
$conn = new mysqli("localhost:3307", "root", "", "tm");

use TCPDF;

$type = $_GET['type'];
$start = $_GET['start'];
$end = $_GET['end'];

$sql = "SELECT volume_status, COUNT(*) as total 
        FROM traffic_logs 
        WHERE DATE(created_at) BETWEEN '$start' AND '$end'
        GROUP BY volume_status";
$result = $conn->query($sql);

$low = $medium = $high = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['volume_status'] == 'Low') $low = $row['total'];
    if ($row['volume_status'] == 'Medium') $medium = $row['total'];
    if ($row['volume_status'] == 'High') $high = $row['total'];
}

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

$html = "<h2>$type Monitoring Report</h2>
         <p><strong>From:</strong> $start | <strong>To:</strong> $end</p>
         <table border='1' cellpadding='5'>
            <tr><th>Traffic Volume</th><th>Total Entries</th></tr>
            <tr><td>Low</td><td>$low</td></tr>
            <tr><td>Medium</td><td>$medium</td></tr>
            <tr><td>High</td><td>$high</td></tr>
         </table>";

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("report_$type.pdf", "I");
