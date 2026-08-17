<?php
$page_title = 'Create Sale';
$extra_css = ['sale-create.css'];
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['create']);

$config = getBusinessInventoryConfig($conn, $businessId);
$localNow = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
$csrfToken = generateCsrfToken();
$saleIdempotencyKey = createIdempotencyToken();
$generatedSaleNumber = generateUniqueSaleNumber($conn, $businessId);
if (!isset($_SESSION['pending_sale_numbers']) || !is_array($_SESSION['pending_sale_numbers'])) $_SESSION['pending_sale_numbers'] = [];
foreach ($_SESSION['pending_sale_numbers'] as $pendingKey => $pendingSale) {
    if (!is_array($pendingSale) || (int)($pendingSale['created_at'] ?? 0) < time() - 86400) unset($_SESSION['pending_sale_numbers'][$pendingKey]);
}
$_SESSION['pending_sale_numbers'][$saleIdempotencyKey] = ['number'=>$generatedSaleNumber,'created_at'=>time()];
$role = getPreviewRole();
$roleQuery = $role ? '?role=' . rawurlencode($role) : '';

$customers = [];
$customerStmt = mysqli_prepare($conn, 'SELECT id,name FROM customers WHERE business_id=? AND is_active=1 ORDER BY name');
mysqli_stmt_bind_param($customerStmt, 'i', $businessId);
mysqli_stmt_execute($customerStmt);
$customerResult = mysqli_stmt_get_result($customerStmt);
while ($row = mysqli_fetch_assoc($customerResult)) $customers[] = $row;

$locations = [];
$locationStmt = mysqli_prepare($conn, 'SELECT id,name,code FROM business_locations WHERE business_id=? AND is_active=1 ORDER BY name');
mysqli_stmt_bind_param($locationStmt, 'i', $businessId);
mysqli_stmt_execute($locationStmt);
$locationResult = mysqli_stmt_get_result($locationStmt);
while ($row = mysqli_fetch_assoc($locationResult)) $locations[] = $row;

$products = [];
$productStmt = mysqli_prepare($conn, 'SELECT p.id,p.name,p.sku,p.uom_id,p.uom,p.sale_price,p.package_uom_id,p.units_per_package,p.package_sale_price,pu.code package_uom FROM products p LEFT JOIN units_of_measure pu ON pu.id=p.package_uom_id WHERE p.business_id=? AND p.is_active=1 ORDER BY p.name');
mysqli_stmt_bind_param($productStmt, 'i', $businessId);
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);
while ($row = mysqli_fetch_assoc($productResult)) $products[] = $row;

$stock = [];
$stockStmt = mysqli_prepare($conn, 'SELECT product_id,location_id,available_quantity FROM inventory_balances WHERE business_id=?');
mysqli_stmt_bind_param($stockStmt, 'i', $businessId);
mysqli_stmt_execute($stockStmt);
$stockResult = mysqli_stmt_get_result($stockStmt);
while ($row = mysqli_fetch_assoc($stockResult)) $stock[] = $row;

$businessStmt = mysqli_prepare($conn, 'SELECT currency_code FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
mysqli_stmt_execute($businessStmt);
$currency = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt))['currency_code'] ?? 'RWF';

$activeTax = getActiveBusinessTax($conn, $businessId);
$activeTaxLabel = 'No tax';
if ($activeTax) {
    $activeTaxLabel = (string)$activeTax['name'];
    if ($activeTax['tax_type'] === 'PERCENTAGE') {
        $activeTaxLabel .= ' (' . rtrim(rtrim(number_format((float)$activeTax['tax_value'], 4, '.', ''), '0'), '.') . '%)';
    } else {
        $activeTaxLabel .= ' (fixed)';
    }
}

$canOverridePrice = hasPermission($conn, $membershipId, $businessId, 'products.update');
$canRegisterCustomer = !isRolePreviewActive()
    && hasPermission($conn, $membershipId, $businessId, 'customers.view')
    && hasPermission($conn, $membershipId, $businessId, 'customers.create');
