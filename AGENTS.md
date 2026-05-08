# AGENTS.md - MinC Auto Supply

## Project Overview
PHP auto parts e-commerce site. No framework, no composer. Deploys to InfinityFree hosting via FTP.

## Key Commands & Setup
- **Database setup**: Run `SETUP_DATABASE.sql` in phpMyAdmin, or visit `setup/setup_chat.php` in browser for chat tables
- **Email tests**: `php tests/TEST_EMAIL.php` (requires Mailtrap SMTP configured in `config/email_config.php`)
- **No build step** — plain PHP, served directly

## Architecture
```
index.php              → Homepage (customer-facing)
admin_dashboard.php    → Redirects to app/frontend/dashboard.php
backend/               → API endpoints (auth, cart, checkout, products, orders, etc.)
html/                  → Frontend pages (product.php, checkout.php, profile.php, etc.)
html/components/       → Shared UI (navbar.php, footer.php, chat_bubble.php)
app/frontend/          → Admin dashboard pages
config/                → email_config.php, payment_config.php
library/               → PHP classes (EmailService, EmailVerificationHelper, TokenGenerator)
database/              → connect_database.php (creates $connection + $pdo), migrations, MinC.sql
setup/                 → One-time setup scripts (setup_chat.php)
tests/                 → Manual diagnostic scripts (TEST_*.php)
```

## Critical Facts
- **Dual DB connections**: `database/connect_database.php` creates both `$connection` (MySQLi procedural) and `$pdo` (PDO). Most backend files use PDO.
- **Database host**: InfinityFree (`sql107.infinityfree.com`). Credentials in `database/connect_database.php` — **do not commit real creds**.
- **Deployment**: Push to `master` triggers FTP deploy via `.github/workflows/deploy.yml`. Excludes `.git*`, `.github/`, `tests/`.
- **User levels**: 1=Admin, 2=Employee, 4=Customer. Legacy level 3 merged into Employee. Admin access = level <= 2.
- **Email**: Uses Mailtrap sandbox for testing (`sandbox.smtp.mailtrap.io`). Switch to real SMTP for production.
- **No composer** — PHPMailer must be manually included if used.

## Conventions
- Backend APIs return JSON. Frontend pages are PHP with inline HTML/JS.
- Auth checked via `$_SESSION['user_id']` and `$_SESSION['user_level_id']`.
- Color theme: teal `#08415c` / `#0a5273`. Tailwind CSS via CDN.
- Alerts use SweetAlert2 (CDN).

## Testing
- Tests are manual diagnostic scripts in `tests/`, not a test suite. Run individually with `php tests/TEST_*.php`.
- No automated test framework.

## Chat System
- **Real-time via SSE**: `backend/chat/sse.php` streams `new_message` and `new_conversation` events
- **500ms DB polling loop** inside SSE, 30s timeout with auto-reconnect
- **Fallback**: After 3 consecutive SSE failures, degrades to 3s polling
- **Customer side**: `chat_bubble.php` uses EventSource, incrementally appends messages
- **Admin side**: `chat-admin.php` has two SSE streams — conversation list + current messages

## Gotchas
- `backup_stuff.sh` is from a different project — do not trust its patterns for this repo.
- No `.env` file — config is in PHP `define()` constants.
- Session-based auth, no JWT or API tokens.
