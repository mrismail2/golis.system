<?php
/** Portal layout - bottom of the page. */
declare(strict_types=1);
?>
    </main>

    <footer class="app-footer">
      &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &ndash; <?= e(APP_CAMPUS) ?> &nbsp;|&nbsp;
      University Management System &nbsp;|&nbsp; <?= e(APP_ADDRESS) ?>
    </footer>
  </div>
</div>
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
