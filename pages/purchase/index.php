<?php
$page_title = 'Procurement & Purchases';
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

$where_clause = " WHERE p.business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (p.purchase_number LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Count total
$count_query = "
    SELECT COUNT(*) as total 
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    $where_clause
";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT p.*, s.name as supplier_name, l.name as location_name, l.code as location_code
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    JOIN business_locations l ON p.location_id = l.id
    $where_clause
    ORDER BY p.purchase_date DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch suppliers
$supQuery = "SELECT id, name FROM suppliers WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$supStmt = mysqli_prepare($conn, $supQuery);
mysqli_stmt_bind_param($supStmt, 'i', $businessId);
mysqli_stmt_execute($supStmt);
$suppliersResult = mysqli_stmt_get_result($supStmt);
$suppliers_list = [];
while ($sRow = mysqli_fetch_assoc($suppliersResult)) {
    $suppliers_list[] = $sRow;
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
$prodQuery = "SELECT id, name, sku, cost_price FROM products WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$pStmt = mysqli_prepare($conn, $prodQuery);
mysqli_stmt_bind_param($pStmt, 'i', $businessId);
mysqli_stmt_execute($pStmt);
$products_list = [];
$pResult = mysqli_stmt_get_result($pStmt);
while ($pRow = mysqli_fetch_assoc($pResult)) {
    $products_list[] = $pRow;
}

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
$canCreatePurchase = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['create']);
$canUpdatePurchase = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
$canReceivePurchase = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['receive']);
?>
<div>
  <!-- Left Column: Table List -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div class="card-title">Purchase Orders (PO) Registry</div>
        <?php if ($canCreatePurchase): ?>
          <button class="btn-primary" onclick="openAddModal()">+ Add Purchase Order</button>
        <?php endif; ?>
      </div>
      <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center;">
        <?php if (isset($_GET['role'])): ?>
          <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
        <?php endif; ?>
        <select name="status" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Statuses</option>
          <option value="DRAFT" <?php echo ($status_filter === 'DRAFT') ? 'selected' : ''; ?>>Draft</option>
          <option value="ORDERED" <?php echo ($status_filter === 'ORDERED') ? 'selected' : ''; ?>>Ordered</option>
          <option value="RECEIVED" <?php echo ($status_filter === 'RECEIVED') ? 'selected' : ''; ?>>Received</option>
          <option value="CANCELLED" <?php echo ($status_filter === 'CANCELLED') ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <input type="text" name="search" placeholder="Search PO Ref..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 140px;">
        <button class="btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>PO Number</th>
          <th>Order Date</th>
          <th>Supplier</th>
          <th>Target Location</th>
          <th>Total Amount</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text3); padding: 30px;">
              No purchase orders recorded.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['purchase_number']); ?></span></td>
              <td><?php echo e(date('Y-m-d', strtotime($row['purchase_date']))); ?></td>
              <td class="td-name"><?php echo e($row['supplier_name']); ?></td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['location_code']); ?></span></td>
              <td class="td-bold" style="color:var(--blue);"><?php echo formatCurrency($row['total_amount'], $bizCur); ?></td>
              <td>
                <?php if ($row['status'] === 'RECEIVED'): ?>
                  <span class="status-pill pill-green">Received</span>
                <?php elseif ($row['status'] === 'ORDERED'): ?>
                  <span class="status-pill pill-blue">Ordered</span>
                <?php elseif ($row['status'] === 'DRAFT'): ?>
                  <span class="status-pill pill-amber">Draft</span>
                <?php else: ?>
                  <span class="status-pill pill-red">Cancelled</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <button class="btn-sm" onclick="viewDetails(<?php echo (int)$row['id']; ?>)">View</button>
                  
                  <?php if ($row['status'] === 'DRAFT' && $canUpdatePurchase): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Send Purchase Order?');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="mark_ordered">
                      <input type="hidden" name="purchase_id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn-sm" style="background:var(--blue); color:#fff; border:none;">Order</button>
                    </form>
                  <?php elseif ($row['status'] === 'ORDERED' && $canReceivePurchase): ?>
                    <button class="btn-sm" style="background:var(--green); color:#fff; border:none;" onclick="triggerReceive(<?php echo (int)$row['id']; ?>)">Receive</button>
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
     MODAL: CREATE PURCHASE ORDER
     ========================================== -->
