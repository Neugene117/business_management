<?php
$page_title = 'Product Categories';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);
$canCreate = hasPermission($conn, $membershipId, $businessId, $permissions['create']);
$canUpdate = hasPermission($conn, $membershipId, $businessId, $permissions['update']);
$csrfToken = generateCsrfToken();
$roleQuery = getRolePreviewQuery();

$stmt = mysqli_prepare($conn, 'SELECT pc.id,pc.name,pc.description,pc.is_active,COUNT(p.id) product_count
    FROM product_categories pc
    LEFT JOIN products p ON p.business_id=pc.business_id AND p.category_id=pc.id
    WHERE pc.business_id=?
    GROUP BY pc.id,pc.name,pc.description,pc.is_active
    ORDER BY pc.name');
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$categories = [];
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $categories[] = $row;
$activeCount = count(array_filter($categories, static fn($category) => (int)$category['is_active'] === 1));
$assignedProducts = array_sum(array_map(static fn($category) => (int)$category['product_count'], $categories));
?>

<div class="categories-page-head">
  <div><h1>Product Categories</h1><p>Create and maintain the categories used to organize registered products.</p></div>
  <div class="categories-head-actions">
    <a class="btn-sm" href="../product/index.php<?php echo e($roleQuery); ?>">Back to Products</a>
    <?php if ($canCreate): ?><button type="button" class="btn-primary" onclick="openCategoryModal()">+ Add Category</button><?php endif; ?>
  </div>
</div>

<div class="category-summary">
  <div class="card"><span>Total Categories</span><strong><?php echo count($categories); ?></strong></div>
  <div class="card"><span>Active Categories</span><strong><?php echo $activeCount; ?></strong></div>
  <div class="card"><span>Assigned Products</span><strong><?php echo $assignedProducts; ?></strong></div>
</div>

<section class="card category-table-card">
  <div class="category-card-head"><div><div class="card-title">Category Directory</div><p>Categories in use are protected from deletion.</p></div></div>
  <div class="category-table-wrap">
    <table class="data-table category-table">
      <thead><tr><th>Category Name</th><th>Description</th><th>Products</th><th>Status</th><th class="category-actions-column">Actions</th></tr></thead>
      <tbody>
        <?php if (!$categories): ?>
          <tr><td colspan="5" class="category-empty">No product categories have been created.</td></tr>
        <?php else: foreach ($categories as $category): ?>
          <tr>
            <td class="td-name"><?php echo e($category['name']); ?></td>
            <td class="category-description"><?php echo $category['description'] ? e($category['description']) : '<span>No description</span>'; ?></td>
            <td><span class="product-count-badge"><?php echo (int)$category['product_count']; ?></span></td>
            <td><span class="status-pill <?php echo (int)$category['is_active'] === 1 ? 'pill-green' : 'pill-red'; ?>"><?php echo (int)$category['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
            <td class="category-actions-column">
              <?php if ($canUpdate): ?>
                <button type="button" class="btn-sm" data-category="<?php echo e(json_encode($category)); ?>" onclick="openCategoryModal(JSON.parse(this.dataset.category))">Edit</button>
                <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" class="inline-category-form" onsubmit="return confirm('Delete this product category?');">
                  <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                  <button type="submit" class="btn-sm delete-category">Delete</button>
                </form>
              <?php else: ?><span class="view-only">View only</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($canCreate || $canUpdate): ?>
<div class="modal-overlay" id="categoryModal" aria-hidden="true">
  <div class="modal-content-card category-modal-card" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
    <div class="modal-header">
      <div><div class="modal-title" id="categoryModalTitle">Add Product Category</div><p id="categoryModalSubtitle">Create a category for organizing products.</p></div>
      <button type="button" class="modal-close-btn" onclick="closeCategoryModal()" aria-label="Close">&times;</button>
    </div>
    <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" id="categoryForm">
      <div class="modal-body category-form">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" id="category_action" value="create">
        <input type="hidden" name="category_id" id="category_id">
        <label>Category Name <span>*</span><input name="name" id="category_name" maxlength="150" placeholder="e.g. Beverages" required></label>
        <label>Description <small>Optional</small><textarea name="description" id="category_description" maxlength="500" rows="4" placeholder="Briefly describe the products in this category"></textarea></label>
        <label id="category_status_field">Status <select name="is_active" id="category_status"><option value="1">Active</option><option value="0">Inactive</option></select><small>Categories with active products cannot be made inactive.</small></label>
      </div>
      <div class="modal-footer"><button type="button" class="btn-sm" onclick="closeCategoryModal()">Cancel</button><button type="submit" class="btn-primary" id="categorySubmit">Create Category</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
.categories-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}.categories-page-head h1{margin:0;color:var(--text);font-size:22px}.categories-page-head p,.category-card-head p,.modal-header p{margin:5px 0 0;color:var(--text3);font-size:11px}.categories-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px}.categories-head-actions a{text-decoration:none}.category-summary{display:grid;grid-template-columns:repeat(3,minmax(150px,210px));gap:10px;margin-bottom:14px}.category-summary .card{padding:14px 16px;box-shadow:none}.category-summary span{display:block;color:var(--text3);font-size:9px;text-transform:uppercase;letter-spacing:.04em}.category-summary strong{display:block;margin-top:7px;color:var(--text);font-size:19px}.category-table-card{overflow:hidden;box-shadow:none}.category-card-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px;border-bottom:1px solid var(--table-border)}.category-table-wrap{overflow-x:auto}.category-table{min-width:760px}.category-table td{vertical-align:middle}.category-description{max-width:420px;color:var(--text2);line-height:1.45}.category-description span{color:var(--text3);font-style:italic}.product-count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:32px;padding:5px 8px;border-radius:999px;background:var(--bg);color:var(--text2);font-size:10px;font-weight:700}.category-actions-column{text-align:right!important;white-space:nowrap}.inline-category-form{display:inline}.delete-category{margin-left:4px;color:var(--red)!important;background:var(--bg)!important}.view-only{color:var(--text3);font-size:9px}.category-empty{text-align:center!important;color:var(--text3)!important;padding:38px!important}.category-modal-card{width:min(560px,calc(100vw - 28px))}.category-form{display:grid;gap:15px}.category-form>input[type=hidden]{display:none}.category-form label{display:flex;flex-direction:column;gap:7px;color:var(--text2);font-size:10px;font-weight:650}.category-form label>span{color:var(--red)}.category-form label>small{color:var(--text3);font-size:9px;font-weight:400}.category-form input,.category-form textarea,.category-form select{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit;outline:none}.category-form input,.category-form select{min-height:40px}.category-form textarea{resize:vertical;min-height:96px}.category-form input:focus,.category-form textarea:focus,.category-form select:focus{border-color:var(--border-hover)}@media(max-width:720px){.categories-page-head{align-items:stretch;flex-direction:column}.categories-head-actions{justify-content:flex-start}.category-summary{grid-template-columns:repeat(3,1fr)}}@media(max-width:520px){.category-summary{grid-template-columns:1fr}.categories-head-actions{align-items:stretch;flex-direction:column}}
</style>

