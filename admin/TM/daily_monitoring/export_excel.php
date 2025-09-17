<?php
require 'vendor/autoload.php';
$conn = new mysqli("localhost:3307", "root", "", "tm");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', "$type Monitoring Report");
$sheet->setCellValue('A2', "From: $start");
$sheet->setCellValue('B2', "To: $end");

$sheet->setCellValue('A4', 'Traffic Volume');
$sheet->setCellValue('B4', 'Total Entries');

$sheet->setCellValue('A5', 'Low');
$sheet->setCellValue('B5', $low);

$sheet->setCellValue('A6', 'Medium');
$sheet->setCellValue('B6', $medium);

$sheet->setCellValue('A7', 'High');
$sheet->setCellValue('B7', $high);

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=report_$type.xlsx");
$writer->save("php://output");