<!-- ==========================================
     MODAL: CREATE PURCHASE ORDER
     ========================================== -->
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        Create Purchase Order
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addPOForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="po_num">PO Number <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="purchase_number" id="po_num" value="PO-<?php echo date('YmdHis'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="po_date">Order Date <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="datetime-local" name="purchase_date" id="po_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="po_sup">Select Supplier <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="supplier_id" id="po_sup" required>
              <option value="">-- Choose Supplier --</option>
              <?php foreach ($suppliers_list as $sup): ?>
                <option value="<?php echo $sup['id']; ?>"><?php echo e($sup['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="po_loc">Target Location (Warehouse/Branch) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="location_id" id="po_loc" required>
              <option value="">-- Choose Location --</option>
              <?php foreach ($locations_list as $loc): ?>
                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> [<?php echo e($loc['code']); ?>]</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="po_notes">Notes / Special Instructions</label>
          <div class="field-wrap">
            <input type="text" name="notes" id="po_notes" placeholder="e.g. deliver before 5 PM">
          </div>
        </div>

        <!-- Line Items Section -->
        <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
          <div style="display:flex; justify-space-between; align-items:center; margin-bottom:8px;">
            <h4 style="font-size:11.5px; font-weight:600; text-transform:uppercase; color:var(--text3);">Procurement Items</h4>
            <button type="button" class="btn-sm" onclick="addItemRow()">+ Add Item</button>
          </div>
          
          <div id="itemsContainer" style="display:flex; flex-direction:column; gap:8px; max-height: 250px; overflow-y:auto; padding-right:4px;">
            <!-- Dynamically added rows -->
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-weight:600; font-size:13px;">
            <span>Subtotal:</span>
            <span id="poSubtotal">0.00 <?php echo e($bizCur); ?></span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="submitPOBtn">Save Purchase Order</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================
     MODAL: VIEW DETAILS
     ========================================== -->
<div class="modal-overlay" id="detailModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Purchase Order Details: <span id="dt_po_num" style="color:var(--orange);"></span>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeDetails()">✕</button>
    </div>
    <div class="modal-body" style="font-size:12.5px;" id="detailContent">
      <!-- Populated by JS -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-sm" onclick="closeDetails()">Close</button>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL: RECEIVE PO ITEMS
     ========================================== -->
<div class="modal-overlay" id="receiveModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        Record Physical Stock Receipt
      </div>
      <button type="button" class="modal-close-btn" onclick="closeReceive()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="receiveForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="receive_po">
        <input type="hidden" name="purchase_id" id="rc_po_id" value="">
        
        <div style="font-size:12px; color:var(--text3); margin-bottom:12px;">
          Confirm the actual quantities physically received at the warehouse location.
        </div>

        <table class="data-table" style="margin-bottom: 16px;">
          <thead>
            <tr>
              <th>SKU / Product</th>
              <th>Ordered Qty</th>
              <th>Received Qty</th>
            </tr>
          </thead>
          <tbody id="receiveItemsBody">
            <!-- Populated dynamically -->
          </tbody>
        </table>

        <div class="field">
          <label for="rc_invoice">Supplier Invoice / Delivery Note Ref</label>
          <div class="field-wrap">
            <input type="text" name="supplier_invoice_number" id="rc_invoice" placeholder="e.g. INV-92837">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeReceive()">Cancel</button>
        <button type="submit" class="btn-primary" id="postReceiveBtn">Record Goods Received Note (GRN)</button>
      </div>
    </form>
  </div>
</div>

<!-- Simple raw data arrays for JS row rendering -->
<script>
var productsList = <?php echo json_encode($products_list); ?>;
var bizCurCode = "<?php echo e($bizCur); ?>";

// Add initial line row
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
    optionsHtml += `<option value="${p.id}" data-cost="${p.cost_price}">${p.name} (${p.sku})</option>`;
  });

  div.innerHTML = `
    <select name="product_ids[]" required onchange="rowProductChanged(${index})" style="font-size:11.5px; padding:6px;">
      ${optionsHtml}
    </select>
    <input type="number" name="quantities[]" min="0.0001" step="0.0001" placeholder="Qty" required oninput="recalcPOSubtotal()" style="font-size:11.5px; padding:6px;">
    <input type="number" name="unit_costs[]" min="0" step="0.0001" placeholder="Cost" required oninput="recalcPOSubtotal()" style="font-size:11.5px; padding:6px;">
    <button type="button" class="btn-action reject" onclick="removeItemRow(${index})" style="padding:4px; font-size:11px; text-align:center;">X</button>
  `;
  
  container.appendChild(div);
}

