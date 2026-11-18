<div class="row align-items-center py-5">
    <div class="col-lg-7">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill mb-3">
            <i class="bi bi-patch-check-fill me-1"></i> Phase 0: Foundation Running
        </span>
        <h1 class="display-5 fw-bold text-success mb-3"><?= e(t('welcome_title')) ?></h1>
        <p class="lead text-muted mb-4"><?= e(t('welcome_subtitle')) ?></p>
        
        <div class="d-flex flex-wrap gap-2">
            <a href="/api/health" class="btn btn-outline-success">
                <i class="bi bi-activity me-1"></i> Check PHP API Health
            </a>
            <a href="http://localhost:8025" target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-envelope me-1"></i> Mailpit Inbox
            </a>
        </div>
    </div>
    <div class="col-lg-5 text-center mt-4 mt-lg-0">
        <div class="card shadow-sm border-0 bg-white p-4">
            <h5 class="fw-bold mb-3 text-success"><i class="bi bi-layers-fill me-2"></i>V2 Architecture Status</h5>
            <ul class="list-group list-group-flush text-start small">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Web App (PHP 8.3 + Apache)
                    <span class="badge bg-success rounded-pill">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Database (MySQL 8)
                    <span class="badge bg-success rounded-pill">Ready</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    ML Service (Python 3.12 FastAPI)
                    <span class="badge bg-info text-dark rounded-pill">Port 8000</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Mailpit Dev SMTP
                    <span class="badge bg-secondary rounded-pill">Port 8025</span>
                </li>
            </ul>
        </div>
    </div>
</div>
