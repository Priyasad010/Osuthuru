<?php use App\Service\AssistantService; ?>
<button type="button" id="assistantToggle" class="assistant-toggle btn btn-suwoda shadow" aria-controls="assistantPanel" aria-expanded="false" title="<?= e(t('assistant_title')) ?>">
    <i class="bi bi-chat-dots-fill"></i><span class="visually-hidden"><?= e(t('assistant_title')) ?></span>
</button>

<section id="assistantPanel" class="assistant-panel card shadow-lg d-none" aria-labelledby="assistantTitle"
    data-error="<?= e(t('assistant_err_request')) ?>"
    data-thinking="<?= e(t('assistant_thinking')) ?>">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <strong id="assistantTitle"><i class="bi bi-flower1 me-1"></i><?= e(t('assistant_title')) ?></strong>
        <button type="button" id="assistantClose" class="btn-close btn-close-white" aria-label="<?= e(t('assistant_close')) ?>"></button>
    </div>
    <div class="alert alert-warning rounded-0 py-1 px-2 mb-0 assistant-notice">
        <?= e(t('assistant_privacy')) ?>
    </div>
    <div id="assistantLog" class="assistant-log" role="log" aria-live="polite">
        <div class="assistant-msg assistant-bot"><?= e(t('assistant_greeting')) ?></div>
    </div>
    <form id="assistantForm" class="card-footer bg-white d-flex gap-2">
        <input type="text" id="assistantInput" class="form-control" maxlength="<?= AssistantService::MAX_INPUT ?>" autocomplete="off"
            placeholder="<?= e(t('assistant_placeholder')) ?>" aria-label="<?= e(t('assistant_placeholder')) ?>" required>
        <button type="submit" class="btn btn-suwoda" title="<?= e(t('assistant_send')) ?>"><i class="bi bi-send-fill"></i></button>
    </form>
</section>
<script src="/assets/js/assistant.js" defer></script>