function removeItemRow(index) {
  const container = document.getElementById('itemsContainer');
  if (container.children.length > 1) {
    const row = document.getElementById('row-' + index);
    if (row) row.remove();
    recalcPOSubtotal();
  } else {
    alert("Purchase Order must contain at least one line item.");
  }
}

function rowProductChanged(index) {
  const row = document.getElementById('row-' + index);
  const select = row.querySelector('select');
  const costInput = row.querySelector('input[name="unit_costs[]"]');
  const selectedOpt = select.options[select.selectedIndex];
  const cost = selectedOpt.getAttribute('data-cost');
  if (cost) {
    costInput.value = parseFloat(cost).toFixed(4);
  }
  recalcPOSubtotal();
}

function recalcPOSubtotal() {
  let subtotal = 0;
  const container = document.getElementById('itemsContainer');
  const rows = container.querySelectorAll('.item-row');
  rows.forEach(r => {
    const qty = parseFloat(r.querySelector('input[name="quantities[]"]').value) || 0;
    const cost = parseFloat(r.querySelector('input[name="unit_costs[]"]').value) || 0;
    subtotal += (qty * cost);
  });
  document.getElementById('poSubtotal').textContent = subtotal.toFixed(2) + " " + bizCurCode;
}

// Close modals when clicking outside
document.getElementById('addModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('detailModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeDetails();
});
document.getElementById('receiveModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeReceive();
});

function viewDetails(poId) {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.set('view_id', poId);
  window.location.search = urlParams.toString();
}

function closeDetails() {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.delete('view_id');
  window.location.search = urlParams.toString();
}

function triggerReceive(poId) {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.set('receive_id', poId);
  window.location.search = urlParams.toString();
}

function closeReceive() {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.delete('receive_id');
  window.location.search = urlParams.toString();
}

// Safeguard double submissions client-side
document.getElementById('addPOForm').addEventListener('submit', function() {
  document.getElementById('submitPOBtn').disabled = true;
  document.getElementById('submitPOBtn').style.opacity = '0.7';
  document.getElementById('submitPOBtn').textContent = 'Saving PO...';
});
</script>

<?php
// Handle View ID Modal in PHP load state
if (isset($_GET['view_id'])):
    $viewId = (int)$_GET['view_id'];
    $vQuery = "
        SELECT p.*, s.name as supplier_name, l.name as location_name
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id
        JOIN business_locations l ON p.location_id = l.id
        WHERE p.id = ? AND p.business_id = ?
        LIMIT 1
    ";
    $vStmt = mysqli_prepare($conn, $vQuery);
    mysqli_stmt_bind_param($vStmt, 'ii', $viewId, $businessId);
    mysqli_stmt_execute($vStmt);
    $po = mysqli_fetch_assoc(mysqli_stmt_get_result($vStmt));
    
    if ($po):
        // Fetch items
        $iQuery = "
            SELECT pi.*, pr.name as product_name, pr.sku, pr.uom
            FROM purchase_items pi
            JOIN products pr ON pi.product_id = pr.id
            WHERE pi.purchase_id = ?
        ";
        $iStmt = mysqli_prepare($conn, $iQuery);
        mysqli_stmt_bind_param($iStmt, 'i', $viewId);
        mysqli_stmt_execute($iStmt);
        $itemsRes = mysqli_stmt_get_result($iStmt);