$selectedCustomerId = (int)($_GET['customer_id'] ?? 0);
$resumeDraft = ($_GET['resume_sale'] ?? '') === '1';
$resourcesReady = $locations && $products;
?>

<main class="sale-create-page">
  <nav class="sale-create-breadcrumb" aria-label="Breadcrumb"><a href="index.php<?php echo e($roleQuery); ?>">Sales</a><span>/</span><span>Create sale</span></nav>

  <header class="sale-create-header">
    <div class="sale-create-heading">
      <span class="sale-create-kicker">New transaction</span>
      <h1>Create Sale</h1>
      <p>Build the invoice, confirm payment, and complete the sale. Stock batches are selected automatically.</p>
    </div>
    <div class="sale-create-header-meta">
      <span>Local time</span>
      <strong><?php echo e($localNow->format('d M Y · H:i')); ?></strong>
    </div>
  </header>

  <?php if (!$resourcesReady): ?>
    <div class="sale-create-warning" role="alert">Register at least one active product and stock location before creating a sale.</div>
  <?php endif; ?>

  <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" id="createSaleForm" class="sale-create-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="idempotency_key" value="<?php echo e($saleIdempotencyKey); ?>">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="return_to" value="sale_create">

    <div class="sale-create-layout">
      <div class="sale-create-main">
        <section class="sale-create-card">
          <div class="sale-create-section-head">
            <div><span>1</span><div><h2>Sale information</h2><p>Customer, reference, time, and the location supplying this sale.</p></div></div>
            <small>Required fields are marked *</small>
          </div>
          <div class="sale-information-grid">
            <label class="system-sale-number"><span>Sale number</span><input type="text" name="sale_number" value="<?php echo e($generatedSaleNumber); ?>" readonly aria-readonly="true"><small>Generated automatically and cannot be changed.</small></label>
            <label><span>Sold at *</span><input type="datetime-local" name="sold_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>" required></label>
            <div class="sale-customer-field"><div class="sale-field-label-row"><label for="saleCustomer">Customer</label><?php if ($canRegisterCustomer): ?><button type="button" id="registerCustomerFromSale" aria-haspopup="dialog" aria-controls="saleCustomerModal">+ Register a new customer</button><?php endif; ?></div><select name="customer_id" id="saleCustomer"><option value="">Walk-in customer</option><?php foreach ($customers as $customer): ?><option value="<?php echo (int)$customer['id']; ?>" <?php echo $selectedCustomerId === (int)$customer['id'] ? 'selected' : ''; ?>><?php echo e($customer['name']); ?></option><?php endforeach; ?></select><small class="sale-customer-feedback" id="saleCustomerFeedback" role="status" aria-live="polite"></small></div>
            <label><span>Stock location *</span><select name="location_id" id="saleLocation" required><option value="">Choose location</option><?php foreach ($locations as $location): ?><option value="<?php echo (int)$location['id']; ?>"><?php echo e($location['name'] . ' · ' . $location['code']); ?></option><?php endforeach; ?></select></label>
            <label class="sale-information-notes"><span>Notes <small>Optional</small></span><textarea name="notes" rows="2" maxlength="1000" placeholder="Add an internal note or customer instruction"></textarea></label>
          </div>
        </section>

        <section class="sale-create-card sale-items-card">
          <div class="sale-create-section-head sale-items-head">
            <div><span>2</span><div><h2>Invoice items</h2><p>Select products and selling units. Available stock updates when the location changes.</p></div></div>
            <button type="button" class="sale-secondary-button" id="addSaleItem">+ Add product</button>
          </div>
          <div class="sale-items-columns" aria-hidden="true"><span>Product</span><span>Sale unit</span><span>Quantity</span><span>Unit price</span><span>Line total</span><span></span></div>
          <div id="saleItems" class="sale-items-list"></div>
          <div class="sale-items-guidance"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 7v5c0 5 3.8 8.5 9 9 5.2-.5 9-4 9-9V7l-9-4Zm-3 9 2 2 4-4"/></svg><span>Batch allocation is automatic. The system uses non-expired stock and preserves purchase traceability.</span></div>
        </section>
      </div>

      <aside class="sale-create-sidebar">
        <section class="sale-create-card sale-summary-card">
          <div class="sale-create-section-head"><div><span>3</span><div><h2>Payment summary</h2><p>Review the invoice before completing it.</p></div></div></div>
          <dl class="sale-total-list">
            <div><dt>Subtotal</dt><dd id="saleSubtotal">0.00 <?php echo e($currency); ?></dd></div>
            <div><dt><?php echo e($activeTaxLabel); ?></dt><dd id="saleTax">0.00 <?php echo e($currency); ?></dd></div>
            <div class="sale-grand-total"><dt>Total payable</dt><dd id="saleTotal">0.00 <?php echo e($currency); ?></dd></div>
          </dl>
          <?php if (!$activeTax): ?><p class="sale-tax-message">No active tax is registered, so no tax will be added.</p><?php endif; ?>
          <div class="sale-payment-fields">
            <label><span>Payment method</span><select name="payment_method" id="paymentMethod"><option value="CASH">Cash</option><option value="CARD">Card</option><option value="BANK_TRANSFER">Bank transfer</option><option value="MOBILE_MONEY">Mobile money</option><option value="CHEQUE">Cheque</option><option value="CREDIT">Credit</option><option value="OTHER">Other</option></select></label>
            <label><span>Amount paid</span><input type="number" min="0" step="0.0001" name="amount_paid" id="amountPaid" placeholder="0.00"><small id="paymentHelp">Defaults to the full sale total.</small></label>
            <label><span>Payment reference <small>Optional</small></span><input type="text" name="payment_reference" maxlength="120" placeholder="Receipt or transaction number"></label>
            <label><span>Paid at</span><input type="datetime-local" name="paid_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>"></label>
          </div>
        </section>

        <div class="sale-draft-status" id="saleDraftStatus" aria-live="polite"><span></span><strong id="saleDraftText">Draft saves automatically</strong></div>
      </aside>
    </div>

    <footer class="sale-create-actions">
      <div><strong>Ready to complete the sale?</strong><span>Stock and payment records are posted only after successful validation.</span></div>
      <div class="sale-create-action-buttons"><button type="button" class="sale-secondary-button" id="clearSaleDraft">Start over</button><a class="sale-secondary-button" href="index.php<?php echo e($roleQuery); ?>">Cancel</a><button type="submit" class="sale-primary-button" id="completeSale" <?php echo $resourcesReady ? '' : 'disabled'; ?>>Complete sale</button></div>
    </footer>
  </form>
