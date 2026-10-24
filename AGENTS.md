# AGENTS.md

Workspace instructions for AI agents working in this repository (OpenPlan Work / "LazyMan Tools").
Read this first, then consult the authoritative spec docs below for non-trivial changes.

## What this is

A self-hosted, **zero-knowledge encrypted** PHP business suite: tasks, projects, CRM (clients),
invoicing, finance, inventory, notes, habits, todos, meetings, tickets, payments/subscriptions,
and an autonomous AI agent + native MCP server. No SQL database — all data is AES-256-GCM
encrypted JSON under `data/`. Local-first, shared-hosting deployable.

- **Backend**: PHP 8.0+, no framework, no Composer runtime deps. Required exts: `json`, `mbstring`, `openssl`, `curl`.
- **Frontend**: vanilla JS (`assets/js/app.js`) + Tailwind CSS (CDN) + Inter font + **Heroicons inline SVG only**.
- **MCP server**: Node 18+ ESM in `mcp-server/index.js` (stdio, `@modelcontextprotocol/sdk`).
- **Mobile**: `mobile/` is a parallel PHP view tree; `flutter_app/` is a separate Flutter client; `alexa-skill/` for Alexa.

## Authoritative docs (read before sensitive edits)

When these disagree with any other file (including CLAUDE.md), **the spec docs win** — they are evidence-based (`file:line`). Update them when behavior changes.

- `docs/PRD.md` — product scope, personas, what & why
- `docs/TRD.md` — technical contract: classes, schemas, APIs, encryption, request lifecycle (the source of truth)
- `docs/DESIGN.md` — UX flows, routing, layout, theming
- `docs/mobile/` — mobile-specific design notes
- `CLAUDE.md` — detailed API tables, data models, icon reference, recovery-fix history (longer/more verbose than this file)

## Layout

- `config.php` — bootstrap; loads `.env`/`.env.local`, defines constants (`APP_*`, paths), starts session. Always `require_once` it first.
- `includes/` — all PHP classes (`Database`, `Encryption`, `Auth`, `BaseAPI`, `*API`, AI providers, MCP, Mailer, etc.). autoloaded via composer classmap.
- `api/*.php` — thin endpoint files: auth → CSRF → instantiate `*API($db, $collection)` → switch on method/action. Standard JSON envelope via `successResponse()` / `errorResponse()`.
- `views/` — desktop PHP pages; `mobile/views/` — mobile pages; `views/layouts/` — `main.php`, `auth.php`.
- `index.php` — front controller; routes via `?page=`; auth gating + login/master-password two-step flow lives here.
- `data/` — **gitignored, encrypted at rest**. Per-user: `data/users/{userId}/*.json.enc`. `data/config.json.enc` = business settings. `data/PROTECTED.md` + `data/.htaccess` are the only committed files.
- `test/`, `scripts/`, `mcp-server/`, `flutter_app/`, `alexa-skill/`, `release-artifacts/` — see commands below.

## Common commands

```bash
# QA (composer scripts; run from repo root). vendor/bin must exist (composer install).
composer qa:test        # PHPUnit, delegates to scripts/run_phpunit.php (auto-picks a PHP with openssl)
composer qa:stan        # PHPStan level 5 (only Auth/Database/TasksAPI + tests are in scope — see phpstan.neon)
composer qa:cs          # php-cs-fixer dry-run on a specific allowlist
composer qa:all         # test + stan + cs

# Direct
php scripts/run_phpunit.php
vendor/bin/phpstan analyze              # respects phpstan.neon
php test/smoke_test_comprehensive.php   # manual smoke harness
node test/mcp_channel_smoke_nosdk.mjs   # MCP channel smoke (needs API_URL/MASTER_PASSWORD env)

# Local dev server
php start_server.php        # or start_server.bat on Windows

# Clean release zip (strips data/secrets/agent folders) — see docs, release-export API
```

Note: `test/`, `phpunit.xml`, `phpstan.neon` are gitignored — they live locally only.

## Architecture & layer rules (matters for edits)

- **Data is per-user.** `new Database(getMasterPassword(), Auth::userId())`. Never read/write `data/` files directly — always go through `Database`. Each collection is encrypted JSON.
- **Encryption**: AES-256-GCM, key = `SHA256(masterPassword, raw=true)`. Stored as base64(IV + Tag + ciphertext). `Encryption::hashPassword` = bcrypt cost 12.
- **Auth**: PHP sessions (HTTP-only, SameSite=Strict) + bcrypt + 32-byte CSRF token. **CSRF is required on all non-GET requests** unless `Auth::isTokenAuth()` (MCP/token bridge). Always re-validate `Auth::check()` at the top of endpoints.
- **New collection/API?** Subclass `BaseAPI` (`getAllowedFields()` whitelists fields), add an `api/<name>.php` endpoint following the `tasks.php` pattern (auth → CSRF → switch on method/action → `successResponse`/`errorResponse`).
- **Tasks are embedded inside project records**, not a separate collection (see TRD data model). `TasksAPI` operates on the `projects` collection.
- **AI providers** (Groq, OpenRouter, Gemini, Ollama) are BYO key stored in encrypted settings. The AI agent (`includes/AIAgent.php`) has function-calling + RAG-lite over Knowledge Base folders.

## Conventions & gotchas

- **Icons: Heroicons outline inline SVG only.** Never icon fonts. Standard wrapper:
  `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..."/></svg>`.
- **PHP/JS variable mixing bug (mobile views)**: define JS consts from PHP once (`const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';`), then reference the JS variable — never write `<?= CSRF_TOKEN ?>` inside JS. See CLAUDE.md "Common Pitfalls".
- **API response envelope** is fixed: `{ success, data, message, timestamp }`. Frontend uses the `api.get/post/put/delete` helper in `app.js` and injects `csrf_token: CSRF_TOKEN`.
- **Toasts/modals**: `showToast(msg, type)`, `openModal(html)`, `confirmAction(msg, fn)`.
- **Secrets**: `.env`, `.mcp.json`, `includes/master_password.php`, `data/` are gitignored and must never be committed. The master password unlocks all data — never log or echo it. Do not paste real credentials into docs/code.
- **FTP deploy gotcha (if deploying to host)**: the FTP account is chrooted into the web root — upload paths are relative to `/`; do **not** prepend `public_html/` and never `--ftp-create-dirs` for existing dirs. Always LIST-then-upload-then-verify. (Full details in CLAUDE.md.)
- **Logging**: errors go to `data/php_error.log`; check it first for 500s.

## Sensitive areas — update docs when changing

Encryption (`includes/Encryption.php`, `Database.php`), auth/session/CSRF (`includes/Auth.php`,
`index.php` login + master-password flow), the data schema (TRD §data models), the MCP
server tool surface (`mcp-server/index.js`), or the release-export allowlist (`api/release-export.php`,
`api/release-download.php`). Each of these has corresponding sections in TRD/DESIGN/CLAUDE.md that must stay in sync.
