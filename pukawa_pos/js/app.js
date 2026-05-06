/* ============================================================
   Pukawa Store POS — app.js  (shared utilities)
============================================================ */

// ── Toast helper ─────────────────────────────────────────
function showToast(message, type = 'success') {
  const id = 'toast-' + Date.now();
  const icons = { success: 'check-circle-fill', danger: 'x-circle-fill',
                  warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
  const html = `
    <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi bi-${icons[type] || 'info-circle-fill'}"></i>
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>`;
  let tc = document.querySelector('.toast-container');
  if (!tc) {
    tc = document.createElement('div');
    tc.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(tc);
  }
  tc.insertAdjacentHTML('beforeend', html);
  const toastEl = document.getElementById(id);
  new bootstrap.Toast(toastEl, { delay: 3500 }).show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

// ── Currency format ───────────────────────────────────────
function fmtCurrency(n) {
  return '₱ ' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── Debounce ──────────────────────────────────────────────
function debounce(fn, ms) {
  let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

// ── Confirm dialog (Bootstrap modal) ─────────────────────
function confirmAction(message, onConfirm) {
  const id = 'confirm-modal-' + Date.now();
  const html = `
    <div class="modal fade" id="${id}" tabindex="-1">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body pt-4 pb-2 px-4 text-center">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
            <p class="mt-3 mb-1 fw-semibold">${message}</p>
          </div>
          <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
            <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-sm btn-danger" id="${id}-confirm">Confirm</button>
          </div>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById(id));
  modal.show();
  document.getElementById(id + '-confirm').addEventListener('click', () => {
    modal.hide(); onConfirm();
  });
  document.getElementById(id).addEventListener('hidden.bs.modal', () => {
    document.getElementById(id)?.remove();
  });
}

// ── AJAX shorthand ─────────────────────────────────────────
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return res.json();
}

async function apiGet(url) {
  const res = await fetch(url);
  return res.json();
}

// ── Sidebar Toggle (Mobile) ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const mainWrapper = document.getElementById('mainWrapper');
  
  if (sidebarToggle && sidebar) {
    // Toggle sidebar on mobile
    sidebarToggle.addEventListener('click', () => {
      const isMobile = window.innerWidth <= 768;
      if (isMobile) {
        sidebar.classList.toggle('mobile-open');
      } else {
        // Desktop: collapse/expand
        sidebar.classList.toggle('collapsed');
        mainWrapper.classList.toggle('expanded');
      }
    });

    // Close sidebar when clicking on nav item (mobile only)
    const navItems = sidebar.querySelectorAll('.nav-item');
    navItems.forEach(item => {
      item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove('mobile-open');
        }
      });
    });

    // Close sidebar when clicking outside (mobile)
    document.addEventListener('click', (e) => {
      const isMobile = window.innerWidth <= 768;
      if (isMobile && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        sidebar.classList.remove('mobile-open');
      }
    });
  }

  // Display clock on page load (static, not updating)
  const storeClock = document.getElementById('storeClock');
  if (storeClock) {
    const updateClock = () => {
      const now = new Date();
      storeClock.textContent = now.toLocaleTimeString('en-PH', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
      });
    };
    updateClock();
  }
});
