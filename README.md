# Suwoda HerbHub V2

> Herbal and Ayurvedic E-commerce platform for Sri Lanka.
> Built phase-by-phase following the Antigravity V2 Build Roadmap.

## Stack
- **Web App**: PHP 8.3 (Apache, PSR-4 classes in `src/`, vanilla JS, Bootstrap 5.3)
- **Database**: MySQL 8.0 via PDO (utf8mb4, native prepared statements)
- **Intelligence**: Python 3.12 FastAPI (uvicorn) in `ml/`
- **Mail Testing**: Mailpit (Web UI on port 8025, SMTP on port 1025)
- **Containerization**: Docker Compose

## Quick Start

1. **Clone the repository**:
   ```bash
   git clone <repo-url> suwoda-herbhub-v2
   cd suwoda-herbhub-v2
   ```

2. **Setup Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Start Docker Services**:
   ```bash
   docker compose up -d --build
   ```

4. **Install Composer Dependencies & Run Migrations**:
   ```bash
   docker compose exec web composer install
   docker compose exec web php bin/migrate.php
   docker compose exec web php bin/seed.php
   docker compose exec web php bin/create_admin.php
   ```
   `seed.php` loads the demo catalogue and is safe to run again. `create_admin.php` uses `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.

5. **Run Tests**:
   ```bash
   # Run PHPUnit tests
   docker compose exec web composer test

   # Run Python ML tests
   docker compose exec ml pytest
   ```
   PHPUnit rebuilds a separate `suwoda_test` database on every run (using `DB_ROOT_PASS`), so development data is not touched. Test emails still appear in Mailpit.

## Services & Ports
- Web Application: [http://localhost:8080](http://localhost:8080)
- PHP API Health: [http://localhost:8080/api/health](http://localhost:8080/api/health)
- ML Service Health: [http://localhost:8000/health](http://localhost:8000/health)
- Mailpit Web UI: [http://localhost:8025](http://localhost:8025)
- MySQL Database: `localhost:3306` (user: `app`, database: `suwoda`)

## Dosha quiz and safety checker
- The quiz ships as an **unpublished draft** (12 questions). Review it, then publish at `/admin/quiz`. Administrators can preview it at `/quiz`.
- Enter result descriptions and tips at `/admin/quiz`, and match products to doshas (strength 1 to 3) at `/admin/dosha`. Recommendations stay empty until products are matched.
- Safety data is managed at `/admin/safety`. Only **published** entries are shown, and publishing requires a warning and its source. `bin/seed.php` adds three unpublished `SAMPLE` entries that are not medical information.
- Safety answers are kept in the visitor's session only (and in the browser's `localStorage` if they ask). They are never written to the database. An `avoid` match must be acknowledged at checkout.
- Quiz answers are not stored; signed-in users can save their percentage scores.

## Behaviour to know about
- **Email verification**: new accounts cannot log in until the emailed link is opened (see Mailpit in development). Logging in with the correct password sends the link again.
- **Login lockout**: based on the connecting IP address. Behind a reverse proxy, configure the web server to set `REMOTE_ADDR` from that trusted proxy.
- **Cancelled orders**: stock returns once, only if it had been taken. Cancelled is final, delivered orders cannot be cancelled, and a paid order that is cancelled must be refunded manually.
- **Herb text**: no health text is seeded. Enter practitioner-reviewed text at `/admin/herbs`; empty entries show "awaiting review".
- **Languages**: shop pages, admin pages and emails are in English and Sinhala; emails use the customer's last chosen language.
- **Invoices**: English downloads as PDF. Sinhala opens as a printable page (print or save as PDF), because the PDF library cannot shape Sinhala script.
- **Sinhala review**: `docker compose exec web php bin/export_translations.php` writes `docs/translation_review.csv` for a native speaker. Apply corrections in `lang/si.php`.
- **Catalogue text**: seeded descriptions make no health claims. Admins can edit English and Sinhala descriptions under Manage Products.

## Project Phases
- [x] **Phase 0: Foundation** (Docker, DB baseline migration, Core PSR-4 classes, base layout)
- [x] **Phase 1: Security and core fixes** (Safe auth, admin guards, token reset, checkout correctness)
- [x] **Phase 2: Commerce for Sri Lanka** (COD, bank transfer, delivery zones, invoices, wishlist)
- [x] **Phase 3: Herbal knowledge and bilingual search** (Sinhala/English, concern pages, voice)
- [x] **Phase 4: Novelty A** (Dosha quiz, safety checker)
- [ ] **Phase 5: Novelty B** (QR batch traceability, AI Assistant)
- [ ] **Phase 6: Python intelligence service** (FastAPI recommender & sales forecast)
- [ ] **Phase 7: Admin analytics, PWA, quality & polish**
