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
<?php if (isset($extra_js)): ?>
  <?php foreach ($extra_js as $js_file): ?>
    <script src="<?php echo $root_prefix; ?>src/js/<?php echo e($js_file); ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
