<?php
$page_title = 'Stock & Costing Management';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$active_tab = $_GET['tab'] ?? 'balances';
$canAdjustInventory = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['adjust']);
if ($active_tab === 'adjust' && !$canAdjustInventory) {
    requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['adjust']);
}

// Pagination helper
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch active products for dropdowns
$prodQ = "SELECT id, name, sku, cost_price, uom FROM products WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$pStmt = mysqli_prepare($conn, $prodQ);
mysqli_stmt_bind_param($pStmt, 'i', $businessId);
mysqli_stmt_execute($pStmt);
$prodResult = mysqli_stmt_get_result($pStmt);
$products_list = [];
while ($pRow = mysqli_fetch_assoc($prodResult)) {
    $products_list[] = $pRow;
}

// Fetch active locations for dropdowns
$locQ = "SELECT id, name, code FROM business_locations WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$lStmt = mysqli_prepare($conn, $locQ);
mysqli_stmt_bind_param($lStmt, 'i', $businessId);
mysqli_stmt_execute($lStmt);
$locResult = mysqli_stmt_get_result($lStmt);
$locations_list = [];
while ($lRow = mysqli_fetch_assoc($locResult)) {
    $locations_list[] = $lRow;
}

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
?>

<!-- Tab Selector -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
  <a href="index.php?tab=balances<?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>" class="btn-sm <?php echo ($active_tab === 'balances') ? 'active' : ''; ?>" style="text-decoration:none;">Stock Balances</a>
  <a href="index.php?tab=movements<?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>" class="btn-sm <?php echo ($active_tab === 'movements') ? 'active' : ''; ?>" style="text-decoration:none;">Stock Movements History</a>
  <?php if ($canAdjustInventory): ?>
    <a href="index.php?tab=adjust<?php echo getPreviewRole() !== null ? '&role='.e(getPreviewRole()) : ''; ?>" class="btn-sm <?php echo ($active_tab === 'adjust') ? 'active' : ''; ?>" style="text-decoration:none;">Stock Adjustment</a>
  <?php endif; ?>
</div>

<!-- ==========================================
     TAB: STOCK BALANCES
     ========================================== -->
<?php if ($active_tab === 'balances'): ?>
  <?php
  // Fetch stock balances
  $balQuery = "
      SELECT b.*, p.name as product_name, p.sku, p.uom, l.name as location_name, l.code as location_code
      FROM inventory_balances b
      JOIN products p ON b.product_id = p.id
      JOIN business_locations l ON b.location_id = l.id
      WHERE b.business_id = ?
      ORDER BY p.name ASC, l.name ASC
  ";
  $stmt = mysqli_prepare($conn, $balQuery);
  mysqli_stmt_bind_param($stmt, 'i', $businessId);
  mysqli_stmt_execute($stmt);
  $balResult = mysqli_stmt_get_result($stmt);
  ?>
  <div class="card">
    <div class="card-header"><div class="card-title">Real-Time Inventory Levels &amp; Cost Valuation</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>SKU Code</th>
          <th>Product Name</th>
          <th>Location</th>
          <th>Qty On Hand</th>
          <th>Avg Unit Cost (WAVG)</th>
          <th>Cost Valuation</th>
          <th>Last Calculated</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($balResult) === 0): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text3); padding: 30px;">No inventory balances found. Perform purchase receipts or stock entries to increase stock.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($balResult)): 
              $val = $row['quantity_on_hand'] * $row['average_unit_cost'];
          ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['sku']); ?></span></td>
              <td class="td-name"><?php echo e($row['product_name']); ?></td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['location_code']); ?></span> <?php echo e($row['location_name']); ?></td>
              <td class="td-bold"><?php echo (float)$row['quantity_on_hand']; ?> <span style="font-size:10px; font-weight:400; color:var(--text3);"><?php echo e($row['uom']); ?></span></td>
              <td><?php echo formatCurrency($row['average_unit_cost'], $bizCur); ?></td>
              <td class="td-bold" style="color:var(--blue);"><?php echo formatCurrency($val, $bizCur); ?></td>
              <td style="font-size:11.5px; color:var(--text3);"><?php echo formatDate($row['last_calculated_at']); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<!-- ==========================================
     TAB: STOCK MOVEMENTS LOGS
     ========================================== -->
