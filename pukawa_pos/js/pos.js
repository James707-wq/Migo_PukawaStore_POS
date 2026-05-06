/* ============================================================
   Pukawa Store POS — pos.js
   Cart management, product search, barcode scanning, checkout
============================================================ */

// ── State ─────────────────────────────────────────────────
let cart    = [];         // { product_id, barcode, name, price, qty }
let scanner = null;
let allProducts = [];

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadProducts();

  // Search
  const searchEl = document.getElementById('posSearch');
  searchEl.addEventListener('input', debounce(() => renderGrid(), 220));
  searchEl.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); tryAddByBarcode(searchEl.value.trim()); }
  });

  // Category filter
  document.getElementById('catFilter').addEventListener('change', () => renderGrid());

  // Scanner modal events
  const scanModal = document.getElementById('scannerModal');
  scanModal.addEventListener('shown.bs.modal',  startScanner);
  scanModal.addEventListener('hidden.bs.modal', stopScanner);

  // Sync desktop discount input to mobile
  document.getElementById('discountInput').addEventListener('change', () => {
    const val = document.getElementById('discountInput').value;
    const offcanvas = document.getElementById('discountInputOffcanvas');
    if (offcanvas) offcanvas.value = val;
    updateTotals();
  });

  // Sync desktop payment input to mobile
  document.getElementById('paymentInput').addEventListener('change', () => {
    const val = document.getElementById('paymentInput').value;
    const offcanvas = document.getElementById('paymentInputOffcanvas');
    if (offcanvas) offcanvas.value = val;
    updateChange();
  });

  // Sync desktop payment method to mobile
  document.getElementById('paymentMethod').addEventListener('change', () => {
    const val = document.getElementById('paymentMethod').value;
    const offcanvas = document.getElementById('paymentMethodOffcanvas');
    if (offcanvas) offcanvas.value = val;
  });

  // Sync mobile inputs back to desktop (if they exist)
  const discountOffcanvas = document.getElementById('discountInputOffcanvas');
  if (discountOffcanvas) {
    discountOffcanvas.addEventListener('change', () => {
      document.getElementById('discountInput').value = discountOffcanvas.value;
      updateTotals();
    });
  }

  const paymentOffcanvas = document.getElementById('paymentInputOffcanvas');
  if (paymentOffcanvas) {
    paymentOffcanvas.addEventListener('change', () => {
      document.getElementById('paymentInput').value = paymentOffcanvas.value;
      updateChange();
    });
  }

  const methodOffcanvas = document.getElementById('paymentMethodOffcanvas');
  if (methodOffcanvas) {
    methodOffcanvas.addEventListener('change', () => {
      document.getElementById('paymentMethod').value = methodOffcanvas.value;
    });
  }
});

// ── Load products from API ────────────────────────────────
async function loadProducts() {
  try {
    const res = await fetch(BASE_URL + 'api/products.php?action=list');
    const data = await res.json();
    if (data.success) { allProducts = data.products; renderGrid(); }
  } catch (e) { console.error(e); }
}

