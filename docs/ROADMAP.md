# BUILD ROADMAP · FOR GOOGLE ANTIGRAVITY
# Suwoda HerbHub V2
## Novelty features + modern stack, built phase by phase
*Know your body → Find the right herb → Verify it is genuine → Buy safely*
November 2026 · Planning document, adjust phases to your own deadline

---

### At a glance
- **Project**: Suwoda HerbHub: herbal and Ayurvedic e-commerce site for Sri Lanka (V1 was a 4-member university project)
- **This document**: A build plan for V2: a new, separate build that keeps what V1 does, fixes its weak points, and adds novelty features and newer technologies
- **Core stack**: PHP 8.3 + MySQL 8 + Bootstrap 5 + JavaScript (fetch, Chart.js), packaged with Docker Compose
- **Added technologies**: Python FastAPI service (recommender, forecast, optional leaf identification), LLM API (assistant), QR codes, PDF invoices, PWA
- **Novelty features**: Dosha quiz · Safety checker · QR authenticity and batch traceability · AI Herbal Assistant · Hybrid recommender · Bilingual (EN/SI) herb search · (stretch) Leaf identification
- **Build method**: One phase at a time inside Google Antigravity. Every phase has tasks, data model changes, acceptance criteria and a ready-to-paste prompt
- **Size**: 8 phases, about 8 weeks for a team of 4 (see Section 5)

---

## 1. How to use this with Antigravity
- **Create a new repo**: Make a folder `suwoda-herbhub-v2/`, run `git init`, and copy the V1 project into `legacy/` as read-only reference. Remove real passwords and keys from the copy first.
- **Add the rules file**: Put `AGENTS.md` (template in Appendix A) in the repo root. Antigravity loads workspace rules from `AGENTS.md`, `GEMINI.md` or `.agents/rules/*.md`.
- **Add this roadmap**: Save the text of this document as `docs/ROADMAP.md` so the agent can read the phase it is working on.
- **One phase = one agent conversation**: Start with the common preface below, then paste the prompt from that phase.
- **Plan first, then code**: Ask the agent for an implementation plan, read it, correct it, and only then approve. After it finishes, read the diff and walkthrough, run the tests, and tick the acceptance criteria yourself.
- **Commit after every phase** (for example `phase3: bilingual content and search`). Do not start the next phase until the current one passes.
- **Secrets stay in .env**: Never paste real API keys, passwords or customer data into a prompt.

### Common preface (paste before every phase prompt)
> Read AGENTS.md and docs/ROADMAP.md. We are working on PHASE N only (replace N). First produce an implementation plan: files to create or change, migrations, risks and open questions, then wait for my approval. After approval, implement the tasks, add tests, run them, and finish with a short walkthrough that lists what changed and which acceptance criteria are met. Do not change code that belongs to other phases. If something in the roadmap is unclear, ask instead of guessing.

### Tips that save time
- Keep prompts small. If a phase feels too big for one session, split it by its task list.
- Ask the agent to run `docker compose exec web composer test` and show the result. Do not accept "should work" as an answer.
- Review SQL migrations by hand. They are the part that is hardest to undo.
- Health, safety and dosha text must come from admin-entered data and be checked by a qualified person. The agent must not invent medical claims.

---

## 2. V1 snapshot and known problems
### Technology used in V1
- **Backend**: Plain PHP with OOP classes in `classes/`, PDO; database connection through `DbConnector` (`includes/dbconnect.php`)
- **Database**: MySQL / MariaDB, 12 tables (`suwodaDB.sql`)
- **Frontend**: Bootstrap 5.3 and Bootstrap Icons from CDN, a little vanilla JS, Chart.js on the sales report page
- **Payments**: Stripe Checkout (card only, LKR)
- **Email**: PHPMailer through Gmail SMTP (account verification mail)
- **Language**: Google Translate widget (English, Sinhala, Tamil)
- **Libraries**: Composer packages shipped as `vendor.zip`

### Roles and modules in V1
- **Guest**: Register (email verification), browse categories and products, search, herbal dictionary, blog
- **Registered user**: Login, choose preferred categories, cart, Stripe checkout, view orders, review a product after it is Delivered, profile
- **Admin**: Manage categories, products and discounts, users (view only), orders (status), dictionary (herbs), blogs; sales reports (daily / weekly / monthly)

