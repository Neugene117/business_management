<?php
$page_title = 'Products Catalog';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);

$canCreateProduct = hasPermission($conn, $membershipId, $businessId, $permissions['create']);
$canUpdateProduct = hasPermission($conn, $membershipId, $businessId, $permissions['update']);
$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ' WHERE p.business_id=?';
$params = [$businessId];
$types = 'i';
if ($search !== '') {
    $where .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ss';
}
if ($categoryFilter > 0) {
    $where .= ' AND p.category_id=?';
    $params[] = $categoryFilter;
    $types .= 'i';
}

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM products p $where");
mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRows = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
$totalPages = (int)ceil($totalRows / $limit);

$productStmt = mysqli_prepare($conn, "SELECT p.id,p.sku,p.name,p.category_id,p.uom_id,p.uom,p.is_active,pc.name category_name
    FROM products p
    JOIN product_categories pc ON pc.id=p.category_id AND pc.business_id=p.business_id
    $where ORDER BY p.name,p.sku LIMIT ? OFFSET ?");
$listParams = array_merge($params, [$limit, $offset]);
$listTypes = $types . 'ii';
mysqli_stmt_bind_param($productStmt, $listTypes, ...$listParams);
mysqli_stmt_execute($productStmt);
$products = mysqli_stmt_get_result($productStmt);

$categoryStmt = mysqli_prepare($conn, 'SELECT id,name,is_active FROM product_categories WHERE business_id=? ORDER BY is_active DESC,name');
mysqli_stmt_bind_param($categoryStmt, 'i', $businessId);
mysqli_stmt_execute($categoryStmt);
$categoryOptions = [];
$categoryResult = mysqli_stmt_get_result($categoryStmt);
while ($row = mysqli_fetch_assoc($categoryResult)) $categoryOptions[] = $row;

$uomStmt = mysqli_prepare($conn, 'SELECT id,business_id,code,name,symbol FROM units_of_measure WHERE business_id IS NULL OR business_id=? ORDER BY business_id IS NULL DESC,name');
mysqli_stmt_bind_param($uomStmt, 'i', $businessId);
mysqli_stmt_execute($uomStmt);
$uomOptions = [];
$uomResult = mysqli_stmt_get_result($uomStmt);
while ($row = mysqli_fetch_assoc($uomResult)) $uomOptions[] = $row;

$summaryStmt = mysqli_prepare($conn, 'SELECT COUNT(*) total,SUM(is_active=1) active FROM products WHERE business_id=?');
mysqli_stmt_bind_param($summaryStmt, 'i', $businessId);
mysqli_stmt_execute($summaryStmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStmt)) ?: ['total'=>0,'active'=>0];
$csrfToken = generateCsrfToken();
$roleQuery = getRolePreviewQuery();
$queryBase = array_filter([
    'search' => $search,
    'category_id' => $categoryFilter ?: null,
    'role' => getPreviewRole(),
], static fn($value) => $value !== null && $value !== '');
?>

<div class="products-page-head">
  <div>
    <h1>Products</h1>
    <p>Register and organize the core information that identifies each product.</p>
  </div>
  <div class="products-head-actions">
    <a class="btn-sm" href="../product_category/index.php<?php echo e($roleQuery); ?>">Product Categories</a>
    <a class="btn-sm" href="../unit/index.php<?php echo e($roleQuery); ?>">Units of Measure</a>
    <?php if ($canCreateProduct): ?><button type="button" class="btn-primary" onclick="openProductModal()">+ Register Product</button><?php endif; ?>
  </div>
</div>

<div class="product-summary">
  <div class="card"><span>Registered Products</span><strong><?php echo (int)$summary['total']; ?></strong></div>
  <div class="card"><span>Active Products</span><strong><?php echo (int)$summary['active']; ?></strong></div>
  <div class="card"><span>Product Categories</span><strong><?php echo count($categoryOptions); ?></strong></div>
</div>

