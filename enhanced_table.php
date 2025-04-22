<?php
// Save as enhanced_table.php
session_start();
$user_id = $_SESSION['user_id'];

// Direct connection
$conn = new mysqli("localhost", "root", "", "crypto_tracker");

// Raw query 
$sql = "SELECT * FROM portfolio WHERE user_id = $user_id";
$result = $conn->query($sql);

// Prepare data
$portfolio = [];
while ($row = $result->fetch_assoc()) {
    $portfolio[] = $row;
}

// Get current prices
$coin_ids = [];
foreach ($portfolio as $entry) {
    $symbol = strtolower($entry['symbol']);
    if ($symbol == 'btc') $coin_ids[] = 'bitcoin';
    if ($symbol == 'eth') $coin_ids[] = 'ethereum';
}

// We'll skip actual API calls for this test
?>
<!DOCTYPE html>
<html>
<head>
    <title>Enhanced Table</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .debug { margin: 20px 0; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Enhanced Table View</h1>
            <div class="actions">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
            </div>
        </header>
        
        <div class="debug">
            <p>Found <?php echo count($portfolio); ?> entries</p>
            <p>Symbols: <?php echo implode(", ", array_column($portfolio, 'symbol')); ?></p>
        </div>

        <div class="portfolio-section">
            <h2>Your Portfolio</h2>
            <table>
                <thead>
                    <tr>
                        <th>Coin</th>
                        <th>Symbol</th>
                        <th>Quantity</th>
                        <th>Buy Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($portfolio as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['coin_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['symbol']); ?></td>
                        <td><?php echo htmlspecialchars($entry['quantity']); ?></td>
                        <td>$<?php echo number_format($entry['buy_price'], 2); ?></td>
                        <td>
                            <a href="#" class="action-link"><i class="fas fa-eye"></i></a>
                            <a href="#" class="action-link"><i class="fas fa-edit"></i></a>
                            <a href="#" class="action-link delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>