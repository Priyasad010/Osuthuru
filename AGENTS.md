# AGENTS.md - Osuthuru
## Project
E-commerce site for Sri Lankan herbal / Ayurvedic products. Roles: guest, customer, admin.
Bilingual (English + Sinhala). Work from docs/ROADMAP.md, ONE phase at a time.

## Stack
PHP 8.3 (plain PHP, PSR-4 classes in src/, no framework), MySQL 8 via PDO, Bootstrap 5.3,
vanilla JS (fetch), Chart.js, Python 3.12 FastAPI in ml/, Docker Compose, PHPUnit, pytest.

## Commands
docker compose up -d --build
docker compose exec web php bin/migrate.php
docker compose exec web composer test
docker compose exec ml pytest

## Conventions
- All SQL through PDO prepared statements. Never concatenate user input into SQL.
- Passwords: password_hash(). Never log or echo secrets. Secrets only in .env.
- Every POST form and state-changing fetch has a CSRF token.
- Every admin page and API route starts with Auth::requireAdmin() / requireUser().
- Escape output with e(). Validate uploads (type, size) and rename files.
- Schema changes only via a new file in database/migrations/ (NNN_description.sql).
  Never edit a migration that has been applied.
- Timezone Asia/Colombo, currency LKR ("Rs."), charset utf8mb4.
- UI text goes through t('key') with lang/en.php and lang/si.php.
- JSON API: {"ok": true, "data": ...} or {"ok": false, "error": "..."}.

## Medical content rule
The site is educational. Do not write or hard-code medical claims. Health text comes from
admin-entered data. Show the disclaimer on concern, quiz, safety and assistant pages.

## Definition of done
Acceptance criteria of the phase pass, tests added and green, migration included,
README updated, commit message "phaseN: short description".

## Do not
- Copy code from legacy/ without refactoring it (V1 has known security problems).
- Add frameworks or packages that are not listed in the roadmap without asking.
- Touch code that belongs to a different phase.
