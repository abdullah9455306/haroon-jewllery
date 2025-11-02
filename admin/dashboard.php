<?php
require_once 'includes/admin-header.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch dashboard statistics from database
$stats = [];
$recentOrders = [];
$salesData = [];
$paymentData = [];

try {
    // Total Revenue - Fixed column names to match schema
    $revenueQuery = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE order_status = 'delivered'";
    $revenueStmt = $conn->prepare($revenueQuery);
    $revenueStmt->execute();
    $revenueData = $revenueStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_revenue'] = $revenueData['total_revenue'] ?: 0;

    // Total Orders
    $ordersQuery = "SELECT COUNT(*) as total_orders FROM orders";
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->execute();
    $ordersData = $ordersStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_orders'] = $ordersData['total_orders'] ?: 0;

    // Total Products - Fixed table name (products instead of products)
    $productsQuery = "SELECT COUNT(*) as total_products FROM products WHERE status = 'active'";
    $productsStmt = $conn->prepare($productsQuery);
    $productsStmt->execute();
    $productsData = $productsStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_products'] = $productsData['total_products'] ?: 0;

    // Total Customers - Using users table instead of customers table
    $customersQuery = "SELECT COUNT(*) as total_customers FROM users WHERE status = 'active' AND role = 'customer'";
    $customersStmt = $conn->prepare($customersQuery);
    $customersStmt->execute();
    $customersData = $customersStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_customers'] = $customersData['total_customers'] ?: 0;

    // Recent Orders (last 5 orders) - Fixed column names to match schema
    $recentOrdersQuery = "
        SELECT o.id as order_id, o.order_number, o.customer_name, o.total_amount,
               o.payment_method, o.order_status, o.created_at
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 5
    ";
    $recentOrdersStmt = $conn->prepare($recentOrdersQuery);
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Quick Stats
    // Pending Orders - Fixed status field name
    $pendingOrdersQuery = "SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'pending'";
    $pendingOrdersStmt = $conn->prepare($pendingOrdersQuery);
    $pendingOrdersStmt->execute();
    $pendingOrdersData = $pendingOrdersStmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_orders'] = $pendingOrdersData['pending_orders'] ?: 0;

    // Low Stock Products
    $lowStockQuery = "SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active'";
    $lowStockStmt = $conn->prepare($lowStockQuery);
    $lowStockStmt->execute();
    $lowStockData = $lowStockStmt->fetch(PDO::FETCH_ASSOC);
    $stats['low_stock'] = $lowStockData['low_stock'] ?: 0;

    // Today's Revenue - Fixed status field name
    $todayRevenueQuery = "SELECT SUM(total_amount) as today_revenue FROM orders WHERE DATE(created_at) = CURDATE() AND order_status = 'delivered'";
    $todayRevenueStmt = $conn->prepare($todayRevenueQuery);
    $todayRevenueStmt->execute();
    $todayRevenueData = $todayRevenueStmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_revenue'] = $todayRevenueData['today_revenue'] ?: 0;

    // New Customers (today) - Using users table
    $newCustomersQuery = "SELECT COUNT(*) as new_customers FROM users WHERE DATE(created_at) = CURDATE() AND role = 'customer'";
    $newCustomersStmt = $conn->prepare($newCustomersQuery);
    $newCustomersStmt->execute();
    $newCustomersData = $newCustomersStmt->fetch(PDO::FETCH_ASSOC);
    $stats['new_customers'] = $newCustomersData['new_customers'] ?: 0;

    // Sales data for chart (last 7 days) - Fixed status field name
    $salesChartQuery = "
        SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND order_status = 'delivered'
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $salesChartStmt = $conn->prepare($salesChartQuery);
    $salesChartStmt->execute();
    $salesData = $salesChartStmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment methods data for pie chart - Fixed status field name
    $paymentMethodsQuery = "
        SELECT payment_method, COUNT(*) as count
        FROM orders
        WHERE order_status = 'delivered'
        GROUP BY payment_method
    ";
    $paymentMethodsStmt = $conn->prepare($paymentMethodsQuery);
    $paymentMethodsStmt->execute();
    $paymentData = $paymentMethodsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading dashboard data: " . $e->getMessage() . "</div>";
    // Initialize empty arrays to prevent undefined variable errors
    $stats = [
        'total_revenue' => 0,
        'total_orders' => 0,
        'total_products' => 0,
        'total_customers' => 0,
        'pending_orders' => 0,
        'low_stock' => 0,
        'today_revenue' => 0,
        'new_customers' => 0
    ];
    $recentOrders = [];
    $salesData = [];
    $paymentData = [];
}
?>

