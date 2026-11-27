# BUILD ROADMAP · FOR GOOGLE ANTIGRAVITY
# Osuthuru
## Novelty features + modern stack, built phase by phase

*Know your body → Find the right herb → Verify it is genuine → Buy safely*

November 2026 · Planning document, adjust phases to your own deadline

### At a glance

| | |
|---|---|
| **Project** | Osuthuru: herbal and Ayurvedic e-commerce site for Sri Lanka (V1 was a 4-member university project) |
| **This document** | A build plan for V2: a new, separate build that keeps what V1 does, fixes its weak points, and adds novelty features and newer technologies |
| **Core stack** | PHP 8.3 + MySQL 8 + Bootstrap 5 + JavaScript (fetch, Chart.js), packaged with Docker Compose |
| **Added technologies** | Python FastAPI service (recommender, forecast, optional leaf identification), LLM API (assistant), QR codes, PDF invoices, PWA |
| **Novelty features** | Dosha quiz · Safety checker · QR authenticity and batch traceability · AI Herbal Assistant · Hybrid recommender · Bilingual (EN/SI) herb search · (stretch) Leaf identification |
| **Build method** | One phase at a time inside Google Antigravity. Every phase has tasks, data model changes, acceptance criteria and a ready-to-paste prompt |
| **Size** | 8 phases, about 8 weeks for a team of 4 (see Section 5) |

**Contents.** 1 How to use this with Antigravity · 2 V1 snapshot and known problems · 3 Goals and novelty features · 4 Architecture and conventions · 5 Roadmap, team split and timeline · 6 Phases 0 to 7 · 7 Viva demo storyline and risks · Appendix A AGENTS.md template · B Tables at a glance · C API summary

---

## 1. How to use this with Antigravity

- **Create a new repo.** Make a folder `suwoda-herbhub-v2/`, run `git init`, and copy the V1 project into `legacy/` as read-only reference. Remove real passwords and keys from the copy first.
- **Add the rules file.** Put `AGENTS.md` (template in Appendix A) in the repo root. Antigravity loads workspace rules from `AGENTS.md`, `GEMINI.md` or `.agents/rules/*.md`. If your installed version uses another location, check its Rules page.
- **Add this roadmap.** Save the text of this document as `docs/ROADMAP.md` so the agent can read the phase it is working on.
- **One phase = one agent conversation.** Start with the common preface below, then paste the prompt from that phase.
- **Plan first, then code.** Ask the agent for an implementation plan, read it, correct it, and only then approve. After it finishes, read the diff and walkthrough, run the tests, and tick the acceptance criteria yourself.
- **Commit after every phase** (for example `phase3: bilingual content and search`). Do not start the next phase until the current one passes.
- **Secrets stay in `.env`.** Never paste real API keys, passwords or customer data into a prompt.

### Common preface (paste before every phase prompt)

> Read AGENTS.md and docs/ROADMAP.md. We are working on **PHASE N only** (replace N). First produce an implementation plan: files to create or change, migrations, risks and open questions, then wait for my approval. After approval, implement the tasks, add tests, run them, and finish with a short walkthrough that lists what changed and which acceptance criteria are met. Do not change code that belongs to other phases. If something in the roadmap is unclear, ask instead of guessing.

### Tips that save time

- Keep prompts small. If a phase feels too big for one session, split it by its task list (for example Phase 2: payments first, then coupons, then wishlist).
- Ask the agent to run `docker compose exec web composer test` and show the result. Do not accept "should work" as an answer.
- Review SQL migrations by hand. They are the part that is hardest to undo.
- Health, safety and dosha text must come from admin-entered data and be checked by a qualified person. The agent must not invent medical claims.

---

## 2. V1 snapshot and known problems

This is what the original project contains. It was read file by file; use it as the reference for what V2 must keep.

### Technology used in V1

| Layer | What V1 uses |
|---|---|
| Backend | Plain PHP with OOP classes in `classes/`, PDO; database connection through `DbConnector` (`includes/dbconnect.php`) |
| Database | MySQL / MariaDB, 12 tables (`suwodaDB.sql`) |
| Frontend | Bootstrap 5.3 and Bootstrap Icons from CDN, a little vanilla JS, Chart.js on the sales report page |
| Payments | Stripe Checkout (card only, LKR) |
| Email | PHPMailer through Gmail SMTP (account verification mail) |
| Language | Google Translate widget (English, Sinhala, Tamil) |
| Libraries | Composer packages shipped as `vendor.zip` |

### Roles and modules

| Role | Class / files | What they can do |
|---|---|---|
| Guest | `GuestUser` | Register (email verification), browse categories and products, search, herbal dictionary, blog |
| Registered user | `RegisteredUser` in `classes/RegisterUser.php` | Login, choose preferred categories (used for "recommended for you"), cart, Stripe checkout, view orders, review a product after it is Delivered, profile |
| Admin | `Admin` in `classes/adminClass.php` (a facade over the manage* methods) | Manage categories, products and discounts, users (view only), orders (status), dictionary (herbs), blogs; sales reports (daily / weekly / monthly) with top products and status charts via `SalesReportTypes.php` |

### V1 database

| Table | Key columns |
|---|---|
| `admin` | admin_id, name, email, password |
| `user` | user_id, name, email, password, phone, address, status (active / inactive), verification_token |
| `user_category` | userCategory_id, user_id, category_id |
| `category` | category_id, name, image |
| `product` | product_id, product_name, category_id, price, quantity, image, description, original_price |
| `discount` | discount_id, product_id, discount_percentage, start_date, end_date |
| `cart` | cart_id, user_id, product_id, quantity |
| `orders` | order_id, user_id, order_date, total_price, status (Pending, Confirmed, Out for Delivery, Delivered) |
| `order_item` | order_item_id, order_id, product_id, quantity, unit_price, subtotal |
| `review` | review_id, user_id, product_id, rating (1 to 5), comment, review_date |
| `herb` | herb_id, name, scientific_name, uses, image |
| `blog` | blog_id, title, image, content, published_date |

### Known problems in V1 (do not carry these into V2)