<section class="card products-card">
  <div class="products-toolbar">
    <div><div class="card-title">Product Directory</div><p>Only product details registered in the catalog are shown below.</p></div>
    <form method="GET" action="index.php" class="product-filter">
      <?php if (getPreviewRole()): ?><input type="hidden" name="role" value="<?php echo e(getPreviewRole()); ?>"><?php endif; ?>
      <select name="category_id" aria-label="Filter by product category">
        <option value="">All categories</option>
        <?php foreach ($categoryOptions as $category): ?>
          <option value="<?php echo (int)$category['id']; ?>" <?php echo $categoryFilter === (int)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?><?php echo (int)$category['is_active'] === 0 ? ' (Inactive)' : ''; ?></option>
        <?php endforeach; ?>
      </select>
      <input name="search" value="<?php echo e($search); ?>" placeholder="Search by SKU or product name">
      <button class="btn-sm" type="submit">Filter</button>
      <?php if ($search !== '' || $categoryFilter > 0): ?><a class="btn-sm clear-filter" href="index.php<?php echo e($roleQuery); ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <div class="product-table-wrap">
    <table class="data-table product-table">
      <thead><tr><th>SKU</th><th>Product Name</th><th>Category</th><th>Unit of Measure</th><th>Status</th><th class="action-column">Actions</th></tr></thead>
      <tbody>
        <?php if (mysqli_num_rows($products) === 0): ?>
          <tr><td colspan="6" class="product-empty">No products match the selected filters.</td></tr>
        <?php else: while ($product = mysqli_fetch_assoc($products)): ?>
          <tr>
            <td><span class="code-badge"><?php echo e($product['sku']); ?></span></td>
            <td class="td-name"><?php echo e($product['name']); ?></td>
            <td><?php echo e($product['category_name']); ?></td>
            <td><span class="uom-badge"><?php echo e($product['uom']); ?></span></td>
            <td><span class="status-pill <?php echo (int)$product['is_active'] === 1 ? 'pill-green' : 'pill-red'; ?>"><?php echo (int)$product['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
            <td class="action-column">
              <?php if ($canUpdateProduct): ?>
                <button type="button" class="btn-sm" data-product="<?php echo e(json_encode($product)); ?>" onclick="openProductModal(JSON.parse(this.dataset.product))">Edit</button>
                <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" class="inline-form" onsubmit="return confirm('Change the status of this product?');">
                  <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                  <button type="submit" class="btn-sm status-button"><?php echo (int)$product['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?></button>
                </form>
              <?php else: ?><span class="muted-action">View only</span><?php endif; ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="product-pagination"><span>Page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; <?php echo $totalRows; ?> products</span><div>
      <?php if ($page > 1): ?><a class="btn-sm" href="?<?php echo e(http_build_query(array_merge($queryBase, ['page'=>$page-1]))); ?>">Previous</a><?php endif; ?>
      <?php if ($page < $totalPages): ?><a class="btn-sm" href="?<?php echo e(http_build_query(array_merge($queryBase, ['page'=>$page+1]))); ?>">Next</a><?php endif; ?>
    </div></div>
  <?php endif; ?>
</section>

<?php if ($canCreateProduct || $canUpdateProduct): ?>
<div class="modal-overlay" id="productModal" aria-hidden="true">
  <div class="modal-content-card product-modal-card" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
    <div class="modal-header">
      <div><div class="modal-title" id="productModalTitle">Register Product</div><p id="productModalSubtitle">Enter the product's catalog information.</p></div>
      <button type="button" class="modal-close-btn" onclick="closeProductModal()" aria-label="Close">&times;</button>
    </div>
    <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" id="productForm">
      <div class="modal-body product-form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" id="product_action" value="create">
        <input type="hidden" name="product_id" id="product_id">
        <label>SKU Code <span>*</span><input name="sku" id="product_sku" maxlength="100" placeholder="e.g. WATER-500" required></label>
        <label>Product Name <span>*</span><input name="name" id="product_name" maxlength="200" placeholder="e.g. Mineral Water 500ml" required></label>
        <label>Product Category <span>*</span><select name="category_id" id="product_category" required><option value="">Choose a category</option><?php foreach ($categoryOptions as $category): if ((int)$category['is_active'] !== 1) continue; ?><option value="<?php echo (int)$category['id']; ?>"><?php echo e($category['name']); ?></option><?php endforeach; ?></select><small><a href="../product_category/index.php<?php echo e($roleQuery); ?>">Manage categories</a></small></label>
        <label>Unit of Measure <span>*</span><select name="uom" id="product_uom" required><option value="">Choose a unit</option><?php foreach ($uomOptions as $unit): ?><option value="<?php echo (int)$unit['id']; ?>"><?php echo e($unit['name']); ?> (<?php echo e($unit['code']); ?>)</option><?php endforeach; ?></select><small><a href="../unit/index.php<?php echo e($roleQuery); ?>">Manage units of measure</a></small></label>
      </div>
      <div class="modal-footer"><button type="button" class="btn-sm" onclick="closeProductModal()">Cancel</button><button type="submit" class="btn-primary" id="productSubmit">Save Product</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
.products-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}.products-page-head h1{margin:0;color:var(--text);font-size:22px}.products-page-head p,.products-toolbar p,.modal-header p{margin:5px 0 0;color:var(--text3);font-size:11px}.products-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.products-head-actions a{text-decoration:none}.product-summary{display:grid;grid-template-columns:repeat(3,minmax(150px,210px));gap:10px;margin-bottom:14px}.product-summary .card{padding:14px 16px;box-shadow:none}.product-summary span{display:block;color:var(--text3);font-size:9px;text-transform:uppercase;letter-spacing:.04em}.product-summary strong{display:block;margin-top:7px;color:var(--text);font-size:19px}.products-card{overflow:hidden;box-shadow:none}.products-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:16px;border-bottom:1px solid var(--table-border)}.product-filter{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.product-filter select,.product-filter input{min-height:35px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit;font-size:11px}.product-filter input{min-width:220px}.clear-filter{text-decoration:none}.product-table-wrap{overflow-x:auto}.product-table{min-width:760px}.product-table td{vertical-align:middle}.uom-badge{display:inline-flex;padding:5px 8px;border:1px solid var(--border);border-radius:999px;background:var(--bg);color:var(--text2);font-size:9px;font-weight:650}.action-column{text-align:right!important;white-space:nowrap}.inline-form{display:inline}.status-button{margin-left:4px;background:var(--bg)!important}.muted-action{color:var(--text3);font-size:9px}.product-empty{text-align:center!important;color:var(--text3)!important;padding:36px!important}.product-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-top:1px solid var(--table-border);color:var(--text3);font-size:10px}.product-pagination>div{display:flex;gap:6px}.product-pagination a{text-decoration:none}.product-modal-card{width:min(640px,calc(100vw - 28px))}.product-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.product-form-grid>input[type=hidden]{display:none}.product-form-grid label{display:flex;flex-direction:column;gap:6px;color:var(--text2);font-size:10px;font-weight:650}.product-form-grid label>span{color:var(--red)}.product-form-grid input,.product-form-grid select{width:100%;min-height:40px;padding:9px 11px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit;outline:none}.product-form-grid input:focus,.product-form-grid select:focus{border-color:var(--border-hover)}.product-form-grid small{font-size:9px;font-weight:400}.product-form-grid small a{color:var(--blue);text-decoration:none}@media(max-width:850px){.products-page-head,.products-toolbar{align-items:stretch;flex-direction:column}.products-head-actions,.product-filter{justify-content:flex-start}.product-summary{grid-template-columns:repeat(3,1fr)}}@media(max-width:580px){.product-summary,.product-form-grid{grid-template-columns:1fr}.product-filter{display:grid;grid-template-columns:1fr 1fr}.product-filter input{min-width:0;grid-column:1/-1}.products-head-actions{align-items:stretch;flex-direction:column}.product-pagination{align-items:flex-start;flex-direction:column}}
</style>

