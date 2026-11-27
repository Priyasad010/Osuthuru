<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm p-4 text-center">
            <i class="bi bi-shield-lock display-5 text-success" aria-hidden="true"></i>
            <h2 class="mt-2"><?= e(t('payhere_title')) ?></h2>
            <p class="mb-1"><?= e(t('order_number', ['id' => (int)$order['order_id']])) ?></p>
            <p class="fs-4 fw-bold"><?= e(format_price((float)$order['total_price'])) ?></p>
            <p class="text-muted small"><?= e(t('payhere_intro')) ?></p>
            <?php if ($sandbox): ?>
                <div class="alert alert-warning small py-2"><?= e(t('payhere_sandbox')) ?></div>
            <?php endif; ?>

            <!-- Posted to PayHere, so it deliberately carries no token of this site -->
            <form method="post" action="<?= e($action) ?>" id="payhereForm">
                <?php foreach ($fields as $name => $value): ?>
                    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-brand btn-lg w-100"><?= e(t('payhere_continue')) ?></button>
            </form>
            <a href="/orders/<?= (int)$order['order_id'] ?>" class="btn btn-link btn-sm mt-2"><?= e(t('payhere_later')) ?></a>
        </div>
    </div>
</div>
