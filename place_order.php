<?php
require_once __DIR__ . '/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle order submission via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fp_cart'])) {
    header("Location: products.php");
    exit;
}

$fpCartIn = $_POST['fp_cart']; // [name => quantity]
$fpFulfilment = $_POST['fp_fulfilment'] ?? 'pickup';
$fpAddress = $_POST['fp_address'] ?? '';

// Check membership status for 10% discount
$stmt_mem = mysqli_prepare($conn, "SELECT membership FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_mem, "i", $user_id);
mysqli_stmt_execute($stmt_mem);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_mem));
$is_member = (isset($user_data['membership']) && strtolower($user_data['membership']) === 'yes');

// Fetch product details for validation
$fpNames = array_keys($fpCartIn);
$fpPlaceholders = implode(',', array_fill(0, count($fpNames), '?'));
$fpTypes = str_repeat('s', count($fpNames));

$fpStmt = mysqli_prepare($conn, "SELECT id, name, price, quantity FROM products WHERE name IN ($fpPlaceholders)");
mysqli_stmt_bind_param($fpStmt, $fpTypes, ...$fpNames);
mysqli_stmt_execute($fpStmt);
$fpRes = mysqli_stmt_get_result($fpStmt);

$fpItems = [];
while ($fpRow = mysqli_fetch_assoc($fpRes)) {
    $fpItems[$fpRow['name']] = $fpRow;
}
mysqli_stmt_close($fpStmt);

// Begin processing the purchase
mysqli_begin_transaction($conn);
$order_success = true;
$processed_items = [];
$total_final_bill = 0;

try {
    foreach ($fpCartIn as $fpName => $fpQty) {
        $fpQty = (int)$fpQty;
        if (!isset($fpItems[$fpName]) || $fpQty <= 0) continue;

        $targetProduct = $fpItems[$fpName];
        $productId = $targetProduct['id'];
        $unitPrice = (float)$targetProduct['price'];
        
        // Check stock
        if ($targetProduct['quantity'] < $fpQty) {
            throw new Exception("Not enough stock for $fpName.");
        }

        // Apply membership discount
        if ($is_member) {
            $unitPrice *= 0.9;
        }
        $lineTotal = $unitPrice * $fpQty;
        $total_final_bill += $lineTotal;

        // Update Stock
        $upd_stmt = mysqli_prepare($conn, "UPDATE products SET quantity = quantity - ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "ii", $fpQty, $productId);
        mysqli_stmt_execute($upd_stmt);

        // Record Purchase in the database (one record per product in the order)
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO purchases (username, product_id, product_name, quantity_purchased, total_price) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "sisid", $username, $productId, $fpName, $fpQty, $lineTotal);
        mysqli_stmt_execute($ins_stmt);

        $processed_items[] = "$fpQty x $fpName";
    }
    
    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    $order_success = false;
    $error_msg = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status</title>
    <link rel="stylesheet" href="styles-products.css">
</head>
<body>
    <div class="result-container">
        <?php if ($order_success): ?>
            <h2 class="success-hdr">Order Confirmed!</h2>
            <p>Thank you for your purchase, <strong><?php echo htmlspecialchars($username); ?></strong>.</p>
            
            <div class="order-summary">
                <strong>Items Processed:</strong>
                <ul>
                    <?php foreach ($processed_items as $item): ?>
                        <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Total Paid: £<?php echo number_format($total_final_bill, 2); ?></strong></p>
                <p><strong>Fulfilment:</strong> <?php echo ucfirst(htmlspecialchars($fpFulfilment)); ?></p>
                <?php if ($fpFulfilment === 'delivery'): ?>
                    <p style="font-size: 0.9rem; color: #aaa;">Delivering to: <?php echo nl2br(htmlspecialchars($fpAddress)); ?></p>
                <?php endif; ?>
            </div>
            
            <p>Your items will be ready soon. You can view your history in your profile.</p>
        <?php else: ?>
            <h2 class="error-hdr">Ordering Failed</h2>
            <p>We encountered an error while processing your request:</p>
            <div style="background: rgba(204,0,0,0.1); padding: 15px; border-radius: 6px; margin: 20px 0; color: #ff6666;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <a href="products.php" class="btn-link">Return to Shop</a>
        <a href="profile-standalone.php" class="btn-link" style="background: transparent; color: #ffff99; border: 1px solid #ffff99; margin-left: 10px;">View My Profile</a>
    </div>
</body>
</html>