</main>

<?php if ($canRegisterCustomer): ?>
<div class="sale-customer-modal" id="saleCustomerModal" hidden>
  <section class="sale-customer-dialog" role="dialog" aria-modal="true" aria-labelledby="saleCustomerModalTitle" aria-describedby="saleCustomerModalDescription">
    <header class="sale-customer-dialog-header">
      <div class="sale-customer-dialog-heading">
        <span class="sale-customer-dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M15 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 2 18.5V20M8.5 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6"/></svg></span>
        <div><span>Customer directory</span><h2 id="saleCustomerModalTitle">Register a new customer</h2><p id="saleCustomerModalDescription">Add the customer’s essential details without leaving this sale.</p></div>
      </div>
      <button type="button" class="sale-customer-modal-close" data-close-customer-modal aria-label="Close customer registration">&times;</button>
    </header>

    <form action="../customer/backend.php<?php echo e($roleQuery); ?>" method="POST" id="saleCustomerForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="return_to" value="sale_popup">

      <div class="sale-customer-dialog-body">
        <div class="sale-customer-form-status" id="saleCustomerFormStatus" role="alert" hidden></div>
        <div class="sale-customer-form-grid">
          <label class="sale-customer-name-field"><span>Customer name <b>*</b></span><input type="text" name="name" id="saleCustomerName" maxlength="200" autocomplete="name" placeholder="e.g. John Doe or Gold Buyers" required><small>Use the person’s name or registered company name.</small></label>
          <label><span>Phone number</span><input type="tel" name="phone" maxlength="32" autocomplete="tel" placeholder="e.g. +250 788 777 777"></label>
          <label><span>Email address</span><input type="email" name="email" maxlength="254" autocomplete="email" placeholder="e.g. customer@company.com"></label>
          <label><span>TIN / Tax number</span><input type="text" name="tax_number" maxlength="100" placeholder="e.g. 109837264"></label>
          <label class="sale-customer-address-field"><span>Address</span><textarea name="address" rows="3" maxlength="500" autocomplete="street-address" placeholder="Street, district, city, or delivery address"></textarea></label>
        </div>
        <div class="sale-customer-form-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20ZM12 10v6M12 7h.01"/></svg><span>The customer will be saved as active and selected automatically for this sale.</span></div>
      </div>

      <footer class="sale-customer-dialog-footer">
        <button type="button" class="sale-secondary-button" data-close-customer-modal>Cancel</button>
        <button type="submit" class="sale-primary-button" id="saveSaleCustomer">Register customer</button>
      </footer>
    </form>
  </section>