// ── Render product grid ───────────────────────────────────
function renderGrid() {
  const search  = document.getElementById('posSearch').value.toLowerCase();
  const catId   = document.getElementById('catFilter').value;
  const grid    = document.getElementById('productGrid');

  let filtered = allProducts.filter(p => {
    const matchSearch = !search ||
          p.product_name.toLowerCase().includes(search) ||
          (p.barcode && p.barcode.includes(search));
    const matchCat = !catId || String(p.category_id) === catId;
    return matchSearch && matchCat;
  });

  if (!filtered.length) {
    grid.innerHTML = `<div style="padding:40px;text-align:center;color:#aaa;grid-column:1/-1">
      <i class="bi bi-search" style="font-size:36px;opacity:.3;display:block;margin-bottom:10px"></i>
      <span>No products found</span>
    </div>`;
    return;
  }

  // Create table HTML
  const tableRows = filtered.map(p => {
    const oos = p.stock_quantity <= 0;
    const displayStock = p.stock_quantity > 9999 ? '9999+' : p.stock_quantity;
    const stockClass = oos ? 'text-danger fw-bold' : 'text-success';
    
    return `<tr class="${oos ? 'table-secondary' : ''}">
      <td style="font-size:12px">${escHtml(p.barcode || '-')}</td>
      <td>${escHtml(p.product_name)}</td>
      <td style="font-size:13px">${escHtml(p.category_name)}</td>
      <td style="text-align:right;font-weight:bold">₱${fmt2(p.price)}</td>
      <td style="text-align:center;${stockClass}font-size:13px">${oos ? 'Out of Stock' : displayStock}</td>
      <td style="text-align:center">
        <button class="btn btn-sm ${oos ? 'btn-secondary disabled' : 'btn-brand'}" 
                onclick="addToCart(${p.product_id})" 
                ${oos ? 'disabled' : ''}>
          <i class="bi bi-plus-circle"></i>
        </button>
      </td>
    </tr>`;
  }).join('');

  grid.innerHTML = `
    <table class="table table-hover mb-0" style="font-size:14px">
      <thead class="table-light sticky-top">
        <tr>
          <th style="width:15%;font-size:12px">Barcode</th>
          <th style="width:35%">Product</th>
          <th style="width:18%">Category</th>
          <th style="width:12%;text-align:right">Price</th>
          <th style="width:12%;text-align:center">Stock</th>
          <th style="width:8%;text-align:center">Action</th>
        </tr>
      </thead>
      <tbody>
        ${tableRows}
      </tbody>
    </table>
  `;
}

// ── Add to cart ───────────────────────────────────────────
function addToCart(productId) {
  const p = allProducts.find(x => x.product_id == productId);
  if (!p) return;
  if (p.stock_quantity <= 0) { showToast('Out of stock!', 'warning'); return; }

  const existing = cart.find(x => x.product_id == productId);
  if (existing) {
    if (existing.qty >= p.stock_quantity) {
      showToast('Not enough stock!', 'warning'); return;
    }
    existing.qty++;
  } else {
    cart.push({
      product_id: p.product_id,
      barcode:    p.barcode,
      name:       p.product_name,
      price:      parseFloat(p.price),
      qty:        1,
      max_qty:    p.stock_quantity
    });
  }
  renderCart();
  showToast(`${p.product_name} added`, 'success');
}

function tryAddByBarcode(code) {
  if (!code) return;
  const p = allProducts.find(x => x.barcode === code);
  if (p) {
    addToCart(p.product_id);
    // Auto-clear and re-focus for faster scanning workflow
    const searchEl = document.getElementById('posSearch');
    searchEl.value = '';
    searchEl.focus();
    renderGrid();
  } else {
    // fetch from server in case product isn't loaded
    fetch(BASE_URL + `api/products.php?action=barcode&code=${encodeURIComponent(code)}`)
      .then(r=>r.json()).then(d=>{
        if (d.success && d.product) {
          if (!allProducts.find(x=>x.product_id==d.product.product_id))
            allProducts.push(d.product);
          addToCart(d.product.product_id);
          // Auto-clear and re-focus for faster scanning workflow
          const searchEl = document.getElementById('posSearch');
          searchEl.value = '';
          searchEl.focus();
          renderGrid();
        } else {
          showToast('Product not found: ' + code, 'warning');
          // Keep focus on search field even when product not found
          document.getElementById('posSearch').focus();
        }
      });
  }
}

// ── Render cart ───────────────────────────────────────────
function renderCart() {
  const el = document.getElementById('cartItems');
  const empty = document.getElementById('cartEmpty');
  const totalItems = cart.reduce((s,i)=>s+i.qty,0);

  if (!cart.length) {
    el.innerHTML = '';
    el.appendChild(buildEmpty());
    document.getElementById('cartCount').textContent = '0';
    // Mobile sync
    syncMobileCart();
    updateTotals(); return;
  }

  document.getElementById('cartCount').textContent = totalItems;
  // Mobile sync
  if (document.getElementById('cartCountMobile')) {
    document.getElementById('cartCountMobile').textContent = totalItems;
    document.getElementById('cartCountOffcanvas').textContent = totalItems;
  }
  
  el.innerHTML = cart.map((item,idx) => `
    <div class="cart-row">
      <div>
        <div class="cart-row-name">${escHtml(item.name)}</div>
        <div class="cart-row-price">₱${fmt2(item.price)} each</div>
      </div>
      <div class="qty-ctrl">
        <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
        <span class="qty-display">${item.qty}</span>
        <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
      </div>
      <div class="cart-row-sub">₱${fmt2(item.price * item.qty)}</div>
      <button class="cart-row-del" onclick="removeItem(${idx})">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>`).join('');

  // Sync to mobile offcanvas
  syncMobileCart();
  updateTotals();
}