| # | Problem | Where | Fixed in |
|---|---|---|---|
| 1 | Gmail app password and Stripe secret key are hard-coded in source. **Rotate the Gmail password** if the repo was ever public | `GuestUser.php`, `orderClass.php` | P0, P1 |
| 2 | Anyone can reset any account's password by typing only the email (no token, no email link) | `pages/forgot_password.php` | P1 |
| 3 | Admin pages have no login check (only `session_start`); the admin password is stored as plain text | `admin/*.php`, admin table | P1 |
| 4 | Login accepts a plain-text password as a fallback; passwords are written to the error log | `RegisterUser.php` | P1 |
| 5 | Order is created and stock is reduced before payment is confirmed; cart is not cleared; localhost URLs are hard-coded for Stripe | `orderClass.php` | P1 |
| 6 | Delete actions use GET links (no CSRF protection); uploaded files keep the client's file name and have weak or no type checks | `admin/product_manage.php` and others | P1 |
| 7 | Review text is blank on the home page (code reads `review_text`, column is `comment`) | `pages/index.php` | P1 |
| 8 | `average_order_value` is read by the report page but never calculated (always 0) | `admin/sales_report.php` | P1 |
| 9 | A leftover test script sits in the project root | `test_order_status.php` | P1 |
| 10 | "Recommended for you" is only the first 5 products of the chosen categories; search is LIKE; dictionary is not linked to products; Google Translate mangles medical terms | several pages | P3, P6 |

---

## 3. Goals and novelty features

- **Trustworthy.** Secure login and admin, payments that are only marked paid when they really are, data that can be verified.
- **Local.** Sinhala and English, cash on delivery and bank transfer next to card, delivery fee by district.
- **Novel.** Features that ordinary herbal shops do not have, all connected through the same herb and product data.

### Novelty features

| Feature | What it does | Why it stands out | Phase |
|---|---|---|---|
| **Dosha (Prakriti) quiz** | 12-question quiz, Vata / Pitta / Kapha scores, personalised product list | Turns the Ayurveda content of the blog into a personal experience | 4 |
| **Safety checker** | Customer ticks pregnancy, breastfeeding, child, medicines… and sees herb warnings before paying. Nothing health-related is stored | Responsible selling; privacy by design | 4 |
| **QR authenticity and batch traceability** | QR on each pack opens a public page with batch, dates, source and lab report; flags duplicates and recalls | Answers the counterfeit problem behind "100% authentic" | 5 |
| **AI Herbal Assistant** | Chat in English, Sinhala or Singlish; answers only from your own herb and product data; shows product cards | Grounded (RAG-lite) so it cannot invent products; guarded for medical questions | 5 |
| **Hybrid recommender** | Content + co-purchase + popularity + dosha + preferred categories, with offline evaluation | Measurable, suitable for the university report | 6 |
| **Sales and stock forecast** | 30-day sales forecast, "days to stock-out" per product | Moves the admin panel from reports to decisions | 6, 7 |
| **Bilingual herb search** | Search by English, Sinhala or scientific name or by concern; autocomplete; "did you mean"; voice search | Dictionary and shop become one knowledge base | 3 |
| **Leaf identification** (stretch) | Upload a leaf photo, get the likely herb and where to buy | Computer-vision showpiece | 6 |
| **Replenishment, wishlist, PWA** | Back-in-stock alerts, installable app, offline dictionary | Practical extras that finish the product | 2, 7 |

> **Scope rule.** Core = phases 0 to 5, the recommender and forecast from phase 6, and phase 7. Stretch = leaf identification, PayHere, "customers say" review summaries, loyalty points. Do the stretch items only after the core passes.

---

## 4. Architecture and conventions

PHP stays the main application. Machine-learning work lives in a separate Python service that PHP calls over HTTP and JSON. If that service is down, PHP falls back to simple SQL logic, so the shop keeps working.

```
Browser / installable PWA   (Bootstrap 5 · JavaScript fetch · Chart.js · Web Speech API)
        │ HTTPS
PHP 8.3 web app (Apache)    (Auth · Catalog · Cart/Orders · Coupons · Dosha quiz ·
        │                    Safety checker · QR verify · Assistant API · Admin · Migrations)
        ├── PDO ─────────────► MySQL 8 (single source of truth)
        ├── JSON/HTTP ───────► Python FastAPI ml/ (recommend · forecast · identify; read-only DB user;
        │                       3 s timeout, fallback)
        ├──────────────────── ► LLM API (assistant answers; key stays on server)
        ├──────────────────── ► Stripe / PayHere (card payments, webhooks)
        └──────────────────── ► SMTP / Mailpit (order and reset mail)
```

### Technology choices

| Layer | Choice | Why |
|---|---|---|
| Web app | PHP 8.3, plain PHP with PSR-4 classes in `src/` (no framework) | Same language as V1, so the team keeps its skills; a framework such as Laravel is possible but costs 2 to 3 weeks of learning |
| Database | MySQL 8, utf8mb4, migrations in `database/migrations/` | Sinhala text works; every schema change is versioned |
| Frontend | Bootstrap 5.3, vanilla JS `fetch`, Chart.js, Web Speech API, PWA | No build step; easy for an agent to edit |
| Intelligence | Python 3.12, FastAPI, pandas, scikit-learn, statsmodels (+ PyTorch or TensorFlow for the stretch item) | ML libraries live in Python; PHP only calls HTTP |
| Assistant | LLM API called from PHP with cURL | No extra language needed; key stays server-side |
| Libraries (Composer) | vlucas/phpdotenv, phpmailer/phpmailer, stripe/stripe-php, endroid/qr-code, dompdf/dompdf, phpunit/phpunit (dev) | Small, well known packages |
| Run and test | Docker Compose, PHPUnit, pytest, GitHub Actions | Same environment on all 4 laptops; repeatable demo |

### Folder layout

