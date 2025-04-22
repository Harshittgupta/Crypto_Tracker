<?php
// Save this as raw_portfolio.php
// This bypasses all PHP logic and shows raw database data

// Basic session and database connection
session_start();
// Manual database connection to avoid any include issues
$db_host = "localhost";
$db_user = "root"; // Change if needed
$db_pass = "";     // Change if needed
$db_name = "crypto_tracker";

// Create a new connection
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Direct raw query with no external function dependencies
$query = "SELECT id, coin_name, symbol, quantity, buy_price FROM portfolio WHERE user_id = $user_id";
$result = $db->query($query);

$entries = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
}

// Get current cryptocurrency prices directly
$coin_ids = [];
foreach ($entries as $entry) {
    $symbol = strtolower($entry['symbol']);
    // Manually convert symbols to CoinGecko IDs
    if ($symbol == 'btc') {
        $coin_ids[] = 'bitcoin';
    } else if ($symbol == 'eth') {
        $coin_ids[] = 'ethereum';
    } else {
        // For other coins, use the symbol as ID
        $coin_ids[] = $symbol;
    }
}

// Fetch prices from API if needed
$prices = [];
if (!empty($coin_ids)) {
    $coin_list = implode(",", array_unique($coin_ids));
    $api_url = "https://api.coingecko.com/api/v3/simple/price?ids=$coin_list&vs_currencies=usd&include_24h_change=true";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $prices = json_decode($response, true);
    }
}

// Calculate totals
$total_investment = 0;
$total_current_value = 0;

foreach ($entries as &$entry) {
    $investment = $entry['quantity'] * $entry['buy_price'];
    $total_investment += $investment;
    
    // Add investment to entry for display
    $entry['investment'] = $investment;
    
    // Get current price and calculate value
    $symbol = strtolower($entry['symbol']);
    $coin_id = ($symbol == 'btc') ? 'bitcoin' : (($symbol == 'eth') ? 'ethereum' : $symbol);
    
    if (isset($prices[$coin_id]) && isset($prices[$coin_id]['usd'])) {
        $current_price = $prices[$coin_id]['usd'];
        $current_value = $entry['quantity'] * $current_price;
        $profit_loss = $current_value - $investment;
        
        $entry['current_price'] = $current_price;
        $entry['current_value'] = $current_value;
        $entry['profit_loss'] = $profit_loss;
        $entry['price_change'] = $prices[$coin_id]['usd_24h_change'] ?? null;
        
        $total_current_value += $current_value;
    } else {
        $entry['current_price'] = null;
        $entry['current_value'] = null;
        $entry['profit_loss'] = null;
        $entry['price_change'] = null;
    }
}

$total_profit_loss = $total_current_value - $total_investment;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Portfolio View</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .debug-info {
            background: #333;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .debug-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Raw Portfolio View</h1>
            <div class="actions">
                <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Regular Dashboard</a>
                <a href="portfolio.php" class="btn"><i class="fas fa-plus-circle"></i> Add to Portfolio</a>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>
        
        <div class="debug-info">
            <p><strong>Debug Info:</strong> This page uses direct database queries and manual API calls to avoid any potential issues.</p>
            <p><strong>Found:</strong> <?php echo count($entries); ?> portfolio entries</p>
            <p><strong>Total Investment:</strong> $<?php echo number_format($total_investment, 2); ?></p>
            <p><strong>Total Current Value:</strong> $<?php echo number_format($total_current_value, 2); ?></p>
            <p><strong>Total Profit/Loss:</strong> $<?php echo number_format($total_profit_loss, 2); ?> (<?php echo number_format(($total_investment > 0 ? ($total_profit_loss / $total_investment) * 100 : 0), 2); ?>%)</p>
        </div>

        <div class="overview-section">
            <div class="overview-card">
                <i class="fas fa-money-bill-wave card-icon"></i>
                <div>
                    <h3>Total Investment</h3>
                    <p class="value">$<?php echo number_format($total_investment, 2); ?></p>
                </div>
            </div>
            <div class="overview-card">
                <i class="fas fa-wallet card-icon"></i>
                <div>
                    <h3>Current Value</h3>
                    <p class="value">$<?php echo number_format($total_current_value, 2); ?></p>
                </div>
            </div>
            <div class="overview-card <?php echo $total_profit_loss >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-chart-line card-icon"></i>
                <div>
                    <h3>Total Profit/Loss</h3>
                    <p class="value">$<?php echo number_format($total_profit_loss, 2); ?> 
                        (<?php echo number_format(($total_investment > 0 ? ($total_profit_loss / $total_investment) * 100 : 0), 2); ?>%)
                    </p>
                </div>
            </div>
        </div>

        <div class="portfolio-section">
            <h2>Raw Portfolio Entries (<?php echo count($entries); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Coin</th>
                        <th>Quantity</th>
                        <th>Buy Price</th>
                        <th>Investment</th>
                        <th>Current Price</th>
                        <th>24h Change</th>
                        <th>Current Value</th>
                        <th>Profit/Loss</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="10">No entries found in your portfolio.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): 
                            $profitClass = isset($entry['profit_loss']) && $entry['profit_loss'] >= 0 ? 'positive' : 'negative';
                            $changeClass = isset($entry['price_change']) && $entry['price_change'] >= 0 ? 'positive' : 'negative';
                        ?>
                            <tr>
                                <td><?php echo $entry['id']; ?></td>
                                <td><?php echo htmlspecialchars($entry['coin_name']); ?> (<?php echo htmlspecialchars($entry['symbol']); ?>)</td>
                                <td><?php echo htmlspecialchars($entry['quantity']); ?></td>
                                <td>$<?php echo number_format($entry['buy_price'], 2); ?></td>
                                <td>$<?php echo number_format($entry['investment'], 2); ?></td>
                                <td>$<?php echo isset($entry['current_price']) ? number_format($entry['current_price'], 2) : 'N/A'; ?></td>
                                <td class="<?php echo $changeClass; ?>">
                                    <?php if (isset($entry['price_change'])): ?>
                                        <?php echo $entry['price_change'] >= 0 ? '+' : ''; ?><?php echo number_format($entry['price_change'], 2); ?>%
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>$<?php echo isset($entry['current_value']) ? number_format($entry['current_value'], 2) : 'N/A'; ?></td>
                                <td class="<?php echo $profitClass; ?>">
                                    <?php if (isset($entry['profit_loss'])): ?>
                                        $<?php echo number_format($entry['profit_loss'], 2); ?>
                                        (<?php echo $entry['profit_loss'] >= 0 ? '+' : ''; ?><?php echo number_format(($entry['profit_loss'] / $entry['investment']) * 100, 2); ?>%)
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_coin.php?id=<?php echo $entry['id']; ?>" class="action-link" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="delete_coin.php?id=<?php echo $entry['id']; ?>" class="action-link delete" title="Delete" onclick="return confirm('Are you sure you want to delete this coin?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
