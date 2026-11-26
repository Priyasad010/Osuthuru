<div class="row justify-content-center">
    <div class="col-lg-8">
        <h2 class="fw-bold text-success mb-2"><i class="bi bi-clipboard2-pulse me-2"></i><?= e(t('quiz_title')) ?></h2>
        <p class="text-muted"><?= e(t('quiz_intro')) ?></p>

        <div class="disclaimer-box mb-4">
            <i class="bi bi-shield-exclamation text-warning me-2"></i><?= e(t('quiz_disclaimer')) ?>
        </div>

        <?php if (!$open): ?>
            <div class="card border-0 shadow-sm p-5 text-center">
                <i class="bi bi-hourglass-split text-muted fs-1 mb-2"></i>
                <p class="mb-0"><?= e(t('quiz_err_unavailable')) ?></p>
            </div>
        <?php else: ?>
            <?php if ($preview): ?>
                <div class="alert alert-warning py-2 small"><?= e(t('quiz_preview')) ?></div>
            <?php endif; ?>
            <div id="quizError" class="alert alert-danger py-2 small <?= empty($error) ? 'd-none' : '' ?>" role="alert"><?= e($error ?? '') ?></div>

            <form id="quizForm" method="POST" action="/quiz">
                <?= csrf_field() ?>
                <?php foreach ($questions as $number => $q): ?>
                    <?php $qid = (int)$q['question_id']; ?>
                    <fieldset class="card border-0 shadow-sm p-3 mb-3">
                        <legend class="float-none fs-6 fw-bold text-success"><?= (int)$number + 1 ?>. <?= e($q['display_text']) ?></legend>
                        <?php foreach ($q['options'] as $o): ?>
                            <?php $oid = (int)$o['option_id']; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= $qid ?>]" id="opt<?= $oid ?>" value="<?= $oid ?>" required
                                    <?= is_scalar($answers[$qid] ?? null) && (int)$answers[$qid] === $oid ? 'checked' : '' ?>>
                                <label class="form-check-label" for="opt<?= $oid ?>"><?= e($o['display_text']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-suwoda w-100 py-2 fw-semibold"><?= e(t('quiz_submit')) ?></button>
            </form>
            <script src="/assets/js/quiz.js" defer></script>
        <?php endif; ?>
    </div>
</div>
