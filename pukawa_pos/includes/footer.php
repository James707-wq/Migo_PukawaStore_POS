  </div><!-- /.page-content -->
</div><!-- /.main-wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- Html5 QR Code Scanner (for barcode scanning) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<!-- App JS -->
<script>
  // Make BASE_URL available to all scripts
  const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>js/app.js"></script>
<script>
  // Sidebar toggle
  document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainWrapper').classList.toggle('expanded');
  });
  // Clock - Display once on page load
  function tick() {
    const el = document.getElementById('storeClock');
    if (el) el.textContent = new Date().toLocaleString('en-PH', {
      weekday:'short', month:'short', day:'numeric',
      hour:'2-digit', minute:'2-digit', second:'2-digit'
    });
  }
  tick();
</script>
<?php if (isset($extraJS)) echo $extraJS; ?>
</body>
</html>
