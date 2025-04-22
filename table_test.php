<?php
// Save this as table_test.php
session_start();
$user_id = $_SESSION['user_id'];

// Direct connection
$conn = new mysqli("localhost", "root", "", "crypto_tracker");

// Raw query 
$sql = "SELECT * FROM portfolio WHERE user_id = $user_id";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Table Test</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Basic Table Test</h1>
    <p>Rows found: <?php echo $result->num_rows; ?></p>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Symbol</th>
            <th>Quantity</th>
            <th>Buy Price</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['coin_name']; ?></td>
            <td><?php echo $row['symbol']; ?></td>
            <td><?php echo $row['quantity']; ?></td>
            <td><?php echo $row['buy_price']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>