<?php
require_once __DIR__ . '/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check membership status for 10% discount
$stmt_mem = mysqli_prepare($conn, "SELECT membership FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_mem, "i", $user_id);
mysqli_stmt_execute($stmt_mem);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_mem));
$is_member = (isset($user_data['membership']) && strtolower($user_data['membership']) === 'yes');

$fpCartIn = $_POST['fp_cart'] ?? [];
if (!is_array($fpCartIn)) $fpCartIn = [];

$fpCart = []; // name => qty
foreach ($fpCartIn as $fpName => $fpQtyRaw) {
    $fpQty = (int)$fpQtyRaw;
    $fpName = trim((string)$fpName);
    if ($fpName !== '' && $fpQty > 0) {
        $fpCart[$fpName] = min(5, $fpQty);
    }
}

if (count($fpCart) === 0) {
    header("Location: products.php");
    exit;
}

// Pull latest product info for selected items
$fpNames = array_keys($fpCart);
$fpPlaceholders = implode(',', array_fill(0, count($fpNames), '?'));
$fpTypes = str_repeat('s', count($fpNames));

$fpStmt = mysqli_prepare($conn, "SELECT id, name, price, quantity FROM products WHERE name IN ($fpPlaceholders)");
mysqli_stmt_bind_param($fpStmt, $fpTypes, ...$fpNames);
mysqli_stmt_execute($fpStmt);
$fpRes = mysqli_stmt_get_result($fpStmt);

$fpItems = []; // name => [id, price, quantity]
while ($fpRow = mysqli_fetch_assoc($fpRes)) {
    $fpItems[$fpRow['name']] = [
        'id' => $fpRow['id'],
        'price' => (float)$fpRow['price'],
        'quantity' => (int)$fpRow['quantity'],
    ];
}
mysqli_stmt_close($fpStmt);

// Re-validate quantities against stock and calculate total with membership discount
$fpTotal = 0.0;
$fpFinalCart = []; // name => qty
foreach ($fpCart as $fpName => $fpQty) {
    if (!isset($fpItems[$fpName])) continue;

    $fpAvail = (int)$fpItems[$fpName]['quantity'];
    if ($fpAvail <= 0) continue;

    $fpFinalQty = min($fpQty, $fpAvail, 5);
    if ($fpFinalQty <= 0) continue;

    $fpFinalCart[$fpName] = $fpFinalQty;
    
    $itemPrice = $fpItems[$fpName]['price'];
    if ($is_member) {
        $itemPrice *= 0.9;
    }
    $fpTotal += $itemPrice * $fpFinalQty;
}

if (count($fpFinalCart) === 0) {
    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Order - Review Your Purchase</title>
  <link rel="stylesheet" href="styles-products.css">
</head>
<body>
  <div class="fpshop-order-page">
    <h2 class="fpshop-order-title">Review Your Order</h2>

    <div class="fpshop-order-summary">
      <ul style="list-style: none; padding: 0;">
        <?php foreach ($fpFinalCart as $fpName => $fpQty): ?>
          <li class="fpshop-order-item">
            <span>
                <strong><?php echo htmlspecialchars($fpName); ?></strong> 
                <span style="color: #aaa;">(x<?php echo $fpQty; ?>)</span>
            </span>
            <span>
                <?php if ($is_member): ?>
                    <span style="text-decoration: line-through; font-size: 0.8rem; color: #888;">£<?php echo number_format($fpItems[$fpName]['price'] * $fpQty, 2); ?></span>
                    £<?php echo number_format(($fpItems[$fpName]['price'] * 0.9) * $fpQty, 2); ?>
                <?php else: ?>
                    £<?php echo number_format($fpItems[$fpName]['price'] * $fpQty, 2); ?>
                <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="fpshop-order-total">
        <?php if ($is_member): ?>
            <div class="membership-badge">10% Member Discount Applied</div>
        <?php endif; ?>
        Total: £<?php echo number_format($fpTotal, 2); ?>
      </div>
    </div>

    <form class="fpshop-order-form" method="POST" action="place_order.php">
      <?php foreach ($fpFinalCart as $fpName => $fpQty): ?>
        <input type="hidden" name="fp_cart[<?php echo htmlspecialchars($fpName); ?>]" value="<?php echo (int)$fpQty; ?>">
      <?php endforeach; ?>

      <fieldset class="fpshop-fulfilment">
        <legend style="padding: 0 10px; font-weight: bold;">Fulfilment</legend>

        <label style="display: block; margin: 10px 0;">
          <input type="radio" name="fp_fulfilment" value="pickup" checked> Pickup from Store
        </label>

        <label style="display: block; margin: 10px 0;">
          <input type="radio" name="fp_fulfilment" value="delivery"> Delivery
        </label>

        <div id="fp-delivery-address" style="display:none;">
          <label for="fp_address" style="font-size: 0.9rem; color: #ccc;">Delivery Address:</label><br>
          <textarea class="fpshop-address-input" id="fp_address" name="fp_address" rows="3"></textarea>
        </div>
      </fieldset>

      <button class="fpshop-place-order-btn" type="submit">Complete Order</button>
      <a href="products.php" style="text-align: center; color: #aaa; text-decoration: none; font-size: 0.9rem;">Back to Store</a>
    </form>
  </div>

  <script>
    (function () {
      const radios = document.querySelectorAll('input[name="fp_fulfilment"]');
      const box = document.getElementById('fp-delivery-address');
      const addr = document.getElementById('fp_address');

      function sync() {
        const val = document.querySelector('input[name="fp_fulfilment"]:checked')?.value;
        const isDelivery = (val === 'delivery');
        box.style.display = isDelivery ? 'block' : 'none';
        if (addr) addr.required = isDelivery;
      }
      radios.forEach(r => r.addEventListener('change', sync));
      sync();
    })();
  </script>
</body>
</html>