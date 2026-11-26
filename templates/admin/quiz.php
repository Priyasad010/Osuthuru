<?php use App\Service\DoshaService; ?>
<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_quiz')) ?></h2>
</div>

<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e(t('admin_quiz_warning')) ?></div>
<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

<div class="card border-0 shadow-sm p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong><?= e(t('col_status')) ?>:</strong>
            <span class="badge <?= $published && !$problems ? 'bg-success' : 'bg-secondary' ?>">
                <?= e($published && !$problems ? t('admin_published') : t('admin_unpublished')) ?>
            </span>
            <a href="/quiz" class="small ms-2"><?= e(t('admin_preview')) ?></a>
        </div>
        <form method="POST" action="/admin/quiz/publish">
            <?= csrf_field() ?>
            <input type="hidden" name="published" value="<?= $published ? '0' : '1' ?>">
            <button type="submit" class="btn btn-sm <?= $published ? 'btn-outline-danger' : 'btn-suwoda' ?>">
                <?= e($published ? t('admin_unpublish') : t('admin_publish')) ?>
            </button>
        </form>
    </div>
    <?php foreach ($problems as $problem): ?>
        <div class="small text-danger mt-1"><?= e($problem) ?></div>
    <?php endforeach; ?>
</div>

<h4 class="fw-bold text-success mb-3"><?= e(t('admin_profiles')) ?></h4>
<?php foreach (DoshaService::DOSHAS as $dosha): ?>
    <?php $p = $profiles[$dosha]; ?>
    <form method="POST" action="/admin/quiz/profile" class="card border-0 shadow-sm p-3 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="dosha" value="<?= e($dosha) ?>">
        <h6 class="fw-bold"><?= e(t('dosha_' . $dosha)) ?></h6>
        <div class="row g-2">
            <?php foreach (['description_en' => 'admin_desc_en', 'description_si' => 'admin_desc_si', 'tips_en' => 'admin_tips_en', 'tips_si' => 'admin_tips_si'] as $field => $label): ?>
                <div class="col-md-6">
                    <label class="form-label small" for="<?= e($dosha . '_' . $field) ?>"><?= e(t($label)) ?></label>
                    <textarea class="form-control form-control-sm" rows="2" id="<?= e($dosha . '_' . $field) ?>" name="<?= e($field) ?>"><?= e($p[$field] ?? '') ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-end mt-2"><button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('save')) ?></button></div>
    </form>
<?php endforeach; ?>

<h4 class="fw-bold text-success mt-4 mb-3"><?= e(t('admin_questions')) ?> (<?= count($questions) ?>)</h4>

<?php foreach ($questions as $q): ?>
    <?php $qid = (int)$q['question_id']; ?>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <form method="POST" action="/admin/quiz/question" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $qid ?>">
            <div class="col-md-1">
                <label class="form-label small"><?= e(t('admin_order')) ?></label>
                <input type="number" name="sort_order" value="<?= (int)$q['sort_order'] ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-5">
                <label class="form-label small"><?= e(t('admin_question_en')) ?></label>
                <input type="text" name="text_en" value="<?= e($q['text_en']) ?>" class="form-control form-control-sm" maxlength="255" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small"><?= e(t('admin_question_si')) ?></label>
                <input type="text" name="text_si" value="<?= e($q['text_si']) ?>" class="form-control form-control-sm" maxlength="255">
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('save')) ?></button></div>
        </form>

        <?php // Existing options, then a blank row for a new one ?>
        <?php $rows = $q['options']; if (count($rows) < \App\Repository\QuizRepo::MAX_OPTIONS) { $rows[] = null; } ?>
        <div class="ms-md-4 mt-2">
            <?php foreach ($rows as $o): ?>
                <div class="d-flex gap-1 align-items-start border-top pt-2 mt-2">
                    <form method="POST" action="/admin/quiz/option" class="row g-1 flex-grow-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)($o['option_id'] ?? 0) ?>">
                        <input type="hidden" name="question_id" value="<?= $qid ?>">
                        <div class="col-md-4"><input type="text" name="text_en" value="<?= e($o['text_en'] ?? '') ?>" class="form-control form-control-sm" placeholder="<?= e($o ? t('admin_option_en') : t('admin_new_option')) ?>" aria-label="<?= e(t('admin_option_en')) ?>" maxlength="255" required></div>
                        <div class="col-md-4"><input type="text" name="text_si" value="<?= e($o['text_si'] ?? '') ?>" class="form-control form-control-sm" placeholder="<?= e(t('admin_option_si')) ?>" aria-label="<?= e(t('admin_option_si')) ?>" maxlength="255"></div>
                        <?php foreach (DoshaService::DOSHAS as $dosha): ?>
                            <div class="col-md-1">
                                <input type="number" name="<?= e($dosha) ?>" value="<?= (int)($o[$dosha] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm" title="<?= e(t('dosha_' . $dosha)) ?>" aria-label="<?= e(t('dosha_' . $dosha)) ?>">
                                <div class="text-muted" style="font-size: 0.7rem;"><?= e(t('dosha_' . $dosha)) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-1"><button type="submit" class="btn btn-outline-success btn-sm w-100"><?= e(t('save')) ?></button></div>
                    </form>
                    <?php if ($o): ?>
                        <form method="POST" action="/admin/quiz/option/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$o['option_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="/admin/quiz/question/delete" class="text-end mt-2" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $qid ?>">
            <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('admin_delete_question')) ?></button>
        </form>
    </div>
<?php endforeach; ?>

<form method="POST" action="/admin/quiz/question" class="card border-0 shadow-sm p-3 row g-2 flex-row align-items-end">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="sort_order" value="<?= count($questions) + 1 ?>">
    <div class="col-md-5">
        <label class="form-label small"><?= e(t('admin_question_en')) ?></label>
        <input type="text" name="text_en" class="form-control form-control-sm" maxlength="255" required>
    </div>
    <div class="col-md-5">
        <label class="form-label small"><?= e(t('admin_question_si')) ?></label>
        <input type="text" name="text_si" class="form-control form-control-sm" maxlength="255">
    </div>
    <div class="col-md-2"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('admin_add_question')) ?></button></div>
</form>