</div>
<?php endif; ?>

<script>
const saleProducts=<?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const saleStock=<?php echo json_encode($stock, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const saleTax=<?php echo json_encode($activeTax, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const saleCurrency=<?php echo json_encode($currency); ?>;
const canOverrideSalePrice=<?php echo $canOverridePrice ? 'true' : 'false'; ?>;
const resumeSaleDraft=<?php echo $resumeDraft ? 'true' : 'false'; ?>;
const returnedCustomerId=<?php echo $selectedCustomerId; ?>;
const saleDraftKey=<?php echo json_encode('business-management:sale-draft:' . $businessId . ':' . (int)($_SESSION['user_id'] ?? 0)); ?>;
const saleForm=document.getElementById('createSaleForm');
const saleItems=document.getElementById('saleItems');
const saleCustomerSelect=document.getElementById('saleCustomer');
const saleCustomerModal=document.getElementById('saleCustomerModal');
const saleCustomerForm=document.getElementById('saleCustomerForm');
let saleItemSequence=0;
let saleDraftTimer=null;
let paymentAmountEdited=false;
let saleCustomerModalTrigger=null;

function escapeSaleText(value){return String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));}
function formatSaleNumber(value){const number=Math.round((parseFloat(value)||0)*10000)/10000;return Math.abs(number-Math.round(number))<.00005?String(Math.round(number)):number.toFixed(4);}
function saleProductById(id){return saleProducts.find(product=>Number(product.id)===Number(id));}
function saleAvailable(productId,locationId){return parseFloat(saleStock.find(row=>Number(row.product_id)===Number(productId)&&Number(row.location_id)===Number(locationId))?.available_quantity||0);}
function formatSaleStock(value,product){const base=Math.round((parseFloat(value)||0)*10000)/10000;if(!product)return formatSaleNumber(base);const factor=parseFloat(product.units_per_package||0);if(!product.package_uom_id||factor<=0)return `${formatSaleNumber(base)} ${product.uom}`;const packages=Math.floor((Math.abs(base)+.0000001)/factor);const remainder=Math.round((Math.abs(base)-packages*factor)*10000)/10000;const sign=base<0?'-':'';const parts=[];if(packages>0)parts.push(`${sign}${packages} ${product.package_uom}`);if(remainder>.00005||!parts.length)parts.push(`${parts.length?'':sign}${formatSaleNumber(remainder)} ${product.uom}`);return parts.join(base<0?' - ':' + ');}