function syncMobileCart() {
  // Sync cart items to offcanvas
  const offcanvasEl = document.getElementById('cartItemsOffcanvas');
  const desktopEl = document.getElementById('cartItems');
  if (offcanvasEl) {
    offcanvasEl.innerHTML = desktopEl.innerHTML;
  }
}

function syncMobileTotals() {
  // Sync totals between desktop and mobile
  const subtotal = document.getElementById('subtotalDisplay')?.textContent;
  const grand = document.getElementById('grandTotalDisplay')?.textContent;
  const change = document.getElementById('changeDisplay')?.textContent;
  
  if (document.getElementById('subtotalDisplayOffcanvas')) {
    document.getElementById('subtotalDisplayOffcanvas').textContent = subtotal;
    document.getElementById('grandTotalDisplayOffcanvas').textContent = grand;
    document.getElementById('changeDisplayOffcanvas').textContent = change;
  }
}

function buildEmpty() {
  const div = document.createElement('div');
  div.className = 'cart-empty';
  div.innerHTML = `<i class="bi bi-cart-x"></i><span>Cart is empty</span>
    <small>Scan a barcode or search a product</small>`;
  return div;
}

function changeQty(idx, delta) {
  const item = cart[idx];
  const newQty = item.qty + delta;
  if (newQty < 1) { removeItem(idx); return; }
  if (newQty > item.max_qty) { showToast('Not enough stock!', 'warning'); return; }
  item.qty = newQty;
  renderCart();
}

function removeItem(idx) {
  cart.splice(idx, 1);
  renderCart();
}

function clearCart() {
  if (!cart.length) return;
  confirmAction('Clear all items from cart?', () => { cart = []; renderCart(); });
}

function clearSearch() {
  const searchEl = document.getElementById('posSearch');
  searchEl.value = '';
  searchEl.focus();
  renderGrid();
}

// ── Totals ─────────────────────────────────────────────────
function updateTotals() {
  const subtotal = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value)||0);
  const grand    = Math.max(0, subtotal - discount);

  document.getElementById('subtotalDisplay').textContent  = fmtCurrency(subtotal);
  document.getElementById('grandTotalDisplay').textContent = fmtCurrency(grand);
  
  // Sync discount to mobile
  const discountOffcanvas = document.getElementById('discountInputOffcanvas');
  if (discountOffcanvas) {
    discountOffcanvas.value = document.getElementById('discountInput').value;
  }
  
  syncMobileTotals();
  updateChange();
}

function updateChange() {
  const grand   = parseGrand();
  const payment = parseFloat(document.getElementById('paymentInput').value)||0;
  const change  = payment - grand;

  document.getElementById('changeDisplay').textContent = fmtCurrency(Math.max(0, change));
  
  // Sync payment to mobile
  const paymentOffcanvas = document.getElementById('paymentInputOffcanvas');
  const methodOffcanvas = document.getElementById('paymentMethodOffcanvas');
  if (paymentOffcanvas) {
    paymentOffcanvas.value = document.getElementById('paymentInput').value;
    methodOffcanvas.value = document.getElementById('paymentMethod').value;
  }
  
  syncMobileTotals();
  
  const btn = document.getElementById('checkoutBtn');
  const btnOffcanvas = document.getElementById('checkoutBtnOffcanvas');
  const disabled = !cart.length || payment < grand;
  btn.disabled = disabled;
  if (btnOffcanvas) btnOffcanvas.disabled = disabled;
}

function parseGrand() {
  const subtotal = cart.reduce((s,i)=>s+i.price*i.qty,0);
  const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value)||0);
  return Math.max(0, subtotal - discount);
}

function addDenom(amt) {
  const el = document.getElementById('paymentInput');
  el.value = (parseFloat(el.value)||0) + amt;
  updateChange();
}
function clearPayment() {
  document.getElementById('paymentInput').value = '';
  updateChange();
}