```
suwoda-herbhub-v2/
├── AGENTS.md                  # rules for the AI agent (Appendix A)
├── docker-compose.yml
├── .env.example               # copy to .env (never commit .env)
├── docs/ROADMAP.md            # this document
├── legacy/                    # V1 code, read-only reference
├── public/                    # web root: index.php, pages, admin/, api/, assets/, uploads/
├── src/                       # PSR-4 namespace App\
│   ├── Core/                  # Env, Database, Auth, Csrf, View, Lang, Mailer, Upload, Validator
│   ├── Repository/            # ProductRepo, OrderRepo, HerbRepo, QuizRepo, BatchRepo ...
│   ├── Service/               # CheckoutService, CouponService, SafetyService, MlClient ...
│   └── Payment/               # PaymentGateway interface, Stripe, Cod, BankTransfer, PayHere
├── templates/                 # partials + page templates
├── lang/                      # en.php, si.php
├── database/
│   ├── migrations/            # 000_v1_baseline.sql, 001_..., NNN_...
│   └── seed/                  # demo and catalog data
├── bin/                       # migrate.php, create_admin.php
├── ml/                        # FastAPI service: app/, tests/, requirements.txt, Dockerfile
└── tests/                     # PHPUnit
```

### Docker services

| Service | Image / role | Port |
|---|---|---|
| `web` | PHP 8.3 + Apache (pdo_mysql, gd, intl, mbstring), document root `public/` | 8080 |
| `db` | MySQL 8 with a named volume; user `app` (read/write) and `ml_ro` (read-only) | 3306 |
| `ml` | Python 3.12 + FastAPI (uvicorn) | 8000 |
| `mailpit` | Catches all outgoing mail in development | 8025 |

### .env keys

`APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (used once by `bin/create_admin.php`), `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET` (optional), `LLM_PROVIDER`, `LLM_API_KEY`, `LLM_MODEL`, `ML_URL`.

### Conventions (the agent must follow these)

- All SQL uses PDO prepared statements; user input is never concatenated into SQL.
- Passwords use `password_hash` and `password_verify`. Secrets are never logged, echoed or committed.
- Every POST form and state-changing `fetch` carries a CSRF token. Admin pages start with `Auth::requireAdmin()`.
- All output is escaped with `e()`. Uploads are validated (type, size) and renamed.
- Schema changes only through a new numbered migration file; applied migrations are never edited.
- Time zone Asia/Colombo, currency LKR ("Rs."), UI text through `t('key')` with `lang/en.php` and `lang/si.php`.
- JSON API format: `{"ok": true, "data": ...}` or `{"ok": false, "error": "..."}`.

---

## 5. Roadmap, team split and timeline

| Phase | Name | Weeks | Type | Main output |
|---|---|---|---|---|
| 0 | Foundation | 1 | Core | Docker skeleton, .env, migrations, base layout |
| 1 | Security and core fixes | 1–2 | Core | Safe auth, admin guards, reset by token, correct checkout, bug fixes |
| 2 | Commerce for Sri Lanka | 2–3 | Core | COD, bank transfer, delivery zones, coupons, order timeline and emails, invoice, wishlist |
| 3 | Herbal knowledge and bilingual search | 3–4 | Core | EN/SI content, herb ↔ product link, concerns, search, autocomplete, voice |
| 4 | Novelty A: Dosha quiz and safety checker | 4–5 | Core | Quiz and result, safety warnings, admin tools |
| 5 | Novelty B: QR traceability and AI assistant | 5–6 | Core | Batches, QR labels, verify page, chat widget with guarded answers |
| 6 | Python intelligence service | 6–7 | Core + stretch | Recommender, forecast, (stretch) leaf identification |
| 7 | Admin analytics, PWA, quality | 7–8 | Core | Dashboard, exports, reviews, PWA, tests, CI, README, demo data |

### Suggested split for 4 members

| Member | Owns | Also |
|---|---|---|
| **M1 · Platform and payments** | Phase 0 (lead), 1, 2 (payments, orders, coupons) | CI, Docker help for everyone |
| **M2 · Knowledge and search** | Phase 3 (bilingual, herbs, search), assistant in Phase 5 | Sinhala content review |
| **M3 · Trust and safety** | Safety checker (Phase 4), QR and batches (Phase 5), admin batch screens | Practitioner review of safety data |
| **M4 · Data and AI** | Dosha quiz (Phase 4), Python service (Phase 6), analytics (Phase 7) | Evaluation report |

Phase 0 is done together (pair up) so everyone has a working environment. Phase 7 is shared: everyone tests, writes documentation and prepares the demo.

### Timeline (8 weeks, stretch or shift to fit your deadline)

| Week | Focus |
|---|---|
| 1 | Phase 0 complete; Phase 1 starts (auth, admin guards) |
| 2 | Phase 1 complete (checkout, uploads, bug fixes); Phase 2 starts |
| 3 | Phase 2 complete; Phase 3 starts (bilingual data, herb ↔ product) |
| 4 | Phase 3 complete (search); Phase 4 starts (quiz, safety) |
| 5 | Phase 4 complete; Phase 5 starts (batches, QR) |
| 6 | Phase 5 complete (assistant); Phase 6 starts (recommender) |
| 7 | Phase 6 complete (forecast, optional leaf ID); Phase 7 starts |
| 8 | Phase 7 complete; full test, README, demo rehearsal |

---

## 6. Phases

## PHASE 0 · Foundation (Week 1, Core)

**Goal.** A runnable V2 skeleton that all four members start with one command, plus the shared building blocks every later phase uses.

### Tasks

- [ ] Create the folder layout from Section 4. Put V1 in `legacy/` (read-only).
- [ ] `docker-compose.yml` with web (PHP 8.3 + Apache), db (MySQL 8 + volume), ml (Python 3.12 + FastAPI with `GET /health`) and mailpit.
- [ ] Composer packages: vlucas/phpdotenv, phpmailer/phpmailer, stripe/stripe-php, endroid/qr-code, dompdf/dompdf; dev: phpunit/phpunit. Autoload namespace `App\` from `src/`.
- [ ] `.env.example` with every key from Section 4. The real `.env` is git-ignored.
- [ ] `src/Core`: Env, Database (PDO, exceptions on, utf8mb4, native prepared statements), Csrf, View (template render plus `e()` escape helper), Lang (`t('key')`, session language), Auth (stub, finished in Phase 1).
- [ ] `database/migrations/000_v1_baseline.sql` created from `legacy/suwodaDB.sql`: **schema only**. Move catalog, herb and blog rows to `database/seed/`. Remove all user rows and the plain-text admin row.
- [ ] `bin/migrate.php` applies new migration files in order and records them in `schema_migrations`.
- [ ] Base layout `templates/partials/header.php` and `footer.php` that keeps the V1 green theme and logo (Bootstrap 5.3, Bootstrap Icons).
- [ ] `GET /api/health` (PHP) and `GET /health` (ml) return `{"ok": true}`.

### Data model changes

- `schema_migrations(version, applied_at)`. No other change.

### Pages and endpoints

- `/` placeholder home page, `/api/health`, ml `GET /health`, Mailpit inbox on port 8025.

### Acceptance criteria (all must pass before the next phase)

- `docker compose up -d --build` starts all four services without errors and the site opens at http://localhost:8080.
- Running `php bin/migrate.php` twice gives no change the second time.
- No password, API key or token is present in the V2 repository (`.env` is ignored).
- `composer test` runs and one smoke test passes.

### Antigravity prompt (paste after the common preface)

> Set up the V2 skeleton exactly as in Phase 0: Docker Compose with web (PHP 8.3 + Apache), db (MySQL 8), ml (FastAPI /health) and mailpit; Composer packages and PSR-4 autoload; src/Core classes (Env, Database, Csrf, View, Lang, Auth stub); .env.example; migration runner and 000_v1_baseline.sql built from legacy/suwodaDB.sql with schema only; base header/footer layout in the V1 green theme. Do not implement any feature yet.

---

## PHASE 1 · Security and core fixes (Weeks 1–2, Core)

**Goal.** Close every security hole found in V1 and make checkout correct, so the rest of the roadmap is built on a safe base.

### Tasks

- [ ] **Auth.** Only `password_hash` / `password_verify`; no plain-text fallback; never log passwords; `session_regenerate_id(true)` on login; cookie flags HttpOnly and SameSite=Lax.
- [ ] **Admin account.** Keep the separate admin table with hashed passwords. Create the first admin with `bin/create_admin.php` using `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`, never from SQL.
- [ ] **Guards.** `Auth::requireUser()` and `Auth::requireAdmin()` at the top of every protected page and API route. Unauthenticated admin requests redirect to login.
- [ ] **Login throttling.** After 5 failed tries per email + IP, lock for 15 minutes (`login_attempts`).
- [ ] **Password reset.** `password_resets` with a hashed token, 30-minute expiry, single use, link sent by email. Remove the old "email + new password" form.
- [ ] **CSRF and validation.** Token on every POST form and state-changing fetch; a small Validator; admin delete actions become POST.
- [ ] **Uploads.** One Upload helper: jpg / png / webp only, max 2 MB, MIME check with finfo, random file name.
- [ ] **Secrets.** Gmail and Stripe credentials only from `.env`.
- [ ] **Checkout correctness.** Order starts as `payment_status = unpaid`. Stock is reduced inside a DB transaction (`SELECT ... FOR UPDATE`) only when payment is confirmed (Stripe webhook `checkout.session.completed`) or the order is cash on delivery (Phase 2). Purchased items leave the cart. Success and cancel URLs come from `APP_URL`.
- [ ] **Bug fixes.** Home page review text (`comment`), `average_order_value` in the report classes, delete `test_order_status.php`.

### Data model changes

- `login_attempts(id, email, ip_hash, attempted_at, success)`
- `password_resets(id, user_id, token_hash, expires_at, used_at, created_at)`
- `orders`: add `payment_status ENUM('unpaid','paid','failed','refunded') DEFAULT 'unpaid'`, `payment_method ENUM('card','cod','bank_transfer','payhere')`, `stripe_session_id VARCHAR(255) NULL`
- `user.status` gains `'blocked'`. V2 starts with a clean user table; demo users come from the seed script with hashed passwords.

### Pages and endpoints

- `/login`, `/register`, `/verify`, `/forgot-password`, `/reset-password?token=`, `POST /webhooks/stripe`; every `/admin/*` page guarded.

### Acceptance criteria (all must pass before the next phase)

- Opening any `/admin/*` URL without an admin session redirects to login (check with curl).
- Password reset works only with the emailed one-time link; an expired or reused token is rejected.
- Five wrong passwords lock the account for 15 minutes.
- The password column contains only hashes.
- An unpaid Stripe session never reduces stock. After the webhook the order is paid and stock drops once; replaying the webhook does not reduce it again.
- PHPUnit tests exist for Auth, Csrf, Upload and the checkout service.

### Antigravity prompt (paste after the common preface)

> Implement Phase 1 (security and core fixes) on top of the Phase 0 skeleton. Rebuild registration, email verification, login, logout, forgot/reset password (token based), login throttling, Auth::requireUser/requireAdmin guards, CSRF, Validator and Upload helper as specified. Port the V1 checkout to a CheckoutService: unpaid order first, Stripe webhook marks it paid and reduces stock in a transaction, cart cleanup, URLs from APP_URL. Add the migrations for login_attempts, password_resets and the new orders columns. Write PHPUnit tests for each acceptance criterion.

---

## PHASE 2 · Commerce for Sri Lanka (Weeks 2–3, Core)

**Goal.** Make buying realistic for Sri Lankan customers: cash on delivery and bank transfer next to card, delivery fees by district, coupons, an order timeline with emails, and a wishlist.

### Tasks

- [ ] **Order snapshot.** Copy shipping name, phone, address and district into the order at checkout.
- [ ] **Delivery fee.** `delivery_zone` with a fee and a free-delivery threshold per district; shown in cart and checkout.
- [ ] **Payments.** `PaymentGateway` interface with StripeGateway, CodGateway and BankTransferGateway (customer uploads a slip, admin verifies and marks paid). PayHereGateway (sandbox) is a stretch item.
- [ ] **Coupons.** Admin CRUD; percent or fixed value, minimum order, usage limit, date range; one coupon per order.
- [ ] **Sale prices.** Keep `product.price` as the base price and compute the sale price from `discount` at read time (V1 overwrote price and kept original_price). Discounts get start and end date-time for flash sales.
- [ ] **Order timeline.** `order_status_history`; statuses Pending → Confirmed → Packed → Out for Delivery → Delivered, plus Cancelled. Customer can cancel while Pending. One email per status change (PHPMailer; Mailpit in development). The order page shows the timeline.
- [ ] **Invoice.** PDF invoice per order with dompdf.
- [ ] **Wishlist and back-in-stock.** Heart button on products; "Notify me" on out-of-stock products; when admin raises stock from 0, send emails and set `notified_at`.

### Data model changes

- `orders`: add shipping_name, shipping_phone, shipping_address, district, delivery_fee, discount_total, coupon_id; status enum extended with Packed and Cancelled
- `delivery_zone(zone_id, district UNIQUE, fee, free_over)`
- `coupon(coupon_id, code UNIQUE, type ENUM('percent','fixed'), value, min_order, max_uses, used_count, starts_at, ends_at, active)` and `coupon_redemption(id, coupon_id, user_id, order_id, redeemed_at)`
- `order_status_history(id, order_id, status, note, changed_by_admin_id NULL, created_at)`
- `bank_slip(slip_id, order_id, file_path, status ENUM('submitted','verified','rejected'), reviewed_by NULL, created_at)`
- `wishlist(user_id, product_id, created_at)` and `stock_alert(alert_id, user_id, product_id, created_at, notified_at NULL)`
- `discount.start_date` and `end_date` become DATETIME

### Pages and endpoints

- `/checkout`, `/orders`, `/orders/{id}` (timeline + invoice), `/wishlist`; admin: coupons, delivery zones, bank slips, order status.

### Acceptance criteria (all must pass before the next phase)

- A cash-on-delivery order is created as unpaid, stock drops at placement, and marking it Delivered sets paid.
- The delivery fee changes with the district; orders above free_over show free delivery.
- A coupon is rejected after max_uses or after ends_at; the discount shows on the invoice.
- Each status change adds one history row and sends one email.
- Restocking a product with waiting users sends exactly one email per user.
- A test proves the catalog price equals base price minus the active discount while `product.price` in the database is unchanged.

### Antigravity prompt (paste after the common preface)

> Implement Phase 2. Add the migrations listed, the PaymentGateway interface with Stripe, COD and BankTransfer gateways, delivery zones, coupons, sale price calculation at read time, order_status_history with emails and cancellation, PDF invoice (dompdf), wishlist and back-in-stock alerts. Update checkout to snapshot shipping data. Admin screens for coupons, zones, bank slips and order status. Skip PayHere unless I ask. Add tests for coupon rules, fee calculation and stock alerts.

---

## PHASE 3 · Herbal knowledge and bilingual search (Weeks 3–4, Core)

**Goal.** Connect the herbal dictionary to the shop, make the whole site truly Sinhala and English, and make search understand herbs, symptoms and typos.

### Tasks

- [ ] **Bilingual data.** Add Sinhala columns; admin forms get a Sinhala field beside each English field; language switch (EN / SI) stored in session and cookie; UI text through `t()`; Noto Sans Sinhala web font. Seed Sinhala names for the herbs already used in V1 (for example the "Top 10 Ayurvedic Herbs" blog). A native speaker must review all Sinhala text; machine translation is only a draft. Missing translations fall back to English.
- [ ] **Herb ↔ product link.** `product_herb`; the admin product form gets a herb multi-select; herb page `/herb/{id}` shows uses, scientific name and "Products with this herb".
- [ ] **Health concerns.** `health_concern` and `herb_concern`; one landing page per concern (for example cold and cough, digestion, skin, hair, stress and sleep, joint comfort, immunity). Wording stays "traditionally used for", with no cure claims.
- [ ] **Dictionary upgrade.** One search box matching English name, Sinhala name, scientific name and concern; keep the A–Z filter; "Herb of the day" on the home page (chosen by date).
- [ ] **Search.** FULLTEXT indexes on product and herb with ranked results; use LIKE on the Sinhala columns (the built-in parser is weak for Sinhala) and check results by hand; `GET /api/search/suggest?q=` for autocomplete (debounced fetch); "Did you mean…?" using Levenshtein or SOUNDEX on product and herb names when there are no results; query words that match a concern show concern shortcuts.
- [ ] **Voice search.** Microphone button with the browser Web Speech API (en-US, si-LK where supported). Hide it when unsupported.

### Data model changes

- `product`: product_name_si, description_si; `category`: name_si; `herb`: name_si, uses_si; `blog`: title_si, content_si
- `product_herb(product_id, herb_id)`; `health_concern(concern_id, slug UNIQUE, name_en, name_si, icon)`; `herb_concern(herb_id, concern_id)`
- FULLTEXT: `product(product_name, description)` and `herb(name, scientific_name, uses)`

### Pages and endpoints

- `/herb/{id}`, `/concern/{slug}`, `/dictionary`, `/search`, `GET /api/search/suggest`, `POST /lang` (switch language).

### Acceptance criteria (all must pass before the next phase)

- Switching to Sinhala changes menus, product and category names and herb pages; nothing is blank.
- A product page lists its herbs and a herb page lists its products.
- Searching "kohomba", "neem" and "Azadirachta" finds the same herb; a typo like "tumeric" suggests turmeric.
- Autocomplete answers in under 300 ms on the seeded data.
- Every concern page shows the educational-use disclaimer.

### Antigravity prompt (paste after the common preface)

> Implement Phase 3. Add Sinhala columns and the language switch (lang/en.php, lang/si.php, session + cookie), product_herb mapping in admin, herb and concern pages, dictionary search, FULLTEXT search with ranked results, LIKE for Sinhala columns, autocomplete endpoint with debounce, did-you-mean, and the Web Speech voice button. Seed Sinhala names for the existing herbs. Add tests for search ranking and fallback to English.

---

## PHASE 4 · Novelty A: Dosha quiz and safety checker (Weeks 4–5, Core)

**Goal.** Two features that make the shop personal and responsible: a Prakriti (dosha) quiz that drives recommendations, and a safety checker that warns before a customer buys.

### Tasks

- [ ] **Quiz content.** 12 questions (body frame, skin, appetite, digestion, sleep, energy, weather preference, mood under stress and similar) with 3 to 4 options; each option adds points to Vata, Pitta and Kapha. Text in EN and SI, editable by admin.
- [ ] **Scoring.** Sum points, convert to percentages, name the dominant dosha (or a two-dosha type when the top two are within 10 points). Result page: Chart.js chart, short description, lifestyle tips (admin-edited), products from `product_dosha`.
- [ ] **Access.** Guests can take the quiz and keep the result in the session; logged-in users can save it and retake it (history kept).
- [ ] **Use of the result.** Home page "Recommended for you" and the Phase 6 recommender use the latest result.
- [ ] **Disclaimer** "Educational only, not a medical diagnosis" on the quiz and result pages.
- [ ] **Safety checker** (rule: no health data is stored). A "Check safety" panel on cart and product pages with checkboxes: pregnant, breastfeeding, buying for a child under 12, diabetes medicine, blood-pressure medicine, blood thinners, surgery within 2 weeks, known allergy. It matches the answers against `herb_safety` for every herb in the products and shows warnings by severity (info, caution, avoid). Answers live in the session only (optional "remember on this device" with localStorage).
- [ ] **Checkout rule.** If an item has an avoid match for the answers given, the customer must tick "I have read the warnings" before paying.
- [ ] **Admin.** CRUD for flags and herb_safety entries. Each entry stores source_note and a published switch. A banner reminds admins that entries must be reviewed by a qualified practitioner before publishing.

### Data model changes

- `quiz_question(question_id, text_en, text_si, sort_order)`; `quiz_option(option_id, question_id, text_en, text_si, vata, pitta, kapha)`
- `quiz_result(result_id, user_id NULL, session_token, vata, pitta, kapha, dominant, created_at)`
- `product_dosha(product_id, dosha ENUM('vata','pitta','kapha'), strength TINYINT)`
- `safety_flag(flag_id, code UNIQUE, name_en, name_si)`; `herb_safety(id, herb_id, flag_id, severity ENUM('info','caution','avoid'), note_en, note_si, source_note, published)`

### Pages and endpoints

- `/quiz`, `/quiz/result`, `POST /api/quiz/submit`, `POST /api/safety/check`; admin: quiz editor, product-dosha mapping, safety flags and entries.

### Acceptance criteria (all must pass before the next phase)

- The same answers always give the same result and the percentages add up to 100.
- A logged-in user's saved result changes the products shown in "Recommended for you".
- Ticking "pregnant" with a cart item marked avoid for pregnancy shows a red warning and blocks payment until acknowledged.
- A test proves that only dosha scores are stored; no health answers from the safety panel reach the database.
- Unpublished safety entries never appear to customers.

### Antigravity prompt (paste after the common preface)

> Implement Phase 4 in two parts. Part A: the dosha quiz (question/option tables, scoring service with unit tests, result page with Chart.js, session or saved results, product_dosha mapping, admin editor, use of the result on the home page). Part B: the safety checker (safety_flag and herb_safety tables, SafetyService, session-only answers, warning badges on product and cart pages, checkout acknowledgement, admin CRUD with a published flag). Do not store health answers. Do not invent medical content: seed only the flag names and a few clearly marked sample entries.

---

## PHASE 5 · Novelty B: QR traceability and AI assistant (Weeks 5–6, Core)

**Goal.** Prove products are genuine with a QR code on every pack, and add a chat assistant that answers from the shop's own herb and product data.

### Tasks

- [ ] **Batches.** Admin creates a batch per product: batch code, manufactured and expiry dates, source location, supplier, lab report PDF, notes. Each batch gets a random public_token.
- [ ] **QR labels.** endroid/qr-code encodes `APP_URL/verify?t={public_token}`; admin downloads a printable label sheet (PDF) for the batch.
- [ ] **Verify page** (public, mobile friendly): product, batch, dates, source, lab report link and a status badge: Genuine, Expired or Recalled. Count scans; when one token is scanned from many different hashed IPs (for example more than 50) show "Possible duplicate QR, contact us".
- [ ] **Admin extras.** Recall switch per batch; dashboard list of batches expiring within 30 days.
- [ ] **Order link** (stretch). On confirmation allocate batches first-expiry-first-out and store batch_id on order_item, so the order page shows the batch the customer received.
- [ ] **Assistant widget.** Floating chat button on every page; fetch to `POST /api/assistant`; replies show text plus product cards (image, price, link).
- [ ] **Retrieval (RAG-lite).** AssistantService finds the best herbs, concerns and products for the question with the Phase 3 search, adds the herb_safety notes of those herbs, and puts only that context in the prompt. No vector database in this version.
- [ ] **LLM call.** Server-side cURL using `LLM_PROVIDER`, `LLM_API_KEY`, `LLM_MODEL` from `.env`. The key never reaches the browser.
- [ ] **System prompt rules.** Answer only from the supplied context; reply in the customer's language (English, Sinhala or Singlish); recommend only products from the context; never diagnose or promise cures; for pregnancy, children, serious symptoms or prescription medicine say to consult a doctor; end with a short disclaimer.
- [ ] **Validation.** The model returns JSON `{reply, product_ids[]}`; the server checks that every product ID exists and is in stock before showing cards.
- [ ] **Limits.** 500-character input cap, 20 messages per hour per IP, last 6 turns of history, 24-hour cache for repeated questions, 15-second timeout.
- [ ] **Fallback.** If the API fails or no key is set, return the top search matches and concern links with a polite message.
- [ ] **Admin review.** A list of questions with weak matches ("unanswered") to guide new content.

### Data model changes

- `product_batch(batch_id, product_id, batch_code UNIQUE, manufactured_on, expires_on, source_location, supplier_name, lab_report_path, notes, public_token CHAR(32) UNIQUE, status ENUM('active','recalled'), created_at)`
- `batch_scan(scan_id, batch_id, ip_hash, ua_hash, scanned_at)`; `order_item`: add batch_id NULL
- `chat_session(session_id, user_id NULL, created_at)`; `chat_message(message_id, session_id, role ENUM('user','assistant'), content, created_at)`; `assistant_cache(question_hash PK, answer_json, created_at)`

### Pages and endpoints

- `/verify?t=`, `POST /api/assistant`; admin: batches, QR label download, recall, "unanswered questions".

Note: `/verify?token=` is already used for email verification (Phase 1), so the two uses of `/verify` must be told apart by their parameter.

### Acceptance criteria (all must pass before the next phase)

- Scanning a QR with a phone opens the verify page; an expired batch shows "Expired", a recalled batch shows "Recalled".
- Tokens are random (128 bits or more), not sequential or guessable.
- "I feel stressed and cannot sleep, what do you have?" returns catalog products only, with a disclaimer; a Sinhala question gets a Sinhala answer.
- A question about pregnancy or a prescription drug triggers the "consult a doctor" line.
- Product IDs returned by the model that do not exist are dropped (unit test with a fake LLM client).
- With no API key the widget still answers through the fallback.

### Antigravity prompt (paste after the common preface)

> Implement Phase 5 in two parts. Part A: product_batch and batch_scan tables, admin batch screens, QR generation and printable label PDF, public /verify page with Genuine/Expired/Recalled status and duplicate-scan warning. Part B: the AssistantService (retrieval from the Phase 3 search, server-side LLM call configured from .env, JSON output validated against real product IDs, rate limit, cache, fallback), the /api/assistant endpoint and the floating chat widget with product cards. Write the system prompt as specified in the roadmap and unit-test it with a fake LLM client.

---

## PHASE 6 · Python intelligence service (Weeks 6–7, Core + stretch)

**Goal.** A separate FastAPI service that adds machine learning, while the PHP shop keeps working if the service is down.

### Tasks

- [ ] **Skeleton.** `ml/app/main.py`, `requirements.txt` (fastapi, uvicorn, pandas, scikit-learn, statsmodels, sqlalchemy, pymysql, pillow; add torch or tensorflow only for the stretch item), Dockerfile, pytest tests. Connects with the read-only user `ml_ro`.
- [ ] **PHP client.** MlClient with a 3-second timeout. Every call has a PHP fallback (popular products in the user's categories; moving average for sales).
- [ ] **Recommender (hybrid).** Content-based TF-IDF over product text, herbs, concerns and dosha; item-to-item co-purchase similarity from order_item; popularity from sales and ratings; boosts for user_category and the latest dosha result; cold start = popularity + chosen categories. Endpoints `GET /recommend/user/{id}?limit=8` and `GET /recommend/product/{id}?limit=6` ("similar products" and "customers also bought"). Data cached in memory and refreshed every 15 minutes.
- [ ] **Offline evaluation.** Leave-one-out precision@5 and recall@5 on past orders for the popularity baseline and the hybrid model, written to `ml/reports/recommender_eval.md` (useful for the university report).
- [ ] **Sales forecast.** `GET /forecast/sales?horizon=30` (Holt-Winters or ETS; moving-average baseline when there are fewer than 30 days of data) and `GET /forecast/product/{id}` returning expected daily demand and days until stock-out. Phase 7 uses it for low-stock alerts.
- [ ] **Leaf identification** (stretch). `POST /identify` (image up to 5 MB) returns the top 3 herbs with confidence. Fine-tune a small pretrained image model (for example MobileNet or EfficientNet) on a public medicinal-plant image dataset, limited to the herbs in your dictionary (start with 10 to 15 classes and check the dataset licence). Below 0.6 confidence answer "not sure". PHP page `/identify` shows the matched herb and its products with a note that photo identification is only a guide.

### Data model changes

- No MySQL schema change needed. Optional: `recommendation_log(id, user_id, product_id, source, shown_at, clicked_at)` to measure clicks.

### Pages and endpoints

- ml: `GET /health`, `GET /recommend/user/{id}`, `GET /recommend/product/{id}`, `GET /forecast/sales`, `GET /forecast/product/{id}`, `POST /identify`. PHP: `GET /api/recommendations`, `GET /api/products/{id}/similar`, `POST /api/identify` (proxy), home and product page sections.

### Acceptance criteria (all must pass before the next phase)

- With the ml container stopped, home and product pages still show recommendations (fallback) and no error is visible.
- A new user and a user with order history get different recommendations; a saved dosha result ranks matching products higher.
- `ml/reports/recommender_eval.md` exists with numbers for the baseline and the hybrid model.
- The forecast returns 30 daily values with a lower and upper range; tests cover the "too little data" path.
- (Stretch) Accuracy on a held-out test set is reported in `ml/reports/identify_eval.md`; images below the threshold return "not sure".

### Antigravity prompt (paste after the common preface)

> Implement Phase 6 in the ml/ folder and the PHP MlClient. Build the FastAPI endpoints for the hybrid recommender (TF-IDF content similarity, co-purchase, popularity, dosha and category boosts), the evaluation script that writes ml/reports/recommender_eval.md, and the sales and product forecast endpoints with a moving-average fallback. Add MlClient with timeout and PHP fallbacks, wire the home and product pages, and write pytest and PHPUnit tests. Do the leaf identification endpoint only after everything else passes and I confirm.

---

## PHASE 7 · Admin analytics, PWA and quality (Weeks 7–8, Core)

**Goal.** Turn the admin panel into a real dashboard, make the site installable, and finish with tests, documentation and a demo-ready dataset.

### Tasks

- [ ] **Admin dashboard.** KPI cards (today's revenue, pending orders, low stock, expiring batches, new customers); revenue trend with the forecast line from Phase 6; top products and category revenue; low-stock list using "days to stock-out"; CSV and PDF export of reports; order filters by date, status and payment method; user list with order history and block / unblock.
- [ ] **Reviews.** "Verified purchase" badge, helpful votes, up to 3 photos per review; optional daily "customers say" summary per product (stretch, LLM, cached).
- [ ] **Blog.** Edit posts, tags, and "related products" cards inside a post.
- [ ] **Seasonal picks.** Home page section from seasonal_collection (for example rainy season → cold and cough concern).
- [ ] **PWA.** `manifest.webmanifest`, icons 192 and 512, `service-worker.js` caching the app shell, dictionary pages and herb images, an offline page, install prompt.
- [ ] **Quality.** PHPUnit for the services (Auth, Checkout, Coupon, Safety, Assistant); pytest for ml; GitHub Actions (PHP lint, PHPUnit, pytest); indexes on all foreign keys and search columns; accessibility pass (labels, contrast, alt text); Lighthouse mobile check.
- [ ] **Docs and demo data.** README.md (setup, architecture, screenshots), ER diagram, `database/seed/demo.sql` with realistic products, herbs (EN and SI), batches, quiz, safety entries and 30 days of sample orders so charts and the recommender have data.

### Data model changes

- `review_helpful(review_id, user_id)`; `review_image(image_id, review_id, file_path)`
- `blog_tag(tag_id, name_en, name_si)`, `blog_tag_map(blog_id, tag_id)`, `blog_product(blog_id, product_id)`
- `seasonal_collection(id, name_en, name_si, start_mmdd, end_mmdd, concern_id)`

### Pages and endpoints

- `/admin/dashboard`, `/admin/reports/export`, `/blog/{id}`, `manifest.webmanifest`, `service-worker.js`.

### Acceptance criteria (all must pass before the next phase)

- Dashboard loads in under 2 seconds on the demo data and exports match the on-screen numbers.
- The site can be installed on a phone and the herb dictionary opens offline.
- CI is green on a fresh clone; `docker compose up`, `php bin/migrate.php` and the demo seed give a working demo.
- A review can only show "Verified purchase" if the user has a Delivered order for that product.

### Antigravity prompt (paste after the common preface)

> Implement Phase 7. Build the admin dashboard with KPI cards, Chart.js charts and the ML forecast line (use the fallback when ml is down), report exports, order filters and user block/unblock. Add verified-purchase reviews with helpful votes and photos, blog tags and related products, seasonal picks, the PWA files, GitHub Actions CI, the README and database/seed/demo.sql. Then run the full test suite and list any acceptance criterion that is not met.

---

## 7. Viva demo storyline and risks

A demo that follows the core idea is easier to follow than a list of features. About 8 minutes:

| Step | Show | Say |
|---|---|---|
| 1 | Home page, switch English ↔ Sinhala | The whole site, including medical terms, is bilingual and reviewed |
| 2 | Take the dosha quiz, open the result | Personalised products from the customer's own answers |
| 3 | Ask the assistant in Sinhala or Singlish | Answers come only from our herb and product data, with safety limits |
| 4 | Open a herb page, then the product that contains it | Dictionary and shop are one knowledge base |
| 5 | Cart → safety panel → warning appears | Responsible selling; health answers are not stored |
| 6 | Pay by COD, open the order timeline, receive the email in Mailpit | Local payment methods, tracked orders |
| 7 | Scan the QR on a pack with a phone | Genuine / expired / recalled, with batch and lab report |
| 8 | Admin dashboard with the forecast and low-stock list | Data-driven decisions; recommender evaluation numbers from `ml/reports` |

### Risks and how to handle them

| Risk | What to do |
|---|---|
| Medical claims and liability | Present the site as educational. Health text comes from admin-entered data reviewed by a qualified practitioner. The assistant never diagnoses and always shows a disclaimer. |
| Sinhala quality | Treat machine translation as a draft only; have a native speaker review product, herb and safety text. |
| Personal data | Keep data minimal: no health answers, hashed IPs in scan logs, password hashes only. Sri Lanka has a Personal Data Protection Act (2022), so mention data handling in your report. |
| LLM cost and outages | Rate limits, caching, short history, and a non-AI fallback (Phase 5). |
| Payment accounts | Use Stripe test keys and the PayHere sandbox for the demo. PayHere needs a merchant account, so keep it a stretch item. |
| Leaf identification accuracy and dataset licence | Keep it a stretch item, limit it to a few herbs, report accuracy honestly and show a confidence threshold. |
| Scope creep | Finish a phase's acceptance criteria before starting the next; leave stretch items for the end. |
| Agent mistakes | Review every migration and diff, run tests after each task, commit small, and keep AGENTS.md up to date when a rule keeps being broken. |

---

## Appendix A: AGENTS.md template

Save this as `AGENTS.md` in the repo root. Antigravity reads workspace rules from `AGENTS.md`, `GEMINI.md` or `.agents/rules/*.md`; keep it short and update it when you add a convention.

```
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
```

---

## Appendix B: Tables at a glance

Everything added to the V1 database, by phase. V1 tables that are not listed stay as they are.

| Phase | New tables | Changed tables |
|---|---|---|
| 0 | schema_migrations | all V1 tables imported as the baseline (schema only) |
| 1 | login_attempts, password_resets | orders (+ payment_status, payment_method, stripe_session_id); user.status (+ blocked) |
| 2 | delivery_zone, coupon, coupon_redemption, order_status_history, bank_slip, wishlist, stock_alert | orders (+ shipping fields, delivery_fee, discount_total, coupon_id; extra statuses); discount (DATETIME) |
| 3 | product_herb, health_concern, herb_concern | product, category, herb, blog (+ Sinhala columns); FULLTEXT indexes |
| 4 | quiz_question, quiz_option, quiz_result, product_dosha, safety_flag, herb_safety | none |
| 5 | product_batch, batch_scan, chat_session, chat_message, assistant_cache | order_item (+ batch_id) |
| 6 | optional recommendation_log | none |
| 7 | review_helpful, review_image, blog_tag, blog_tag_map, blog_product, seasonal_collection | none |

Added by the fixes after Phase 3 (not in the original plan): `orders.stock_deducted` and `user.lang`.

## Appendix C: API summary

PHP endpoints return `{"ok": true, "data": ...}`. "Rate-limited" endpoints use the limits in the phase description.

| Method | Path | Access | Phase |
|---|---|---|---|
| GET | `/api/health` | public | 0 |
| POST | `/webhooks/stripe` | Stripe signature | 1 |
| GET | `/api/search/suggest?q=` | public | 3 |
| POST | `/api/quiz/submit` | public (session) | 4 |
| POST | `/api/safety/check` | public (session only, nothing stored) | 4 |
| GET | `/verify?t=` | public page | 5 |
| POST | `/api/assistant` | public, rate-limited | 5 |
| GET | `/api/recommendations` | user (guest gets popular items) | 6 |
| GET | `/api/products/{id}/similar` | public | 6 |
| POST | `/api/identify` | public, rate-limited (proxy to ml) | 6 stretch |
| GET | `/admin/reports/export` | admin | 7 |
| GET (ml) | `/health`, `/recommend/user/{id}`, `/recommend/product/{id}` | internal network only | 6 |
| GET (ml) | `/forecast/sales`, `/forecast/product/{id}` | internal network only | 6 |
| POST (ml) | `/identify` | internal network only | 6 stretch |