function addSaleItem(itemData=null){
  const index=saleItemSequence++;
  const row=document.createElement('div');
  row.className='sale-item-row';
  row.id=`sale-item-${index}`;
  const options=saleProducts.map(product=>`<option value="${Number(product.id)}">${escapeSaleText(product.name)} (${escapeSaleText(product.sku)})</option>`).join('');
  row.innerHTML=`<label class="sale-product-control"><span>Product</span><select class="sale-product" name="product_ids[]" required><option value="">Choose product</option>${options}</select><small class="sale-stock-hint">Choose a location and product</small></label><label><span>Sale unit</span><select class="sale-uom" name="sale_uom_ids[]" required><option value="">Select product</option></select></label><label><span>Quantity</span><input class="sale-quantity" type="number" name="quantities[]" min=".0001" step=".0001" placeholder="0" required></label><label><span>Unit price</span><input class="sale-unit-price" type="number" name="unit_prices[]" min="0" step=".0001" placeholder="0.00" required ${canOverrideSalePrice?'':'readonly'}></label><div class="sale-line-total"><span>Line total</span><strong>0.00 ${escapeSaleText(saleCurrency)}</strong></div><button type="button" class="sale-remove-item" aria-label="Remove product">&times;</button>`;
  saleItems.appendChild(row);
  row.querySelector('.sale-product').addEventListener('change',()=>syncSaleProduct(row));
  row.querySelector('.sale-uom').addEventListener('change',()=>syncSaleUnit(row));
  row.querySelector('.sale-quantity').addEventListener('input',()=>recalculateSale());
  row.querySelector('.sale-unit-price').addEventListener('input',()=>recalculateSale());
  row.querySelector('.sale-remove-item').addEventListener('click',()=>removeSaleItem(row));
  if(itemData){row.querySelector('.sale-product').value=String(itemData.product_id||'');syncSaleProduct(row,itemData.sale_uom_id);row.querySelector('.sale-quantity').value=itemData.quantity||'';row.querySelector('.sale-unit-price').value=itemData.unit_price||'';}
  recalculateSale();
}

function removeSaleItem(row){if(saleItems.children.length===1){row.querySelectorAll('select,input').forEach(input=>input.value='');row.querySelector('.sale-uom').innerHTML='<option value="">Select product</option>';row.querySelector('.sale-stock-hint').textContent='Choose a location and product';}else row.remove();recalculateSale();}
function syncSaleProduct(row,preferredUom=null){const product=saleProductById(row.querySelector('.sale-product').value);const uom=row.querySelector('.sale-uom');uom.innerHTML='<option value="">Select unit</option>';if(product){uom.insertAdjacentHTML('beforeend',`<option value="${product.uom_id}">${escapeSaleText(product.uom)}</option>`);if(product.package_uom_id)uom.insertAdjacentHTML('beforeend',`<option value="${product.package_uom_id}">${escapeSaleText(product.package_uom)}</option>`);uom.value=String(preferredUom||product.uom_id);}syncSaleUnit(row);refreshSaleStock(row);}
function syncSaleUnit(row){const product=saleProductById(row.querySelector('.sale-product').value);const selected=Number(row.querySelector('.sale-uom').value);const price=row.querySelector('.sale-unit-price');if(!product){price.value='';recalculateSale();return;}const packageSelected=product.package_uom_id&&selected===Number(product.package_uom_id);const suggested=packageSelected?(product.package_sale_price!==null?parseFloat(product.package_sale_price):parseFloat(product.sale_price||0)*parseFloat(product.units_per_package||0)):parseFloat(product.sale_price||0);price.value=suggested.toFixed(4);recalculateSale();}
function refreshSaleStock(row){const product=saleProductById(row.querySelector('.sale-product').value);const locationId=Number(document.getElementById('saleLocation').value);const hint=row.querySelector('.sale-stock-hint');if(!product){hint.textContent='Choose a product';hint.classList.remove('is-low');return;}if(!locationId){hint.textContent='Choose a stock location to see availability';hint.classList.remove('is-low');return;}const available=saleAvailable(product.id,locationId);hint.textContent=`Available: ${formatSaleStock(available,product)}`;hint.classList.toggle('is-low',available<=0);}