<script>
const categoryModal = document.getElementById('categoryModal');
function openCategoryModal(category = null) {
  if (!categoryModal) return;
  document.getElementById('categoryForm').reset();
  document.getElementById('category_action').value = category ? 'update' : 'create';
  document.getElementById('category_id').value = category ? category.id : '';
  document.getElementById('category_name').value = category ? category.name : '';
  document.getElementById('category_description').value = category ? (category.description || '') : '';
  document.getElementById('category_status').value = category ? category.is_active : '1';
  document.getElementById('category_status_field').style.display = category ? 'flex' : 'none';
  document.getElementById('categoryModalTitle').textContent = category ? 'Edit Product Category' : 'Add Product Category';
  document.getElementById('categoryModalSubtitle').textContent = category ? 'Update this category and its availability.' : 'Create a category for organizing products.';
  document.getElementById('categorySubmit').textContent = category ? 'Save Changes' : 'Create Category';
  categoryModal.style.display = 'flex';
  categoryModal.setAttribute('aria-hidden', 'false');
  setTimeout(() => document.getElementById('category_name').focus(), 0);
}
function closeCategoryModal() { if (!categoryModal) return; categoryModal.style.display = 'none'; categoryModal.setAttribute('aria-hidden', 'true'); }
categoryModal?.addEventListener('click', event => { if (event.target === categoryModal) closeCategoryModal(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeCategoryModal(); });
document.getElementById('categoryForm')?.addEventListener('submit', function () { const button=document.getElementById('categorySubmit'); button.disabled=true; button.textContent='Saving...'; });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