<!-- Dashboard Content -->
<div class="container-fluid py-4">
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-dark">PKR <div class="text-gold" style="display:inline"><?= number_format($stats['total_revenue']) ?></div></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text text-uppercase mb-1">
                                Total Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gold"><?= number_format($stats['total_orders']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text text-uppercase mb-1">
                                Products</div>
                            <div class="h5 mb-0 font-weight-bold text-gold"><?= number_format($stats['total_products']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text text-uppercase mb-1">
                                Customers</div>
                            <div class="h5 mb-0 font-weight-bold text-gold"><?= number_format($stats['total_customers']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-dark">Sales Overview</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-dark">Payment Methods</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Quick Stats -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Recent Orders</h6>
                    <a href="orders.php" class="btn btn-sm btn-gold">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentOrders)): ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['order_number'] ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td>PKR <?= number_format($order['total_amount']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $order['payment_method'] == 'jazzcash_mobile' ? 'info' :
                                                    ($order['payment_method'] == 'jazzcash_card' ? 'warning' :
                                                    ($order['payment_method'] == 'bank' ? 'primary' : 'secondary'))
                                                ?>">
                                                    <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $order['order_status'] == 'delivered' ? 'success' :
                                                    ($order['order_status'] == 'pending' ? 'warning' :
                                                    ($order['order_status'] == 'processing' ? 'info' :
                                                    ($order['order_status'] == 'shipped' ? 'primary' : 'secondary')))
                                                ?>">
                                                    <?= ucfirst($order['order_status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No recent orders found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-dark">Quick Stats</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Pending Orders:</strong>
                        <span class="badge bg-warning float-end"><?= $stats['pending_orders'] ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Low Stock Products:</strong>
                        <span class="badge bg-danger float-end"><?= $stats['low_stock'] ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Today's Revenue:</strong>
                        <span class="badge bg-success float-end">PKR <?= number_format($stats['today_revenue']) ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>New Customers:</strong>
                        <span class="badge bg-info float-end"><?= $stats['new_customers'] ?></span>
                    </div>
                </div>
            </div>

            <!--<div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="products.php?action=add" class="btn btn-gold">
                            <i class="fas fa-plus me-2"></i>Add New Product
                        </a>
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Manage Orders
                        </a>
                        <a href="settings.php" class="btn btn-outline-secondary">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                    </div>
                </div>
            </div>-->
        </div>
    </div>
</div>

<script>
// Sales Chart Data
const salesData = {
    labels: [<?php
        $labels = [];
        if (!empty($salesData)) {
            foreach ($salesData as $data) {
                $labels[] = "'" . date('M j', strtotime($data['date'])) . "'";
            }
            echo implode(', ', $labels);
        } else {
            // Default labels for empty data
            echo "'No Data'";
        }
    ?>],
    datasets: [{
        label: 'Daily Sales',
        data: [<?php
            $values = [];
            if (!empty($salesData)) {
                foreach ($salesData as $data) {
                    $values[] = $data['daily_sales'] ?: 0;
                }
                echo implode(', ', $values);
            } else {
                echo "0";
            }
        ?>],
        backgroundColor: 'rgba(78, 115, 223, 0.1)',
        borderColor: 'rgba(78, 115, 223, 1)',
        borderWidth: 2,
        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(78, 115, 223, 1)'
    }]
};

// Payment Methods Chart Data
const paymentData = {
    labels: [<?php
        $paymentLabels = [];
        if (!empty($paymentData)) {
            foreach ($paymentData as $data) {
                $paymentLabels[] = "'" . ucwords(str_replace('_', ' ', $data['payment_method'])) . "'";
            }
            echo implode(', ', $paymentLabels);
        } else {
            echo "'No Data'";
        }
    ?>],
    datasets: [{
        data: [<?php
            $paymentValues = [];
            if (!empty($paymentData)) {
                foreach ($paymentData as $data) {
                    $paymentValues[] = $data['count'];
                }
                echo implode(', ', $paymentValues);
            } else {
                echo "0";
            }
        ?>],
        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
        hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a'],
        hoverBorderColor: "rgba(234, 236, 244, 1)",
    }]
};

// Initialize charts when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: salesData,
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'PKR ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'PKR ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Payment Methods Chart
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        new Chart(paymentCtx.getContext('2d'), {
            type: 'doughnut',
            data: paymentData,
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
            }
        });
    }
});
</script>

<?php
require_once 'includes/admin-footer.php';
?>