function recalculateSale(){let subtotal=0;saleItems.querySelectorAll('.sale-item-row').forEach(row=>{const quantity=parseFloat(row.querySelector('.sale-quantity').value)||0;const price=parseFloat(row.querySelector('.sale-unit-price').value)||0;const total=quantity*price;subtotal+=total;row.querySelector('.sale-line-total strong').textContent=`${total.toFixed(2)} ${saleCurrency}`;});let tax=0;if(saleTax)tax=saleTax.tax_type==='PERCENTAGE'?subtotal*(parseFloat(saleTax.tax_value)/100):parseFloat(saleTax.tax_value);const total=subtotal+tax;document.getElementById('saleSubtotal').textContent=`${subtotal.toFixed(2)} ${saleCurrency}`;document.getElementById('saleTax').textContent=`${tax.toFixed(2)} ${saleCurrency}`;document.getElementById('saleTotal').textContent=`${total.toFixed(2)} ${saleCurrency}`;saleForm.dataset.total=total.toFixed(4);if(!paymentAmountEdited&&document.getElementById('paymentMethod').value!=='CREDIT')document.getElementById('amountPaid').value=total.toFixed(4);}
function syncPaymentMethod(){const method=document.getElementById('paymentMethod').value;const amount=document.getElementById('amountPaid');const help=document.getElementById('paymentHelp');if(method==='CREDIT'){amount.value='0.0000';amount.readOnly=true;help.textContent='Credit sales are recorded with no immediate payment.';}else{amount.readOnly=false;if(!paymentAmountEdited)amount.value=saleForm.dataset.total||'0.0000';help.textContent='You can record a full or partial payment.';}}

function collectSaleDraft(){const value=name=>saleForm.elements[name]?.value||'';return{sold_at:value('sold_at'),customer_id:value('customer_id'),location_id:value('location_id'),notes:value('notes'),payment_method:value('payment_method'),amount_paid:value('amount_paid'),payment_reference:value('payment_reference'),paid_at:value('paid_at'),items:Array.from(saleItems.querySelectorAll('.sale-item-row')).map(row=>({product_id:row.querySelector('.sale-product').value,sale_uom_id:row.querySelector('.sale-uom').value,quantity:row.querySelector('.sale-quantity').value,unit_price:row.querySelector('.sale-unit-price').value}))};}
function saveSaleDraft(){try{sessionStorage.setItem(saleDraftKey,JSON.stringify(collectSaleDraft()));document.getElementById('saleDraftStatus').classList.add('is-saved');document.getElementById('saleDraftText').textContent='Draft saved in this browser';}catch(error){}}
function restoreSaleDraft(){let draft=null;try{draft=JSON.parse(sessionStorage.getItem(saleDraftKey)||'null');}catch(error){}if(!draft)return false;['sold_at','customer_id','location_id','notes','payment_method','amount_paid','payment_reference','paid_at'].forEach(name=>{if(saleForm.elements[name]&&draft[name]!==undefined)saleForm.elements[name].value=draft[name];});saleItems.replaceChildren();(Array.isArray(draft.items)&&draft.items.length?draft.items:[null]).forEach(addSaleItem);if(returnedCustomerId&&saleForm.elements.customer_id.querySelector(`option[value="${returnedCustomerId}"]`))saleForm.elements.customer_id.value=String(returnedCustomerId);paymentAmountEdited=String(draft.amount_paid||'')!=='';syncPaymentMethod();recalculateSale();return true;}

function openSaleCustomerModal(){if(!saleCustomerModal)return;saleCustomerModalTrigger=document.activeElement;saleCustomerModal.hidden=false;document.body.classList.add('sale-customer-modal-open');const status=document.getElementById('saleCustomerFormStatus');status.hidden=true;status.textContent='';requestAnimationFrame(()=>document.getElementById('saleCustomerName')?.focus());}
function closeSaleCustomerModal(){if(!saleCustomerModal)return;saleCustomerModal.hidden=true;document.body.classList.remove('sale-customer-modal-open');saleCustomerModalTrigger?.focus();}