<?php elseif ($active_tab === 'movements'): ?>
  <?php
  // Fetch movements count
  $cntMQuery = "SELECT COUNT(*) as total FROM inventory_movements WHERE business_id = ?";
  $cmStmt = mysqli_prepare($conn, $cntMQuery);
  mysqli_stmt_bind_param($cmStmt, 'i', $businessId);
  mysqli_stmt_execute($cmStmt);
  $total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cmStmt))['total'] ?? 0;
  $total_pages = ceil($total_rows / $limit);

  // Fetch movements
  $movQuery = "
      SELECT m.*, p.name as product_name, p.sku, l.name as location_name, l.code as location_code, 
             u.first_name, u.last_name
      FROM inventory_movements m
      JOIN products p ON m.product_id = p.id
      JOIN business_locations l ON m.location_id = l.id
      LEFT JOIN business_memberships bm ON m.created_by_membership_id = bm.id
      LEFT JOIN users u ON bm.user_id = u.id
      WHERE m.business_id = ?
      ORDER BY m.occurred_at DESC
      LIMIT ? OFFSET ?
  ";
  $stmt = mysqli_prepare($conn, $movQuery);
  mysqli_stmt_bind_param($stmt, 'iii', $businessId, $limit, $offset);
  mysqli_stmt_execute($stmt);
  $movResult = mysqli_stmt_get_result($stmt);
  ?>
  <div class="card">
    <div class="card-header"><div class="card-title">Inventory Movements Log Ledger</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Occurred Date</th>
          <th>SKU / Product</th>
          <th>Location</th>
          <th>Movement Type</th>
          <th>Quantity Delta</th>
          <th>Unit Cost</th>
          <th>Recorded By</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($movResult) === 0): ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--text3); padding: 30px;">No inventory movements recorded yet.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($movResult)): 
              $is_in = ($row['quantity_delta'] > 0);
          ?>
            <tr>
              <td><?php echo formatDate($row['occurred_at']); ?></td>
              <td class="td-name">
                <div><?php echo e($row['product_name']); ?></div>
                <span class="code-badge" style="font-size:10px;"><?php echo e($row['sku']); ?></span>
              </td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['location_code']); ?></span></td>
              <td><span class="code-badge" style="font-size:10px;"><?php echo e($row['movement_type']); ?></span></td>
              <td class="td-bold" style="color: <?php echo $is_in ? 'var(--green)' : 'var(--red)'; ?>;">
                <?php echo ($is_in ? '+' : '') . (float)$row['quantity_delta']; ?>
              </td>
              <td><?php echo formatCurrency($row['unit_cost'], $bizCur); ?></td>
              <td><?php echo e($row['first_name'] ? ($row['first_name'] . ' ' . $row['last_name']) : 'System/Import'); ?></td>
              <td style="font-size: 11.5px; color: var(--text3);"><?php echo e($row['notes'] ?? 'N/A'); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Pagination links -->
    <?php if ($total_pages > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px; border-top: 1px solid var(--table-border);">
        <span style="font-size:12px; color: var(--text3);">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> entries)</span>
        <div style="display: flex; gap: 4px;">
          <?php if ($page > 1): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?tab=movements&page=<?php echo ($page - 1); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?tab=movements&page=<?php echo $i; ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?tab=movements&page=<?php echo ($page + 1); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

<!-- ==========================================
     TAB: STOCK ADJUSTMENT FORM
     ========================================== -->
<?php elseif ($active_tab === 'adjust'): ?>
  <div class="card" style="max-width: 500px; margin: 0 auto;">
    <div class="card-header"><div class="card-title">Record Manual Inventory Adjustment</div></div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="padding: 20px;" id="adjustForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
      <input type="hidden" name="action" value="adjust">

      <div class="field" style="margin-bottom: 12px;">
        <label for="p_id">Select Product <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <select name="product_id" id="p_id" required onchange="updateDefaultCost()">
            <option value="">-- Choose Product --</option>
            <?php foreach ($products_list as $p): ?>
              <option value="<?php echo (int)$p['id']; ?>" data-cost="<?php echo (float)$p['cost_price']; ?>"><?php echo e($p['name']); ?> (<?php echo e($p['sku']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="l_id">Target Location <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <select name="location_id" id="l_id" required>
            <option value="">-- Choose Location --</option>
            <?php foreach ($locations_list as $l): ?>
              <option value="<?php echo (int)$l['id']; ?>"><?php echo e($l['name']); ?> [<?php echo e($l['code']); ?>]</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="adj_type">Adjustment Type <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <select name="movement_type" id="adj_type" required>
            <option value="MANUAL_IN">MANUAL IN (Increase Stock)</option>
            <option value="MANUAL_OUT">MANUAL OUT (Decrease Stock)</option>
            <option value="STOCKTAKE_GAIN">STOCKTAKE GAIN (Increase)</option>
            <option value="STOCKTAKE_LOSS">STOCKTAKE LOSS (Decrease)</option>
            <option value="DAMAGE">DAMAGE (Decrease)</option>
            <option value="EXPIRY">EXPIRY (Decrease)</option>
            <option value="CORRECTION_IN">CORRECTION IN (Increase)</option>
            <option value="CORRECTION_OUT">CORRECTION OUT (Decrease)</option>
          </select>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="qty">Quantity <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="number" name="quantity" id="qty" step="0.0001" min="0.0001" placeholder="0.0000" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="u_cost">Unit Cost / Price Value (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="number" name="unit_cost" id="u_cost" step="0.0001" min="0" placeholder="0.0000" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label for="notes">Adjustment Reason Notes <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <textarea name="notes" id="notes" required placeholder="Describe why this manual adjustment is being recorded (e.g. broken seal, monthly audit stocktake count discrepancy)..." style="width:100%; border:1px solid var(--border); border-radius: var(--radius); padding: 8px; background:var(--card); color:var(--text); resize:vertical; min-height:60px;"></textarea>
        </div>
      </div>

      <button type="submit" class="btn-primary" style="width: 100%; border:none; padding:10px; cursor:pointer;" id="submitBtn">
        Record Stock Adjustment
      </button>
    </form>
  </div>
  
  <script>
  function updateDefaultCost() {
    const sel = document.getElementById('p_id');
    const cost = sel.options[sel.selectedIndex].getAttribute('data-cost');
    if (cost) {
      document.getElementById('u_cost').value = parseFloat(cost).toFixed(4);
    }
  }
  
  document.getElementById('adjustForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.textContent = 'Posting stock adjustment entry...';
  });
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
