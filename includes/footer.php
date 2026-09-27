    </main>
  </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
