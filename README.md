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
   ```

5. **Run Tests**:
   ```bash
   # Run PHPUnit tests
   docker compose exec web composer test

   # Run Python ML tests
   docker compose exec ml pytest
   ```

## Services & Ports
- Web Application: [http://localhost:8080](http://localhost:8080)
- PHP API Health: [http://localhost:8080/api/health](http://localhost:8080/api/health)
- ML Service Health: [http://localhost:8000/health](http://localhost:8000/health)
- Mailpit Web UI: [http://localhost:8025](http://localhost:8025)
- MySQL Database: `localhost:3306` (user: `app`, database: `suwoda`)

## Project Phases
- [x] **Phase 0: Foundation** (Docker, DB baseline migration, Core PSR-4 classes, base layout)
- [x] **Phase 1: Security and core fixes** (Safe auth, admin guards, token reset, checkout correctness)
- [ ] **Phase 2: Commerce for Sri Lanka** (COD, bank transfer, delivery zones, invoices, wishlist)
- [ ] **Phase 3: Herbal knowledge and bilingual search** (Sinhala/English, concern pages, voice)
- [ ] **Phase 4: Novelty A** (Dosha quiz, safety checker)
- [ ] **Phase 5: Novelty B** (QR batch traceability, AI Assistant)
- [ ] **Phase 6: Python intelligence service** (FastAPI recommender & sales forecast)
- [ ] **Phase 7: Admin analytics, PWA, quality & polish**