// ── Checkout ───────────────────────────────────────────────
async function checkout() {
  // Sync mobile inputs to desktop if offcanvas is open
  const discountOffcanvas = document.getElementById('discountInputOffcanvas');
  const paymentOffcanvas = document.getElementById('paymentInputOffcanvas');
  const methodOffcanvas = document.getElementById('paymentMethodOffcanvas');
  
  if (discountOffcanvas && discountOffcanvas.offsetParent !== null) {
    document.getElementById('discountInput').value = discountOffcanvas.value;
  }
  if (paymentOffcanvas && paymentOffcanvas.offsetParent !== null) {
    document.getElementById('paymentInput').value = paymentOffcanvas.value;
  }
  if (methodOffcanvas && methodOffcanvas.offsetParent !== null) {
    document.getElementById('paymentMethod').value = methodOffcanvas.value;
  }

  const grand    = parseGrand();
  const payment  = parseFloat(document.getElementById('paymentInput').value)||0;
  const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value)||0);
  const method   = document.getElementById('paymentMethod').value;

  if (!cart.length)      { showToast('Cart is empty!', 'warning'); return; }
  if (payment < grand)   { showToast('Insufficient payment!', 'danger'); return; }

  document.getElementById('checkoutBtn').disabled = true;
  const offcanvasBtn = document.getElementById('checkoutBtnOffcanvas');
  if (offcanvasBtn) offcanvasBtn.disabled = true;
  
  document.getElementById('checkoutBtn').innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
  if (offcanvasBtn) {
    offcanvasBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
  }

  try {
    const res = await fetch(BASE_URL + 'api/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cart, grand, payment, discount,
        change: payment - grand,
        payment_method: method
      })
    });
    const data = await res.json();

    if (data.success) {
      showReceipt(data.transaction);
      // Refresh stock in local product list
      data.updatedStock && data.updatedStock.forEach(s => {
        const p = allProducts.find(x=>x.product_id==s.product_id);
        if (p) p.stock_quantity = s.stock_quantity;
      });
      renderGrid();
    } else {
      showToast(data.message || 'Checkout failed', 'danger');
    }
  } catch(e) {
    showToast('Server error: ' + e.message, 'danger');
  } finally {
    document.getElementById('checkoutBtn').disabled = false;
    if (offcanvasBtn) offcanvasBtn.disabled = false;
    
    document.getElementById('checkoutBtn').innerHTML =
      '<i class="bi bi-check-circle-fill me-2"></i>Checkout';
    if (offcanvasBtn) {
      offcanvasBtn.innerHTML =
        '<i class="bi bi-check-circle-fill me-2"></i>Checkout';
    }
  }
}

