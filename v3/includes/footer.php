<?php $authLayout = $authLayout ?? false; $footerUser = current_user(); ?>
<?php if (!$authLayout && $footerUser): ?>
    </main>
    <footer class="app-footer"><span>GymPro operations</span><span><?= e(date('Y')) ?></span></footer>
  </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= e(base_url('assets/vendor/flowbite/flowbite.min.js?v=' . filemtime(V3_ROOT . '/assets/vendor/flowbite/flowbite.min.js'))) ?>" defer></script>
<script src="<?= e(base_url('assets/js/app.js?v=' . filemtime(V3_ROOT . '/assets/js/app.js'))) ?>" defer></script>
</body>
</html>
