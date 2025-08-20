<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Authentication check (simple version - you might want to enhance this)
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || 
    $_SERVER['PHP_AUTH_USER'] != 'admin' || $_SERVER['PHP_AUTH_PW'] != 'password-here') {
    header('WWW-Authenticate: Basic realm="Network Tools Dashboard"');
    header('HTTP/1.0 401 Unauthorized');
    die('Access Denied');
}

// Database connection
$dbHost = 'localhost';
$dbName = 'blahblah_netools_db';
$dbUser = 'blahblah_netools_dbuser';
$dbPass = 'passwordhere';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get summary statistics
function getSummaryStats($db) {
    $stats = [];
    
    // Total tests
    $stmt = $db->query("SELECT COUNT(*) as total FROM logs");
    $stats['total_tests'] = $stmt->fetchColumn();
    
    // Tests by type
    $stmt = $db->query("SELECT test_type, COUNT(*) as count FROM logs GROUP BY test_type");
    $stats['tests_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity (last 7 days)
    $stmt = $db->query("SELECT DATE(timestamp) as day, COUNT(*) as count 
                        FROM logs 
                        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        GROUP BY day 
                        ORDER BY day DESC");
    $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top targets
    $stmt = $db->query("SELECT target, COUNT(*) as count 
                        FROM logs 
                        GROUP BY target 
                        ORDER BY count DESC 
                        LIMIT 10");
    $stats['top_targets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Countries
    $stmt = $db->query("SELECT country, COUNT(*) as count 
                        FROM logs 
                        WHERE country IS NOT NULL AND country != '' 
                        GROUP BY country 
                        ORDER BY count DESC 
                        LIMIT 10");
    $stats['countries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Get recent logs with pagination
function getRecentLogs($db, $page = 1, $perPage = 20, $filters = []) {
    $offset = ($page - 1) * $perPage;
    
    $where = [];
    $params = [];
    
    if (!empty($filters['test_type'])) {
        $where[] = "test_type = :test_type";
        $params[':test_type'] = $filters['test_type'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "timestamp >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "timestamp <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get logs
    $stmt = $db->prepare("SELECT * FROM logs $whereClause ORDER BY timestamp DESC LIMIT :offset, :perPage");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $stmt = $db->prepare("SELECT COUNT(*) FROM logs $whereClause");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    return [
        'logs' => $logs,
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'current_page' => $page
    ];
}

// Get filters from request
$filters = [
    'test_type' => $_GET['test_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$stats = getSummaryStats($db);
$logsData = getRecentLogs($db, $currentPage, 20, $filters);

// Helper function to format test type
function formatTestType($type) {
    $types = [
        'ping' => 'Ping',
        'whois' => 'WHOIS',
        'dns' => 'DNS Lookup',
        'httpheader' => 'HTTP Header',
        'tcpport' => 'TCP Port 80',
        'tracert' => 'Traceroute'
    ];
    return $types[$type] ?? ucfirst($type);
}

// Helper function to get country name
function getCountryName($code) {
    $countries = [
        'US' => 'United States',
        'IN' => 'India',
        'GB' => 'United Kingdom',
        // Add more as needed
    ];
    return $countries[strtoupper($code)] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Tools Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .dashboard-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-card .label {
            color: #6c757d;
            font-size: 1rem;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background-color: var(--primary-color);
            color: white;
        }
        .badge-type {
            background-color: var(--secondary-color);
            color: white;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .pagination .page-link {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="bi bi-speedometer2"></i> Network Tools Dashboard</h1>
                <small>Last updated: <?= date('Y-m-d H:i:s') ?></small>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="number"><?= number_format($stats['total_tests']) ?></div>
                    <div class="label">Total Tests</div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">Tests by Type</div>
                    <div class="card-body">
                        <canvas id="testsChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Top Targets</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($stats['top_targets'] as $target): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($target['target']) ?>
                                    <span class="badge bg-primary rounded-pill"><?= $target['count'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Countries</div>
                    <div class="card-body">
                        <canvas id="countriesChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Activity</span>
                        <form method="get" class="row g-2">
                            <div class="col-auto">
                                <select name="test_type" class="form-select form-select-sm">
                                    <option value="">All Test Types</option>
                                    <?php foreach ($stats['tests_by_type'] as $type): ?>
                                        <option value="<?= $type['test_type'] ?>" <?= $filters['test_type'] == $type['test_type'] ? 'selected' : '' ?>>
                                            <?= formatTestType($type['test_type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <input type="date" name="date_from" class="form-control form-control-sm" 
                                       value="<?= $filters['date_from'] ?>" placeholder="From">
                            </div>
                            <div class="col-auto">
                                <input type="date" name="date_to" class="form-control form-control-sm" 
                                       value="<?= $filters['date_to'] ?>" placeholder="To">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Test Type</th>
                                        <th>Target</th>
                                        <th>IP Address</th>
                                        <th>Country</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logsData['logs'] as $log): ?>
                                        <tr>
                                            <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                                            <td><span class="badge badge-type"><?= formatTestType($log['test_type']) ?></span></td>
                                            <td><?= htmlspecialchars($log['target']) ?></td>
                                            <td><?= htmlspecialchars($log['ip']) ?></td>
                                            <td><?= getCountryName($log['country']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-result" 
                                                        data-result="<?= htmlspecialchars($log['result']) ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($logsData['pages'] > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($currentPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $currentPage-1 ?>&<?= http_build_query($filters) ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $logsData['pages']; $i++): ?>
                                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($currentPage < $logsData['pages']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $currentPage+1 ?>&<?= http_build_query($filters) ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="modalResultContent" style="white-space: pre-wrap; word-wrap: break-word;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Tests by type chart
            const testsCtx = document.getElementById('testsChart').getContext('2d');
            const testsChart = new Chart(testsCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php foreach ($stats['tests_by_type'] as $type): ?>
                            '<?= formatTestType($type['test_type']) ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($stats['tests_by_type'] as $type): ?>
                                <?= $type['count'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#4361ee', '#3f37c9', '#4cc9f0', '#4895ef', '#560bad', '#7209b7'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });

            // Countries chart
            const countriesCtx = document.getElementById('countriesChart').getContext('2d');
            const countriesChart = new Chart(countriesCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach ($stats['countries'] as $country): ?>
                            '<?= getCountryName($country['country']) ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Tests by Country',
                        data: [
                            <?php foreach ($stats['countries'] as $country): ?>
                                <?= $country['count'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: '#4361ee',
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // View result modal
            $('.view-result').click(function() {
                const result = $(this).data('result');
                $('#modalResultContent').text(result);
                $('#resultModal').modal('show');
            });
        });
    </script>
</body>
</html>