<script>
const productModal = document.getElementById('productModal');
function openProductModal(product = null) {
  if (!productModal) return;
  document.getElementById('productForm').reset();
  document.getElementById('product_action').value = product ? 'update' : 'create';
  document.getElementById('product_id').value = product ? product.id : '';
  document.getElementById('product_sku').value = product ? product.sku : '';
  document.getElementById('product_name').value = product ? product.name : '';
  document.getElementById('product_category').value = product ? product.category_id : '';
  document.getElementById('product_uom').value = product ? product.uom_id : '';
  document.getElementById('productModalTitle').textContent = product ? 'Edit Product' : 'Register Product';
  document.getElementById('productModalSubtitle').textContent = product ? 'Update the registered catalog information.' : 'Enter the product\'s catalog information.';
  document.getElementById('productSubmit').textContent = product ? 'Save Changes' : 'Save Product';
  productModal.style.display = 'flex';
  productModal.setAttribute('aria-hidden', 'false');
  setTimeout(() => document.getElementById('product_sku').focus(), 0);
}
function closeProductModal() { if (!productModal) return; productModal.style.display = 'none'; productModal.setAttribute('aria-hidden', 'true'); }
productModal?.addEventListener('click', event => { if (event.target === productModal) closeProductModal(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeProductModal(); });
document.getElementById('productForm')?.addEventListener('submit', function () { const button=document.getElementById('productSubmit'); button.disabled=true; button.textContent='Saving...'; });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
