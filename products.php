<?php
require_once __DIR__ . '/db_conn.php';

$fpProducts = [];
$fpRes = mysqli_query($conn, "SELECT name, price, quantity FROM products ORDER BY name ASC");
if ($fpRes) {
    while ($fpRow = mysqli_fetch_assoc($fpRes)) {
        $fpProducts[] = $fpRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Products</title>
  <link rel="stylesheet" href="styles-products.css">
</head>
<body>
  <div class="fpshop-products-page">
    <h2 class="fpshop-products-title">Products</h2>

    <form action="order.php" method="POST" class="fpshop-products-container">
      <div class="fpshop-products-grid">
        <?php foreach (array_slice($fpProducts, 0, 24) as $fpP): ?>
          <?php
            $fpName = (string)$fpP['name'];
            $fpPrice = (string)$fpP['price'];
            $fpAvail = (int)$fpP['quantity'];
            $fpMaxSelect = min(5, max(0, $fpAvail));
            $fpSafeId = 'qty_' . sha1($fpName); // Unique ID for label
          ?>
          <div class="fpshop-product-card">
            <div class="fpshop-product-card__header">
              <div class="fpshop-product-name"><?php echo htmlspecialchars($fpName); ?></div>
            </div>

            <div class="fpshop-product-card__body">
              <div class="fpshop-product-price">£<?php echo htmlspecialchars($fpPrice); ?></div>
              <div class="fpshop-product-qty-available">In stock: <?php echo $fpAvail; ?></div>

              <label class="fpshop-product-qty-label" for="<?php echo htmlspecialchars($fpSafeId); ?>">Quantity</label>
              <select
                class="fpshop-product-qty-select"
                id="<?php echo htmlspecialchars($fpSafeId); ?>"
                name="fp_cart[<?php echo htmlspecialchars($fpName); ?>]"
                <?php echo ($fpMaxSelect === 0) ? 'disabled' : ''; ?>
              >
                <option value="0">0</option>
                <?php for ($i = 1; $i <= $fpMaxSelect; $i++): ?>
                  <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="fpshop-products-footer">
        <button class="fpshop-products-purchase-btn" type="submit">Proceed to Checkout</button>
      </div>
    </form>
  </div>
</body>
</html>