saleCustomerForm?.addEventListener('submit',async event=>{
  event.preventDefault();
  if(!saleCustomerForm.checkValidity()){saleCustomerForm.reportValidity();return;}
  const button=document.getElementById('saveSaleCustomer');
  const status=document.getElementById('saleCustomerFormStatus');
  button.disabled=true;
  button.textContent='Registering customer…';
  status.hidden=true;
  status.textContent='';
  try{
    const customerEndpoint=new URL(saleCustomerForm.getAttribute('action'),window.location.href);
    const response=await fetch(customerEndpoint,{method:'POST',body:new FormData(saleCustomerForm),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
    let result=null;
    try{result=await response.json();}catch(error){}
    if(!response.ok||!result?.success)throw new Error(result?.message||'Customer registration could not be completed. Please try again.');
    const customerId=String(result.customer.id);
    let option=Array.from(saleCustomerSelect.options).find(existing=>existing.value===customerId);
    if(!option){option=new Option(result.customer.name,customerId);saleCustomerSelect.add(option);}
    option.textContent=result.customer.name;
    saleCustomerSelect.value=customerId;
    saleCustomerSelect.dispatchEvent(new Event('change',{bubbles:true}));
    document.getElementById('saleCustomerFeedback').textContent=`${result.customer.name} registered and selected`;
    saveSaleDraft();
    saleCustomerForm.reset();
    closeSaleCustomerModal();
    saleCustomerSelect.focus();
  }catch(error){
    status.textContent=error.message;
    status.hidden=false;
  }finally{
    button.disabled=false;
    button.textContent='Register customer';
  }
});

document.getElementById('addSaleItem').addEventListener('click',()=>addSaleItem());
document.getElementById('saleLocation').addEventListener('change',()=>saleItems.querySelectorAll('.sale-item-row').forEach(refreshSaleStock));
document.getElementById('paymentMethod').addEventListener('change',()=>{paymentAmountEdited=false;syncPaymentMethod();recalculateSale();});
document.getElementById('amountPaid').addEventListener('input',()=>{paymentAmountEdited=true;});
document.getElementById('registerCustomerFromSale')?.addEventListener('click',openSaleCustomerModal);
saleCustomerModal?.querySelectorAll('[data-close-customer-modal]').forEach(button=>button.addEventListener('click',closeSaleCustomerModal));
saleCustomerModal?.addEventListener('click',event=>{if(event.target===saleCustomerModal)closeSaleCustomerModal();});
document.addEventListener('keydown',event=>{
  if(!saleCustomerModal||saleCustomerModal.hidden)return;
  if(event.key==='Escape'){closeSaleCustomerModal();return;}
  if(event.key!=='Tab')return;
  const focusable=Array.from(saleCustomerModal.querySelectorAll('button:not(:disabled),input:not(:disabled),textarea:not(:disabled),select:not(:disabled),a[href]'));
  if(!focusable.length)return;
  const first=focusable[0];
  const last=focusable[focusable.length-1];
  if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
  else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
});
document.getElementById('clearSaleDraft').addEventListener('click',()=>{if(!confirm('Clear this sale and start again?'))return;try{sessionStorage.removeItem(saleDraftKey);}catch(error){}window.location.href=<?php echo json_encode('create.php' . $roleQuery); ?>;});
saleForm.addEventListener('input',()=>{clearTimeout(saleDraftTimer);saleDraftTimer=setTimeout(saveSaleDraft,180);});
saleForm.addEventListener('change',saveSaleDraft);
saleForm.addEventListener('submit',event=>{if(!saleForm.checkValidity()){event.preventDefault();saleForm.reportValidity();return;}saveSaleDraft();const button=document.getElementById('completeSale');button.disabled=true;button.textContent='Completing sale…';});

if(!(resumeSaleDraft&&restoreSaleDraft()))addSaleItem();
if(returnedCustomerId&&saleForm.elements.customer_id.querySelector(`option[value="${returnedCustomerId}"]`))saleForm.elements.customer_id.value=String(returnedCustomerId);
syncPaymentMethod();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