function showReceipt(txn) {
  const itemsHtml = txn.items.map(i=>
    `<tr>
      <td>${escHtml(i.name)}</td>
      <td style="text-align:center">${i.qty}</td>
      <td style="text-align:right">₱${fmt2(i.unit_price)}</td>
      <td style="text-align:right">₱${fmt2(i.subtotal)}</td>
    </tr>`
  ).join('');

  document.getElementById('receiptContent').innerHTML = `
    <div class="receipt-container" id="printableReceipt">
      <div class="receipt-header">
        <img src="img/logo.png" class="receipt-logo" alt="Pukawa Store"/>
        <div class="receipt-store-name">${escHtml(txn.store_name)}</div>
        <div>${escHtml(txn.store_address)}</div>
        <div>Tel: ${escHtml(txn.store_phone)}</div>
        <div>TIN: ${escHtml(txn.store_tin)}</div>
        <br>
        <div><strong>TXN #:</strong> ${escHtml(txn.transaction_no)}</div>
        <div>${escHtml(txn.date)}</div>
        <div>Cashier: ${escHtml(txn.cashier)}</div>
      </div>
      <table class="receipt-table">
        <thead>
          <tr>
            <th>Item</th>
            <th style="text-align:center">Qty</th>
            <th style="text-align:right">Price</th>
            <th style="text-align:right">Total</th>
          </tr>
        </thead>
        <tbody>${itemsHtml}</tbody>
      </table>
      <div class="receipt-totals">
        <div style="display:flex;justify-content:space-between"><span>Subtotal:</span><span>₱${fmt2(txn.subtotal)}</span></div>
        ${txn.discount>0?`<div style="display:flex;justify-content:space-between"><span>Discount:</span><span>- ₱${fmt2(txn.discount)}</span></div>`:''}
        <div style="display:flex;justify-content:space-between;font-weight:bold"><span>TOTAL:</span><span>₱${fmt2(txn.total)}</span></div>
        <div style="display:flex;justify-content:space-between"><span>Payment (${txn.method}):</span><span>₱${fmt2(txn.payment)}</span></div>
        <div style="display:flex;justify-content:space-between;font-weight:bold"><span>CHANGE:</span><span>₱${fmt2(txn.change)}</span></div>
      </div>
      <div class="receipt-footer">
        <p style="margin:3px 0">Thank you for shopping at<br><strong>${escHtml(txn.store_name)}</strong></p>
        <p style="margin:3px 0">Please come again!</p>
      </div>
    </div>`;

  new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function newTransaction() {
  cart = [];
  document.getElementById('paymentInput').value  = '';
  document.getElementById('discountInput').value = '0';
  renderCart();
  bootstrap.Modal.getInstance(document.getElementById('receiptModal'))?.hide();
}

// ── Print Receipt ──────────────────────────────────────────
function printReceipt() {
  console.log('Print Receipt clicked');
  
  try {
    // Get receipt content
    const receiptContent = document.getElementById('receiptContent');
    if (!receiptContent) {
      alert('Receipt content not found');
      return;
    }
    
    // Clone the receipt
    const receiptClone = receiptContent.cloneNode(true);
    
    // Create a new window
    const printWindow = window.open('', 'ReceiptPrint');
    
    if (!printWindow) {
      alert('Pop-up blocked! Please allow pop-ups for this site.');
      return;
    }
    
    // Write HTML to print window
    const htmlContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Receipt</title>
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          html, body { height: 100%; }
          body { 
            font-family: 'Courier New', monospace;
            background: #fff;
            color: #000;
            padding: 10px;
          }
          .content {
            max-width: 320px;
            margin: 0 auto;
          }
          .receipt-container { font-size: 12px; }
          .receipt-header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
          .receipt-logo { width: 50px; height: auto; margin: 0 auto 5px; display: block; }
          .receipt-store-name { font-weight: bold; font-size: 13px; }
          .receipt-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
          .receipt-table th { text-align: left; border-bottom: 1px dashed #000; padding: 5px 0; font-weight: bold; }
          .receipt-table td { padding: 3px 0; font-size: 11px; }
          .receipt-totals { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; margin: 10px 0; font-size: 11px; }
          .receipt-totals div { display: flex; justify-content: space-between; margin: 3px 0; }
          .receipt-footer { text-align: center; border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; font-size: 10px; }
          .actions { text-align: center; margin-top: 20px; }
          button { background: #3a8fa3; color: white; border: none; padding: 12px 24px; margin: 5px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
          button:hover { background: #2a6f80; }
          @media print {
            .actions { display: none; }
            body { padding: 0; }
          }
        </style>
      </head>
      <body>
        <div class="content">
          ${receiptContent.innerHTML}
        </div>
        <div class="actions">
          <button onclick="window.print()">🖨 Print This Receipt</button>
          <button onclick="window.close()">✕ Close</button>
        </div>
      </body>
      </html>
    `;
    
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    console.log('Print window created successfully');
    
  } catch (error) {
    console.error('Print error:', error);
    alert('Error opening print window: ' + error.message);
  }
}

// ── Barcode Scanner ────────────────────────────────────────
function startScanner() {
  if (scanner) return;
  scanner = new Html5Qrcode('reader');
  scanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 280, height: 100 } },
    (code) => {
      stopScanner();
      bootstrap.Modal.getInstance(document.getElementById('scannerModal'))?.hide();
      tryAddByBarcode(code);
      // Focus search field for next scan
      setTimeout(() => document.getElementById('posSearch').focus(), 300);
    }
  ).catch(err => showToast('Camera error: ' + err, 'danger'));
}

function stopScanner() {
  if (scanner) {
    scanner.stop().catch(()=>{});
    scanner = null;
  }
}

// ── Helpers ────────────────────────────────────────────────
function fmt2(n) { return parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtCurrency(n) { return '₱ ' + fmt2(n); }
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function debounce(fn,ms){let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms);}}
