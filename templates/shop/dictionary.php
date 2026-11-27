<div class="text-center mb-4">
    <h2 class="fw-bold text-success mb-2"><?= e(t('dictionary_title')) ?></h2>
    <p class="text-muted small mx-auto" style="max-width: 600px;"><?= e(t('dictionary_subtitle')) ?></p>
</div>

<!-- A-Z Alphabet Filter -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-center gap-1">
        <a href="/dictionary" class="btn btn-sm <?= empty($selectedLetter) ? 'btn-suwoda' : 'btn-outline-secondary' ?>">
            <?= e(t('filter_all')) ?>
        </a>
        <?php foreach (range('A', 'Z') as $char): ?>
            <a href="/dictionary?letter=<?= $char ?>"
               class="btn btn-sm <?= ($selectedLetter ?? '') === $char ? 'btn-suwoda' : 'btn-outline-secondary' ?>"
               style="width: 32px; padding: 4px 0;">
                <?= $char ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Herb Cards Grid -->
<?php if (empty($herbs)): ?>
    <div class="card border-0 shadow-sm p-5 text-center text-muted">
        <i class="bi bi-journal-x fs-1 mb-2"></i>
        <h5><?= e(t('no_herbs_letter', ['letter' => $selectedLetter ?? ''])) ?></h5>
        <a href="/dictionary" class="btn btn-sm btn-outline-success mt-2"><?= e(t('view_all_herbs')) ?></a>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <?php foreach ($herbs as $h): ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                        <?= picture($h['image'] ?? null, $h['display_name'], 'text-success opacity-50 fs-1') ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h6 class="fw-bold text-success mb-1">
                            <a href="/herb/<?= (int)$h['herb_id'] ?>" class="text-success text-decoration-none">
                                <?= e($h['display_name']) ?>
                            </a>
                        </h6>
                        <small class="text-muted fst-italic mb-2"><?= e($h['scientific_name']) ?></small>
                        <p class="small text-secondary mb-3 flex-grow-1">
                            <?= e(mb_strimwidth((string)($h['display_uses'] ?: t('uses_pending')), 0, 90, '...')) ?>
                        </p>
                        <div class="mt-auto">
                            <a href="/herb/<?= (int)$h['herb_id'] ?>" class="btn btn-outline-success btn-sm w-100">
                                <?= e(t('view_uses_products')) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="disclaimer-box text-center">
    <i class="bi bi-shield-exclamation text-warning me-2"></i>
    <?= e(t('concern_disclaimer')) ?>
</div>
