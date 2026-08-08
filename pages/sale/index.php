<?php
$page_title = 'Sales Orders';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE s.business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (s.sale_number LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $where_clause .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Count total
$count_query = "
    SELECT COUNT(*) as total 
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    $where_clause
";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT s.*, c.name as customer_name, l.name as location_name, l.code as location_code,
           u.first_name, u.last_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN business_locations l ON s.location_id = l.id
    LEFT JOIN business_memberships bm ON s.cashier_membership_id = bm.id
    LEFT JOIN users u ON bm.user_id = u.id
    $where_clause
    ORDER BY s.sold_at DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch active customers
$custQuery = "SELECT id, name FROM customers WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$custStmt = mysqli_prepare($conn, $custQuery);
mysqli_stmt_bind_param($custStmt, 'i', $businessId);
mysqli_stmt_execute($custStmt);
$customers_list = [];
$cResult = mysqli_stmt_get_result($custStmt);
while ($cRow = mysqli_fetch_assoc($cResult)) {
    $customers_list[] = $cRow;
}

// Fetch active locations
$locQuery = "SELECT id, name, code FROM business_locations WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$lStmt = mysqli_prepare($conn, $locQuery);
mysqli_stmt_bind_param($lStmt, 'i', $businessId);
mysqli_stmt_execute($lStmt);
$locations_list = [];
$lResult = mysqli_stmt_get_result($lStmt);
while ($lRow = mysqli_fetch_assoc($lResult)) {
    $locations_list[] = $lRow;
}

// Fetch active products
$prodQuery = "SELECT id, name, sku, sale_price FROM products WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$pStmt = mysqli_prepare($conn, $prodQuery);
mysqli_stmt_bind_param($pStmt, 'i', $businessId);
mysqli_stmt_execute($pStmt);
$products_list = [];
$pResult = mysqli_stmt_get_result($pStmt);
while ($pRow = mysqli_fetch_assoc($pResult)) {
    $products_list[] = $pRow;
}

// Fetch business tax settings
$acctQ = "SELECT default_tax_rate FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
$aStmt = mysqli_prepare($conn, $acctQ);
mysqli_stmt_bind_param($aStmt, 'i', $businessId);
mysqli_stmt_execute($aStmt);
$default_tax_rate = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt))['default_tax_rate'] ?? 0.0;

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

$csrfToken = generateCsrfToken();
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>
<div>
  <!-- Left Column: Table List -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div class="card-title">Customer Sales Orders History</div>
        <button class="btn-primary" onclick="openAddModal()">+ Add Sales Order</button>
      </div>
      <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center;">
        <?php if (isset($_GET['role'])): ?>
          <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
        <?php endif; ?>
        <select name="status" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Statuses</option>
          <option value="COMPLETED" <?php echo ($status_filter === 'COMPLETED') ? 'selected' : ''; ?>>Completed</option>
          <option value="VOIDED" <?php echo ($status_filter === 'VOIDED') ? 'selected' : ''; ?>>Voided</option>
        </select>
        <input type="text" name="search" placeholder="Search Sale Ref..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 140px;">
        <button class="btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Sale Number</th>
          <th>Sold At</th>
          <th>Customer</th>
          <th>Location</th>
          <th>Tax / Total</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text3); padding: 30px;">
              No sales orders logged.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['sale_number']); ?></span></td>
              <td><?php echo e(date('Y-m-d H:i', strtotime($row['sold_at']))); ?></td>
              <td class="td-name"><?php echo e($row['customer_name'] ?? 'Walk-In Customer'); ?></td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['location_code']); ?></span></td>
              <td>
                <div style="font-size:10px; color:var(--text3);">Tax: <?php echo formatCurrency($row['tax_amount'], $bizCur); ?></div>
                <div class="td-bold" style="color:var(--green);"><?php echo formatCurrency($row['total_amount'], $bizCur); ?></div>
              </td>
              <td>
                <?php if ($row['status'] === 'COMPLETED'): ?>
                  <span class="status-pill pill-green">Completed</span>
                <?php else: ?>
                  <span class="status-pill pill-red" style="opacity: 0.6;">Voided</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <button class="btn-sm" onclick="viewDetails(<?php echo (int)$row['id']; ?>)">Invoice</button>
                  
                  <?php if ($row['status'] === 'COMPLETED'): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('VOID this sale? Stocks will be added back and GP reversed. This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="void_sale">
                      <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn-action reject" style="font-size:10px; padding:3px 6px;">Void</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
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
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page - 1); ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?page=<?php echo $i; ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page + 1); ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==========================================
     MODAL: LOG POS CUSTOMER SALE
     ========================================== -->
