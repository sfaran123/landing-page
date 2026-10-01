<?php
/**
 * Framework-agnostic page fragment (used by the Blade views and by demo/server.php).
 * @var string $page  'dashboard' | 'editor'
 * @var array  $boot  bootstrap payload from DashboardService
 * @var array  $urls  ['dashboard','editor','api','assets','csrf','base']
 */
$v = '1.0.0';
$json = fn ($x) => json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars($urls['assets']) ?>/sd.css?v=<?= $v ?>">
<div id="sd-root" class="sd<?= $page === 'editor' ? ' ed' : '' ?>" dir="rtl" lang="he"></div>
<script>window.SD_BOOT = <?= $json($boot) ?>; window.SD_URLS = <?= $json($urls) ?>;</script>
<script src="<?= htmlspecialchars($urls['assets']) ?>/sd-core.js?v=<?= $v ?>"></script>
<script src="<?= htmlspecialchars($urls['assets']) ?>/sd-<?= $page === 'editor' ? 'editor' : 'dashboard' ?>.js?v=<?= $v ?>"></script>
