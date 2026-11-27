<?php
/** Expects $pager (App\Core\Pager::of), $path and $query. */
use App\Core\Pager;

if ($pager['pages'] < 2) {
    return;
}
$shown = array_unique(array_filter(
    [1, $pager['page'] - 2, $pager['page'] - 1, $pager['page'], $pager['page'] + 1, $pager['page'] + 2, $pager['pages']],
    fn($n) => $n >= 1 && $n <= $pager['pages']
));
sort($shown);
$previous = 0;
?>
<nav class="mt-4" aria-label="<?= e(t('pager_label')) ?>">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $pager['page'] === 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(Pager::url($path, $query, $pager['page'] - 1)) ?>" rel="prev"><?= e(t('pager_previous')) ?></a>
        </li>
        <?php foreach ($shown as $n): ?>
            <?php if ($n > $previous + 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <li class="page-item <?= $n === $pager['page'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(Pager::url($path, $query, $n)) ?>" <?= $n === $pager['page'] ? 'aria-current="page"' : '' ?>><?= $n ?></a>
            </li>
            <?php $previous = $n; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pager['page'] === $pager['pages'] ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(Pager::url($path, $query, $pager['page'] + 1)) ?>" rel="next"><?= e(t('pager_next')) ?></a>
        </li>
    </ul>
</nav>
