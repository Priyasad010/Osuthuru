<?php

use App\Core\Auth;
use App\Service\DoshaService;

$labels = [];
$values = [];
foreach (DoshaService::DOSHAS as $dosha) {
    $labels[] = t('dosha_' . $dosha);
    $values[] = (int)$result[$dosha];
}
?>
<h2 class="fw-bold text-success mb-3"><i class="bi bi-pie-chart me-2"></i><?= e(t('quiz_result_title')) ?></h2>

<div class="disclaimer-box mb-4">
    <i class="bi bi-shield-exclamation text-warning me-2"></i><?= e(t('quiz_disclaimer')) ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm p-4 h-100">
            <p class="text-muted small mb-1"><?= e(t('quiz_your_type')) ?></p>
            <h3 class="fw-bold text-success mb-3"><?= e(DoshaService::label($result['dominant'])) ?></h3>
            <canvas id="doshaChart" role="img" aria-label="<?= e(t('quiz_result_title')) ?>"
                    data-labels="<?= e(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>"
                    data-values="<?= e(json_encode($values)) ?>"></canvas>
            <ul class="list-unstyled mt-3 mb-0">
                <?php foreach ($labels as $i => $label): ?>
                    <li class="d-flex justify-content-between border-top py-1">
                        <span><?= e($label) ?></span><strong><?= (int)$values[$i] ?>%</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <?php if (Auth::check()): ?>
                    <?php if ($saved): ?>
                        <span class="badge bg-success-subtle text-success p-2"><i class="bi bi-check-circle me-1"></i><?= e(t('quiz_is_saved')) ?></span>
                    <?php else: ?>
                        <form method="POST" action="/quiz/save">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('quiz_save')) ?></button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login" class="btn btn-outline-success btn-sm"><?= e(t('quiz_login_to_save')) ?></a>
                <?php endif; ?>
                <a href="/quiz" class="btn btn-outline-secondary btn-sm"><?= e(t('quiz_retake')) ?></a>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <?php foreach (explode('-', $result['dominant']) as $dosha): ?>
            <?php $profile = $profiles[$dosha] ?? null; ?>
            <div class="card border-0 shadow-sm p-4 mb-3">
                <h5 class="fw-bold text-success"><?= e(t_or('dosha_', $dosha)) ?></h5>
                <?php if (empty($profile['display_description']) && empty($profile['display_tips'])): ?>
                    <p class="text-muted small mb-0"><?= e(t('quiz_profile_pending')) ?></p>
                <?php else: ?>
                    <p><?= nl2br(e($profile['display_description'])) ?></p>
                    <?php if (!empty($profile['display_tips'])): ?>
                        <h6 class="fw-bold"><?= e(t('quiz_tips')) ?></h6>
                        <p class="mb-0"><?= nl2br(e($profile['display_tips'])) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h4 class="fw-bold text-success mb-3"><?= e(t('recommended_title')) ?></h4>
<?php if (empty($products)): ?>
    <p class="text-muted"><?= e(t('recommended_none')) ?></p>
<?php else: ?>
    <?= \App\Core\View::partial('product_cards', ['products' => $products]) ?>
<?php endif; ?>

<?php if (!empty($history)): ?>
    <h5 class="fw-bold text-success mt-4 mb-2"><?= e(t('quiz_history')) ?></h5>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('col_date')) ?></th>
                        <th><?= e(t('quiz_your_type')) ?></th>
                        <?php foreach ($labels as $label): ?><th class="text-end"><?= e($label) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= e($row['created_at']) ?></td>
                            <td><?= e(DoshaService::label($row['dominant'])) ?></td>
                            <?php foreach (DoshaService::DOSHAS as $dosha): ?><td class="text-end"><?= (int)$row[$dosha] ?>%</td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="/assets/js/quiz.js" defer></script>
