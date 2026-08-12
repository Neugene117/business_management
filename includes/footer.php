<?php
$root_prefix = getRootPrefix();
?>
    <footer style="margin-top: 40px; padding: 20px 0; text-align: center; border-top: 1px solid var(--border); font-size: 11px; color: var(--text3);">
      © 2026 Business Management · All rights reserved
    </footer>
  </div> <!-- Close .content -->
</div> <!-- Close .main -->

<script src="<?php echo $root_prefix; ?>src/js/navbar.js"></script>
<script src="<?php echo $root_prefix; ?>src/js/sidebar.js"></script>
<script src="<?php echo $root_prefix; ?>src/js/searchable-select.js"></script>
<?php if (isset($extra_js)): ?>
  <?php foreach ($extra_js as $js_file): ?>
    <script src="<?php echo $root_prefix; ?>src/js/<?php echo e($js_file); ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
<script>
(function () {
  document.querySelectorAll('.data-table').forEach(function (table) {
    if (table.closest('.modal-overlay') || table.parentElement.classList.contains('table-scroll')) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'table-scroll';
    wrapper.tabIndex = 0;
    var card = table.closest('.card');
    var title = card ? card.querySelector('.card-title') : null;
    wrapper.setAttribute('aria-label', (title ? title.textContent.trim() : 'Data table') + ' scrollable area');
    var columnCount = table.querySelectorAll('thead th').length;
    var rowCount = table.querySelectorAll('tbody tr').length;
    if (columnCount >= 6 || rowCount > 10) wrapper.classList.add('is-dense');
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });
})();
</script>
<?php if (isRolePreviewActive()): ?>
<script>
(function () {
  var previewRole = <?php echo json_encode(getPreviewRole()); ?>;

  document.querySelectorAll('form').forEach(function (form) {
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method === 'GET' && !form.querySelector('input[name="role"]')) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'role';
      input.value = previewRole;
      form.appendChild(input);
    }
  });

  document.querySelectorAll('a[href]').forEach(function (link) {
    if (link.getAttribute('href').charAt(0) === '#' || link.closest('.role-switcher')) return;
    try {
      var url = new URL(link.href, window.location.href);
      if (url.origin === window.location.origin && !url.searchParams.has('role') && !/\/logout\.php$/.test(url.pathname)) {
        url.searchParams.set('role', previewRole);
        link.href = url.toString();
      }
    } catch (error) {}
  });
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
// The shared header starts an output buffer. Canonicalize every rendered link
// and form action centrally so new pages inherit clean URLs automatically.
if (ob_get_level() > 0) {
    $renderedPage = ob_get_clean();
    echo cleanPageUrlsInHtml($renderedPage);
}
?>
