<?php
session_start();
require_once '../../config/database.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connection
try {
    $pdo_pts = getDBConnection('pts');
    $pdo_ttm = getDBConnection('ttm');
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to get user profile
function getUserProfile($pdo_ttm, $user_id) {
    try {
        $query = "SELECT user_id, username, full_name, email, role FROM users WHERE user_id = :user_id";
        $stmt = $pdo_ttm->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        return ['full_name' => 'User', 'role' => 'Unknown'];
    }
}

// Handle CRUD for payments
if (isset($_POST['add_payment'])) {
    $ticket_id = $_POST['ticket_id'];
    $amount = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    $remarks = $_POST['remarks'];
    $date = date("Y-m-d H:i:s");
    
    // Get offender_id from the ticket
    $ticket_query = $pdo_pts->prepare("SELECT offender_id, fine_amount FROM tickets WHERE id = :ticket_id");
    $ticket_query->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $ticket_query->execute();
    
    if ($ticket_query->rowCount() > 0) {
        $ticket = $ticket_query->fetch();
        $offender_id = $ticket['offender_id'];
        $fine_amount = $ticket['fine_amount'];
        
        // Check if payment exceeds the fine amount
        if ($amount > $fine_amount) {
            $alert = [
                'type' => 'error',
                'message' => "Payment amount cannot exceed the fine amount (₱$fine_amount)"
            ];
        } else {
            // Insert payment with offender_id
            $insert_query = $pdo_pts->prepare("INSERT INTO payments (ticket_id, offender_id, amount_paid, payment_method, remarks, payment_date) 
                                             VALUES (:ticket_id, :offender_id, :amount_paid, :payment_method, :remarks, :payment_date)");
            $insert_query->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $insert_query->bindParam(':offender_id', $offender_id, PDO::PARAM_INT);
            $insert_query->bindParam(':amount_paid', $amount, PDO::PARAM_STR);
            $insert_query->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
            $insert_query->bindParam(':remarks', $remarks, PDO::PARAM_STR);
            $insert_query->bindParam(':payment_date', $date, PDO::PARAM_STR);
            $insert_query->execute();
            
            // Update ticket status if fully paid
            $paid_query = $pdo_pts->prepare("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE ticket_id = :ticket_id");
            $paid_query->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $paid_query->execute();
            $paid_total = $paid_query->fetch()['total_paid'] ?? 0;
            
            if ($paid_total >= $fine_amount) {
                $update_ticket = $pdo_pts->prepare("UPDATE tickets SET status = 'Paid' WHERE id = :ticket_id");
                $update_ticket->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $update_ticket->execute();
                
                $update_offender = $pdo_pts->prepare("UPDATE offenders SET status = 'Paid' WHERE id = :offender_id");
                $update_offender->bindParam(':offender_id', $offender_id, PDO::PARAM_INT);
                $update_offender->execute();
            }
            
            $alert = [
                'type' => 'success',
                'message' => 'Payment successfully added'
            ];
        }
    } else {
        $alert = [
            'type' => 'error',
            'message' => 'Invalid Ticket ID'
        ];
    }
}

if (isset($_POST['delete_payment'])) {
    $id = $_POST['id'];
    
    // Get payment details before deletion
    $payment_query = $pdo_pts->prepare("SELECT ticket_id, offender_id, amount_paid FROM payments WHERE id = :id");
    $payment_query->bindParam(':id', $id, PDO::PARAM_INT);
    $payment_query->execute();
    
    if ($payment_query->rowCount() > 0) {
        $payment = $payment_query->fetch();
        $ticket_id = $payment['ticket_id'];
        $offender_id = $payment['offender_id'];
        $amount_paid = $payment['amount_paid'];
        
        // Delete the payment
        $delete_query = $pdo_pts->prepare("DELETE FROM payments WHERE id = :id");
        $delete_query->bindParam(':id', $id, PDO::PARAM_INT);
        $delete_query->execute();
        
        // Update ticket status
        $ticket_query = $pdo_pts->prepare("SELECT fine_amount FROM tickets WHERE id = :ticket_id");
        $ticket_query->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
        $ticket_query->execute();
        
        if ($ticket_query->rowCount() > 0) {
            $ticket = $ticket_query->fetch();
            $fine_amount = $ticket['fine_amount'];
            
            $paid_query = $pdo_pts->prepare("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE ticket_id = :ticket_id");
            $paid_query->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $paid_query->execute();
            $paid_total = $paid_query->fetch()['total_paid'] ?? 0;
            
            if ($paid_total < $fine_amount) {
                $update_ticket = $pdo_pts->prepare("UPDATE tickets SET status = 'Unpaid' WHERE id = :ticket_id");
                $update_ticket->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $update_ticket->execute();
                
                $update_offender = $pdo_pts->prepare("UPDATE offenders SET status = 'Unpaid' WHERE id = :offender_id");
                $update_offender->bindParam(':offender_id', $offender_id, PDO::PARAM_INT);
                $update_offender->execute();
            }
        }
        
        $alert = [
            'type' => 'success',
            'message' => 'Payment successfully deleted'
        ];
    } else {
        $alert = [
            'type' => 'error',
            'message' => 'Payment not found'
        ];
    }
}

// Data for charts
$total_fine_query = $pdo_pts->query("SELECT SUM(fine_amount) AS total FROM tickets");
$total_fine = $total_fine_query->fetch()['total'] ?? 0;

$total_paid_query = $pdo_pts->query("SELECT SUM(amount_paid) AS total FROM payments");
$total_paid = $total_paid_query->fetch()['total'] ?? 0;

$total_unpaid = $total_fine - $total_paid;

$offenderData = [];
$offenders_query = $pdo_pts->query("SELECT * FROM offenders ORDER BY name ASC");
while ($o = $offenders_query->fetch()) {
    $fine_query = $pdo_pts->prepare("SELECT SUM(fine_amount) AS total FROM tickets WHERE offender_id = :offender_id");
    $fine_query->bindParam(':offender_id', $o['id'], PDO::PARAM_INT);
    $fine_query->execute();
    $fine = $fine_query->fetch()['total'] ?? 0;
    
    $offenderData[] = ["name" => $o['name'], "fine" => (int)$fine];
}

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$active_tab = 'permit_ticketing';
$current_page = 'payment_settlement_management.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Settlement Management - Quezon City Traffic Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .stat-card {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .payment-method-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-cash { background-color: #d4edda; color: #155724; }
        .badge-gcash { background-color: #cce5ff; color: #004085; }
        .badge-credit { background-color: #fff3cd; color: #856404; }
        .badge-other { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../../img/FRSM.png" alt="Logo">
                <div class="text">
                    Quezon City<br>
                    <small>Traffic Management System</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="../dashboard/dashboard.php" class="sidebar-link">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Permit & Ticketing System with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="true">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="patsDropdown">
                    <a href="permit_application_processing.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i> Permit Application Processing
                    </a>
                    <a href="ticket_issuance_control.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Ticket Issuance Control          
                    </a>
                    <a href="payment_settlement_management.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-show'></i> Payment & Settlement Management
                    </a>
                    <a href="offender_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-user'></i> Database of Offenders
                    </a>
                    <a href="compliance_revenue_reports.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bar-chart'></i> Compliance & Revenue Reports
                    </a>
                </div>
                
                <div class="sidebar-section mt-4">User</div>
                <a href="../profile.php" class="sidebar-link">
                    <i class='bx bx-user'></i>
                    <span class="text">Profile</span>
                </a>
                <a href="../logout.php" class="sidebar-link">
                    <i class='bx bx-log-out'></i>
                    <span class="text">Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="page-title">
                    <h1>Payment & Settlement Management</h1>
                    <p>Manage payments, settlements, and financial reports</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Alert Notification -->
            <?php if (isset($alert)): ?>
            <div class="alert alert-<?= $alert['type'] == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <?= $alert['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class='bx bx-money'></i>
                    </div>
                    <div class="stat-content">
                        <h3>₱<?= number_format($total_paid, 2) ?></h3>
                        <p>Total Paid</p>
                        <div class="stat-trend trend-up">
                            <i class='bx bx-up-arrow-alt'></i>
                            <span>Collected payments</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <div class="stat-content">
                        <h3>₱<?= number_format($total_unpaid, 2) ?></h3>
                        <p>Total Unpaid</p>
                        <div class="stat-trend trend-down">
                            <i class='bx bx-down-arrow-alt'></i>
                            <span>Pending collections</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                        <i class='bx bx-receipt'></i>
                    </div>
                    <div class="stat-content">
                        <h3>₱<?= number_format($total_fine, 2) ?></h3>
                        <p>Total Fines</p>
                        <div class="stat-trend trend-up">
                            <i class='bx bx-up-arrow-alt'></i>
                            <span>All issued fines</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class='bx bx-user'></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $offenders_count = $pdo_pts->query("SELECT COUNT(*) as count FROM offenders")->fetch()['count'];
                        ?>
                        <h3><?= $offenders_count ?></h3>
                        <p>Total Offenders</p>
                        <div class="stat-trend trend-up">
                            <i class='bx bx-up-arrow-alt'></i>
                            <span>Registered offenders</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Payment Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-plus-circle'></i> Add New Payment</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="addPaymentForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ticket_id" class="form-label">Ticket ID</label>
                                    <input type="number" class="form-control" id="ticket_id" name="ticket_id" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid (₱)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="Cash">Cash</option>
                                        <option value="GCash">GCash</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <input type="text" class="form-control" id="remarks" name="remarks">
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_payment" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Add Payment
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="charts-container mb-4">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title"><i class='bx bx-pie-chart-alt'></i> Payment Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title"><i class='bx bx-bar-chart-alt'></i> Offender Fines</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payments Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class='bx bx-list-ul'></i> Payment Records</h5>
                    <div>
                        <form method="POST" style="display: inline-block;">
                            <button type="submit" name="export_excel" class="btn btn-success btn-sm">
                                <i class='bx bx-download'></i> Export Excel
                            </button>
                        </form>
                        <form method="POST" style="display: inline-block;">
                            <button type="submit" name="export_pdf" class="btn btn-danger btn-sm">
                                <i class='bx bx-download'></i> Export PDF
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Ticket ID</th>
                                    <th>Offender</th>
                                    <th>Amount Paid</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $payments_query = $pdo_pts->query("
                                    SELECT p.*, o.name as offender_name 
                                    FROM payments p 
                                    LEFT JOIN offenders o ON p.offender_id = o.id 
                                    ORDER BY p.payment_date DESC
                                ");
                                
                                if ($payments_query->rowCount() > 0) {
                                    while ($row = $payments_query->fetch()) {
                                        $method_class = '';
                                        switch ($row['payment_method']) {
                                            case 'Cash': $method_class = 'badge-cash'; break;
                                            case 'GCash': $method_class = 'badge-gcash'; break;
                                            case 'Credit Card': $method_class = 'badge-credit'; break;
                                            default: $method_class = 'badge-other';
                                        }
                                        
                                        echo "<tr>
                                                <td>{$row['id']}</td>
                                                <td>{$row['ticket_id']}</td>
                                                <td>{$row['offender_name']}</td>
                                                <td>₱" . number_format($row['amount_paid'], 2) . "</td>
                                                <td><span class='payment-method-badge $method_class'>{$row['payment_method']}</span></td>
                                                <td>" . date('M j, Y g:i A', strtotime($row['payment_date'])) . "</td>
                                                <td>{$row['remarks']}</td>
                                                <td class='action-buttons'>
                                                    <form method='POST' class='d-inline'>
                                                        <input type='hidden' name='id' value='{$row['id']}'>
                                                        <button type='submit' name='delete_payment' class='btn btn-danger btn-sm' onclick='return confirmDelete()'>
                                                            <i class='bx bx-trash'></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center py-4'>No payment records found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
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
                        legend: { position: 'top' },
                        title: {
                            display: true,
                            text: 'Payment Distribution'
                        }
                    }
                }
            });

            // Bar Chart
            new Chart(document.getElementById('barChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($offenderData, 'name')) ?>,
                    datasets: [{
                        label: 'Total Fine Amount (₱)',
                        data: <?= json_encode(array_column($offenderData, 'fine')) ?>,
                        backgroundColor: '#2196F3'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Offenders'
                            }
                        }
                    }
                }
            });
        });

        // Confirm delete function
        function confirmDelete() {
            return confirm("Are you sure you want to delete this payment?");
        }

        // SweetAlert for form submissions
        <?php if (isset($alert)): ?>
        Swal.fire({
            icon: '<?= $alert['type'] ?>',
            title: '<?= $alert['type'] == 'success' ? 'Success' : 'Error' ?>',
            text: '<?= $alert['message'] ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        <?php endif; ?>

        // Form validation
        document.getElementById('addPaymentForm').addEventListener('submit', function(e) {
            const amount = document.getElementById('amount_paid').value;
            if (amount <= 0) {
                e.preventDefault();
                Swal.fire('Error', 'Payment amount must be greater than zero', 'error');
            }
        });
    </script>
</body>
</html>