?>
<script>
  (function() {
    const detailsDiv = document.getElementById('detailContent');
    document.getElementById('dt_po_num').textContent = "<?php echo e($po['purchase_number']); ?>";
    
    let itemsHtml = '';
    <?php while ($it = mysqli_fetch_assoc($itemsRes)): ?>
      itemsHtml += `
        <tr>
          <td><span class="code-badge"><?php echo e($it['sku']); ?></span></td>
          <td class="td-name"><?php echo e($it['product_name']); ?></td>
          <td><?php echo (float)$it['ordered_quantity']; ?> (${"<?php echo e($it['uom']); ?>"})</td>
          <td><?php echo (float)$it['received_quantity']; ?></td>
          <td class="td-bold"><?php echo formatCurrency($it['unit_cost'], $bizCur); ?></td>
          <td class="td-bold" style="color:var(--blue);"><?php echo formatCurrency($it['line_total'], $bizCur); ?></td>
        </tr>
      `;
    <?php endwhile; ?>

    detailsDiv.innerHTML = `
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 16px;">
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Supplier</strong>
          <div><?php echo e($po['supplier_name']); ?></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Target Location</strong>
          <div><?php echo e($po['location_name']); ?></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Order Date</strong>
          <div><?php echo formatDate($po['purchase_date']); ?></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Invoice Number</strong>
          <div><?php echo e($po['supplier_invoice_number'] ?? 'Not received yet'); ?></div>
        </div>
      </div>
      
      <table class="data-table" style="margin-bottom:12px;">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product</th>
            <th>Ordered</th>
            <th>Received</th>
            <th>Cost Price</th>
            <th>Line Total</th>
          </tr>
        </thead>
        <tbody>
          \${itemsHtml}
        </tbody>
      </table>

      <div style="display:flex; justify-content:flex-end; gap:20px; font-weight:600; font-size:13px; border-top:1px solid var(--border); padding-top:12px;">
        <span style="color:var(--text3);">Total PO Value:</span>
        <span style="color:var(--blue);">${"<?php echo formatCurrency($po['total_amount'], $bizCur); ?>"}</span>
      </div>
    `;

    document.getElementById('detailModalOverlay').style.display = 'flex';
  })();
</script>
<?php
    endif;
endif;
?>

<?php
// Handle Receive ID Modal in PHP load state
if (isset($_GET['receive_id'])):
    $receiveId = (int)$_GET['receive_id'];
    $rQuery = "
        SELECT p.*
        FROM purchases p
        WHERE p.id = ? AND p.business_id = ? AND p.status = 'ORDERED'
        LIMIT 1
    ";
    $rStmt = mysqli_prepare($conn, $rQuery);
    mysqli_stmt_bind_param($rStmt, 'ii', $receiveId, $businessId);
    mysqli_stmt_execute($rStmt);
    $po = mysqli_fetch_assoc(mysqli_stmt_get_result($rStmt));
    
    if ($po):
        // Fetch items
        $iQuery = "
            SELECT pi.*, pr.name as product_name, pr.sku
            FROM purchase_items pi
            JOIN products pr ON pi.product_id = pr.id
            WHERE pi.purchase_id = ?
        ";
        $iStmt = mysqli_prepare($conn, $iQuery);
        mysqli_stmt_bind_param($iStmt, 'i', $receiveId);
        mysqli_stmt_execute($iStmt);
        $itemsRes = mysqli_stmt_get_result($iStmt);
?>
<script>
  (function() {
    document.getElementById('rc_po_id').value = "<?php echo (int)$po['id']; ?>";
    const body = document.getElementById('receiveItemsBody');
    
    let rowsHtml = '';
    <?php while ($it = mysqli_fetch_assoc($itemsRes)): ?>
      rowsHtml += `
        <tr>
          <td>
            <div style="font-weight:600;"><?php echo e($it['product_name']); ?></div>
            <span class="code-badge" style="font-size:10px;"><?php echo e($it['sku']); ?></span>
            <input type="hidden" name="item_ids[]" value="<?php echo (int)$it['id']; ?>">
          </td>
          <td><?php echo (float)$it['ordered_quantity']; ?></td>
          <td>
            <input type="number" name="received_quantities[]" min="0" step="0.0001" value="<?php echo (float)$it['ordered_quantity']; ?>" required style="font-size:12px; padding:4px; width:100px;">
          </td>
        </tr>
      `;
    <?php endwhile; ?>

    body.innerHTML = rowsHtml;
    document.getElementById('receiveModalOverlay').style.display = 'flex';
  })();
</script>
<?php
    endif;
endif;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