<!-- ==========================================
     MODAL: LOG POS CUSTOMER SALE
     ========================================== -->
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        Record POS Customer Sale
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addSaleForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_num">Sale Number <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="sale_number" id="s_num" value="SAL-<?php echo date('YmdHis'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_date">Sold At <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="datetime-local" name="sold_at" id="s_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_cust">Customer Account</label>
          <div class="field-wrap">
            <select name="customer_id" id="s_cust">
              <option value="">-- Walk-In Customer --</option>
              <?php foreach ($customers_list as $cust): ?>
                <option value="<?php echo $cust['id']; ?>"><?php echo e($cust['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_loc">Source Location <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="location_id" id="s_loc" required>
              <option value="">-- Choose Location --</option>
              <?php foreach ($locations_list as $loc): ?>
                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> [<?php echo e($loc['code']); ?>]</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_notes">Sale Notes / Remarks</label>
          <div class="field-wrap">
            <input type="text" name="notes" id="s_notes" placeholder="e.g. cash reconciliation note">
          </div>
        </div>

        <!-- Line Items Section -->
        <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <h4 style="font-size:11.5px; font-weight:600; text-transform:uppercase; color:var(--text3);">Sales Invoice Items</h4>
            <button type="button" class="btn-sm" onclick="addItemRow()">+ Add Item</button>
          </div>
          
          <div id="itemsContainer" style="display:flex; flex-direction:column; gap:8px; max-height: 250px; overflow-y:auto; padding-right:4px;">
            <!-- Dynamically added rows -->
          </div>

          <div style="border-top:1px dashed var(--border); margin-top:12px; padding-top:10px; font-size:12px; display:flex; flex-direction:column; gap:4px;">
            <div style="display:flex; justify-content:space-between;">
              <span>Subtotal:</span>
              <span id="saleSubtotal">0.00 <?php echo e($bizCur); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span>Tax (<?php echo (float)($default_tax_rate * 100); ?>%):</span>
              <span id="saleTax">0.00 <?php echo e($bizCur); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; font-size:13.5px; border-top:1px solid var(--border); padding-top:6px; margin-top:4px;">
              <span>Total Payable:</span>
              <span id="saleTotal">0.00 <?php echo e($bizCur); ?></span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="submitBtn">Log POS Cash Sale</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================
     MODAL: VIEW INVOICE
     ========================================== -->
<div class="modal-overlay" id="detailModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Tax Invoice Receipt: <span id="dt_sale_num" style="color:var(--orange);"></span>
      </div>
      <div style="display:inline-flex; gap:6px; align-items:center;">
        <button class="btn-sm" onclick="window.print()">Print</button>
        <button type="button" class="modal-close-btn" onclick="closeDetails()">✕</button>
      </div>
    </div>
    <div class="modal-body" style="font-size:12.5px;" id="detailContent">
      <!-- Populated by JS -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-sm" onclick="closeDetails()">Close</button>
    </div>
  </div>
</div>

<script>
var productsList = <?php echo json_encode($products_list); ?>;
var defaultTaxRate = <?php echo (float)$default_tax_rate; ?>;
var bizCurCode = "<?php echo e($bizCur); ?>";

// Add initial row
addItemRow();

function openAddModal() {
  document.getElementById('addModalOverlay').style.display = 'flex';
}

function closeAddModal() {
  document.getElementById('addModalOverlay').style.display = 'none';
}

function addItemRow() {
  const container = document.getElementById('itemsContainer');
  const index = container.children.length;
  
  const div = document.createElement('div');
  div.className = 'item-row';
  div.style.display = 'grid';
  div.style.gridTemplateColumns = '1fr 80px 100px 30px';
  div.style.gap = '6px';
  div.style.alignItems = 'center';
  div.id = 'row-' + index;

  let optionsHtml = '<option value="">-- Product --</option>';
  productsList.forEach(p => {
    optionsHtml += `<option value="${p.id}" data-price="${p.sale_price}">${p.name} (${p.sku})</option>`;
  });

  div.innerHTML = `
    <select name="product_ids[]" required onchange="rowProductChanged(${index})" style="font-size:11.5px; padding:6px;">
      ${optionsHtml}
    </select>
    <input type="number" name="quantities[]" min="0.0001" step="0.0001" placeholder="Qty" required oninput="recalcTotals()" style="font-size:11.5px; padding:6px;">
    <input type="number" name="unit_prices[]" min="0" step="0.0001" placeholder="Price" required oninput="recalcTotals()" style="font-size:11.5px; padding:6px;">
    <button type="button" class="btn-action reject" onclick="removeItemRow(${index})" style="padding:4px; font-size:11px; text-align:center;">X</button>
  `;
  
  container.appendChild(div);
}

function removeItemRow(index) {
  const container = document.getElementById('itemsContainer');
  if (container.children.length > 1) {
    const row = document.getElementById('row-' + index);
    if (row) row.remove();
    recalcTotals();
  } else {
    alert("Invoice must contain at least one sale item.");
  }
}

function rowProductChanged(index) {
  const row = document.getElementById('row-' + index);
  const select = row.querySelector('select');
  const priceInput = row.querySelector('input[name="unit_prices[]"]');
  const selectedOpt = select.options[select.selectedIndex];
  const price = selectedOpt.getAttribute('data-price');
  if (price) {
    priceInput.value = parseFloat(price).toFixed(4);
  }
  recalcTotals();
}

function recalcTotals() {
  let subtotal = 0;
  const container = document.getElementById('itemsContainer');
  const rows = container.querySelectorAll('.item-row');
  rows.forEach(r => {
    const qty = parseFloat(r.querySelector('input[name="quantities[]"]').value) || 0;
    const price = parseFloat(r.querySelector('input[name="unit_prices[]"]').value) || 0;
    subtotal += (qty * price);
  });
  
  const tax = subtotal * defaultTaxRate;
  const total = subtotal + tax;

  document.getElementById('saleSubtotal').textContent = subtotal.toFixed(2) + " " + bizCurCode;
  document.getElementById('saleTax').textContent = tax.toFixed(2) + " " + bizCurCode;
  document.getElementById('saleTotal').textContent = total.toFixed(2) + " " + bizCurCode;
}

// Close modals when clicking outside
document.getElementById('addModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('detailModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeDetails();
});

function viewDetails(saleId) {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.set('view_id', saleId);
  window.location.search = urlParams.toString();
}

function closeDetails() {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.delete('view_id');
  window.location.search = urlParams.toString();
}

// Safeguard double submissions client-side
document.getElementById('addSaleForm').addEventListener('submit', function() {
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').style.opacity = '0.7';
  document.getElementById('submitBtn').textContent = 'Logging sale...';
});
</script>

<?php
// Handle Invoice Details load in PHP
if (isset($_GET['view_id'])):
    $viewId = (int)$_GET['view_id'];
    $vQuery = "
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.tax_number as customer_tax,
               l.name as location_name, l.address as location_address
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        JOIN business_locations l ON s.location_id = l.id
        WHERE s.id = ? AND s.business_id = ?
        LIMIT 1
    ";
    $vStmt = mysqli_prepare($conn, $vQuery);
    mysqli_stmt_bind_param($vStmt, 'ii', $viewId, $businessId);
    mysqli_stmt_execute($vStmt);
    $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($vStmt));

    if ($sale):
        // Fetch invoice line items
        $iQuery = "
            SELECT si.*, pr.name as product_name, pr.sku, pr.uom
            FROM sale_items si
            JOIN products pr ON si.product_id = pr.id
            WHERE si.sale_id = ?
        ";
        $iStmt = mysqli_prepare($conn, $iQuery);
        mysqli_stmt_bind_param($iStmt, 'i', $viewId);
        mysqli_stmt_execute($iStmt);
        $itemsRes = mysqli_stmt_get_result($iStmt);
?>
<script>
  (function() {
    const detailsDiv = document.getElementById('detailContent');
    document.getElementById('dt_sale_num').textContent = "<?php echo e($sale['sale_number']); ?>";
    
    let itemsHtml = '';
    <?php while ($it = mysqli_fetch_assoc($itemsRes)): ?>
      itemsHtml += `
        <tr>
          <td><span class="code-badge"><?php echo e($it['sku']); ?></span></td>
          <td class="td-name"><?php echo e($it['product_name']); ?></td>
          <td><?php echo (float)$it['quantity']; ?> (${"<?php echo e($it['uom']); ?>"})</td>
          <td><?php echo formatCurrency($it['unit_price'], $bizCur); ?></td>
          <td class="td-bold" style="color:var(--green);">${"<?php echo formatCurrency($it['line_total'], $bizCur); ?>"}</td>
        </tr>
      `;
    <?php endwhile; ?>

    detailsDiv.innerHTML = `
      <!-- Invoice Print Header Layout -->
      <div style="border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <h4 style="font-size:14px; font-weight:700; margin:0;"><?php echo e($_SESSION['first_name'] ?? ''); ?> Inc.</h4>
          <div style="font-size:10px; color:var(--text3);"><?php echo e($sale['location_name']); ?><br><?php echo e($sale['location_address'] ?? ''); ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:10px; color:var(--text3); text-transform:uppercase;">Invoice Date</div>
          <div style="font-weight:500;"><?php echo formatDate($sale['sold_at']); ?></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 16px;">
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Billed To (Customer)</strong>
          <div><?php echo e($sale['customer_name'] ?? 'Walk-In Customer'); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_phone'] ? ('Phone: '.$sale['customer_phone']) : ''); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_tax'] ? ('TIN: '.$sale['customer_tax']) : ''); ?></div>
        </div>
      </div>
      
      <table class="data-table" style="margin-bottom:12px;">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product Description</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Line Total</th>
          </tr>
        </thead>
        <tbody>
          \${itemsHtml}
        </tbody>
      </table>

      <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; font-size:12px; border-top:1px solid var(--border); padding-top:10px; font-weight:500;">
        <div style="display:flex; justify-content:space-between; width:220px;">
          <span style="color:var(--text3);">Subtotal:</span>
          <span>${"<?php echo formatCurrency($sale['subtotal'], $bizCur); ?>"}</span>
        </div>
        <div style="display:flex; justify-content:space-between; width:220px;">
          <span style="color:var(--text3);">VAT (Tax):</span>
          <span>${"<?php echo formatCurrency($sale['tax_amount'], $bizCur); ?>"}</span>
        </div>
        <div style="display:flex; justify-content:space-between; width:220px; font-weight:700; font-size:14px; border-top:1px solid var(--border); padding-top:6px; margin-top:4px; color:var(--green);">
          <span>Total Paid:</span>
          <span>${"<?php echo formatCurrency($sale['total_amount'], $bizCur); ?>"}</span>
        </div>
      </div>
    `;

    document.getElementById('detailModalOverlay').style.display = 'flex';
  })();
</script>
<?php
    endif;
endif;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
