<?php
require_once __DIR__ . '/db_conn.php';

// Check if user is logged in (using Oblong Login System session)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle membership enrollment directly on this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_membership') {
    $stmt_upd = mysqli_prepare($conn, "UPDATE users SET membership = 'yes' WHERE id = ?");
    mysqli_stmt_bind_param($stmt_upd, "i", $user_id);
    if (mysqli_stmt_execute($stmt_upd)) {
        header("Location: profile.php?success=membership_joined");
        exit;
    }
}

// Get user info and purchase history
// Assuming the 'users' table has 'username', 'role', 'membership'
$stmt_user = mysqli_prepare($conn, "SELECT username, role, membership FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));

// Default values
$membership = $user_info['membership'] ?? 'no';

$stmt_purchases = mysqli_prepare($conn, "SELECT product_name, quantity_purchased, total_price, purchase_date FROM purchases WHERE username = ? ORDER BY purchase_date DESC");
mysqli_stmt_bind_param($stmt_purchases, "s", $user_info['username']);
mysqli_stmt_execute($stmt_purchases);
$purchases_res = mysqli_stmt_get_result($stmt_purchases);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Profile</title>
    <!-- Use Oblong's main styles and our product styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-products.css">
</head>
<body>
    <div class="container profile-container">
        <h2>User Profile</h2>
        <div style="margin-bottom: 2rem;">
            <p>Welcome back, <strong><?php echo htmlspecialchars($user_info['username']); ?></strong>!</p>
            <p>Your Role: <strong><?php echo htmlspecialchars($user_info['role']); ?></strong></p>
            <p>Membership: 
                <?php if (strtolower($membership) == 'yes'): ?>
                    <span style="color: #28a745; font-weight: bold;">Member (10% Discount Active)</span>
                <?php else: ?>
                    <span style="color: #666;">Standard User</span>
                    <form action="profile.php" method="POST" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="action" value="join_membership">
                        <button type="submit" class="role-pill" style="background: #28a745; border: none; cursor: pointer; color: white; padding: 4px 10px; border-radius: 4px;">Join Membership</button>
                    </form>
                <?php endif; ?>
            </p>
        </div>

        <div class="purchase-history">
            <h3>Your Order History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($purchases_res) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($purchases_res)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo $row['quantity_purchased']; ?></td>
                                <td>£<?php echo number_format($row['total_price'], 2); ?></td>
                                <td><?php echo date('d M Y, H:i', strtotime($row['purchase_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; border-bottom: none;">No purchases found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem; border-top: 1px solid #eee; padding-top: 1.5rem; display: flex; justify-content: space-between;">
            <a href="products.php" class="back-link">Return to Shop</a>
            <a href="logout.php" style="color: #cc0000; text-decoration: none;">Logout</a>
        </div>
    </div>
</body>
</html>