### Known problems in V1 (do not carry these into V2)
1. Gmail app password and Stripe secret key are hard-coded in source.
2. Anyone can reset any account's password by typing only the email (no token, no email link).
3. Admin pages have no login check (only session_start); the admin password is stored as plain text.
4. Login accepts a plain-text password as a fallback; passwords are written to the error log.
5. Order is created and stock is reduced before payment is confirmed; cart is not cleared; localhost URLs are hard-coded for Stripe.
6. Delete actions use GET links (no CSRF protection); uploaded files keep the client's file name and have weak/no type checks.
7. Review text is blank on the home page (code reads review_text, column is comment).
8. average_order_value is read by the report page but never calculated (always 0).
9. A leftover test script sits in the project root (`test_order_status.php`).
10. "Recommended for you" is only the first 5 products of the chosen categories; search is LIKE; dictionary is not linked to products; Google Translate mangles medical terms.

---

## 3. Goals and novelty features
- **Trustworthy**: Secure login and admin, payments that are only marked paid when they really are, data that can be verified.
- **Local**: Sinhala and English, cash on delivery and bank transfer next to card, delivery fee by district.
- **Novel**: Features that ordinary herbal shops do not have, all connected through the same herb and product data.

### Novelty features
1. **Dosha (Prakriti) quiz** (Phase 4): 12-question quiz, Vata / Pitta / Kapha scores, personalised product list.
2. **Safety checker** (Phase 4): Customer ticks pregnancy, breastfeeding, child, medicines... and sees herb warnings before paying. Nothing health-related is stored.
3. **QR authenticity and batch traceability** (Phase 5): QR on each pack opens a public page with batch, dates, source and lab report; flags duplicates and recalls.
4. **AI Herbal Assistant** (Phase 5): Chat in English, Sinhala or Singlish; answers only from your own herb and product data; shows product cards. Grounded (RAG-lite) so it cannot invent products; guarded for medical questions.
5. **Hybrid recommender** (Phase 6): Content + co-purchase + popularity + dosha + preferred categories, with offline evaluation.
6. **Sales and stock forecast** (Phase 6, 7): 30-day sales forecast, "days to stock-out" per product.
7. **Bilingual herb search** (Phase 3): Search by English, Sinhala or scientific name or by concern; autocomplete; "did you mean"; voice search.
8. **Leaf identification (stretch)** (Phase 6): Upload a leaf photo, get the likely herb and where to buy.
9. **Replenishment, wishlist, PWA** (Phase 2, 7): Back-in-stock alerts, installable app, offline dictionary.

*Scope rule: Core = phases 0 to 5, the recommender and forecast from phase 6, and phase 7. Stretch = leaf identification, PayHere, "customers say" review summaries, loyalty points. Do the stretch items only after the core passes.*

---

## 4. Architecture and conventions
PHP stays the main application. Machine-learning work lives in a separate Python service that PHP calls over HTTP and JSON. If that service is down, PHP falls back to simple SQL logic, so the shop keeps working.

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
- `web`: PHP 8.3 + Apache (pdo_mysql, gd, intl, mbstring), document root `public/` (Port 8080)
- `db`: MySQL 8 with a named volume; user `app` (read/write) and `ml_ro` (read-only) (Port 3306)
- `ml`: Python 3.12 + FastAPI (uvicorn) (Port 8000)
- `mailpit`: Catches all outgoing mail in development (Port 8025)

---

## 5. Phases Summary
- **Phase 0**: Foundation (Docker skeleton, .env, migrations, base layout)
- **Phase 1**: Security and core fixes (Safe auth, admin guards, reset by token, correct checkout, bug fixes)
- **Phase 2**: Commerce for Sri Lanka (COD, bank transfer, delivery zones, coupons, order timeline and emails, invoice, wishlist)
- **Phase 3**: Herbal knowledge and bilingual search (EN/SI content, herb ↔ product link, concerns, search, autocomplete, voice)
- **Phase 4**: Novelty A: Dosha quiz and safety checker (Quiz and result, safety warnings, admin tools)
- **Phase 5**: Novelty B: QR traceability and AI assistant (Batches, QR labels, verify page, chat widget with guarded answers)
- **Phase 6**: Python intelligence service (Recommender, forecast, stretch: leaf identification)
- **Phase 7**: Admin analytics, PWA, quality (Dashboard, exports, reviews, PWA, tests, CI, README, demo data)
