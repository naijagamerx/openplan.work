# OpenPlan Work — Technical Requirements Document (TRD)

**Version:** 1.0.0
**Last Updated:** 2026-05-28
**Status:** Authoritative — synthesized from live codebase analysis on 2026-05-28
**Scope:** Implementation contract for engineers and AI agents working on this repo.

> This TRD is **evidence-based**. Every claim cites `file:line` from the codebase as it existed during analysis. If you change the code, update the cited section.

---

## 1. Stack & Runtime

| Layer | Choice |
|---|---|
| Language | PHP 8.0+ (no framework) + Node.js 18+ (MCP only) |
| Frontend | Vanilla JS (`assets/js/app.js`), Tailwind CSS via CDN, Inter font, Heroicons (inline SVG) |
| Storage | Flat encrypted JSON files (`data/*.json.enc`, per-user `data/users/{userId}/*.json.enc`) |
| Encryption | AES-256-GCM, key = `SHA256(masterPassword, raw=true)` |
| Auth | PHP sessions, bcrypt(cost=12), 32-byte CSRF token |
| AI Providers | Groq, OpenRouter, Gemini, Ollama (all BYO key) |
| MCP | `@modelcontextprotocol/sdk@0.6.0`, stdio transport |
| Web Server | Apache + `mod_rewrite` recommended (Nginx works with manual rewrite) |
| Required PHP exts | `json`, `mbstring`, `openssl`, `curl` |

---

## 2. Directory Layout (authoritative)

```
taskmanager/
├── api/                     # 50 REST endpoints (top-level *.php) + oauth/ subdir
├── includes/                # 41 PHP classes (core + domain APIs + integrations)
├── views/                   # 81 desktop view templates (top-level *.php; ~36K LOC)
│   ├── layouts/             # main.php, auth.php, three-pane.php
│   └── partials/            # header.php, sidebar.php
├── mobile/views/            # 91 mobile templates (top-level *.php; parity + mobile-only)
│   ├── layouts/mobile.php
│   └── partials/            # bottom-nav.php, header-mobile.php, offcanvas-menu.php
├── assets/                  # js/, css/, fonts, icons
├── mcp-server/              # Node MCP stdio server
├── alexa-skill/             # Skill manifest + interaction model
├── android/                 # WebView wrapper
├── cron/                    # scheduler_simple.php, job_definitions.php
├── data/                    # Encrypted runtime data — DO NOT commit
├── docs/                    # User guide, PRD, TRD, DESIGN
├── scripts/                 # PDF gen, PHPUnit runner, MCP test harness
├── tasks/                   # Active plan files (todo.md, lessons.md)
├── test/                    # PHPUnit + smoke + MCP channel tests
├── vendor/                  # Composer deps (PHPStan, PHPUnit, React/PHP)
├── config.php               # Env loader, constants
├── index.php                # Single entry point
└── .htaccess                # Blocks sensitive paths
```

**Discrepancy with `FILE_MANIFEST.md`:** Manifest reports 36 APIs / 27 includes; actual counts now **50 APIs / 41 includes / 81 views / 91 mobile views** (top-level `*.php` only; verified 2026-05-28 via `ls`). Manifest is stale — TRD numbers are authoritative.

---

## 3. Core Classes

### 3.1 `Encryption` (`includes/Encryption.php`)

| Aspect | Value | Reference |
|---|---|---|
| Cipher | `aes-256-gcm` (constant `CIPHER`) | `Encryption.php:8` |
| Key derivation | `hash('sha256', $masterPassword, true)` (raw binary) | `Encryption.php:13` |
| IV length | 16 bytes via `random_bytes(16)` | `Encryption.php:9,35` |
| Auth tag | 16 bytes (AEAD) | `Encryption.php:10,44` |
| Envelope | `base64(IV ‖ TAG ‖ ciphertext)` | `Encryption.php:53` |
| Password hash | `password_hash($p, PASSWORD_BCRYPT, ['cost' => 12])` | `Encryption.php:102` |
| Password verify | `password_verify()` | `Encryption.php:94–102` |

**Public methods:** `encrypt(mixed): string`, `decrypt(string): mixed`, `Encryption::hashPassword(string)`, `Encryption::verifyPassword(string,string)`, `Encryption::fromRawKey(string $rawKey): self` (factory — Alexa multi-user mode bypasses SHA256 derivation by injecting a pre-derived 32-byte raw key unwrapped from `alexa_links.wrappedDataKey`).

### 3.2 `Database` (`includes/Database.php`)

Per-user-scoped flat-file store. Constructor: `new Database(string $masterPassword, ?string $userId = null)`.

**File routing:**
- Per-user path: `DATA_PATH/users/{userId}/{collection}.json.enc` (`Database.php:23`)
- Global plain (users, sessions, rate_limits): `DATA_PATH/{collection}.json` (`Database.php:59–60`)
- Legacy fallback: auto-migrates `users.json.enc` → `users.json` (`Database.php:66–68`)

**Public method surface:**

| Method | Purpose | Line |
|---|---|---|
| `load(string $collection, bool $strict=true): array` | Load encrypted collection | `:78` |
| `safeLoad(string $collection): array` | Load with error wrapping | `:106` |
| `save(string $collection, array $data): bool` | Encrypt + atomic write | `:153` |
| `insert(string $collection, array $record): bool` | Append with id + timestamps | `:204` |
| `update(string $id, array $updates): bool` | Merge + rewrite | `:219` |
| `delete(string $collection, string $id): bool` | Filter + rewrite | `:239` |
| `findById(string $collection, string $id): ?array` | Linear search | `:184` |
| `findBy(string $collection, string $field, $value): array` | Field filter | `:196` |
| `generateId(): string` | UUID v4 via `mt_rand()` | `:254` |
| `exportAll(): array` | All non-system collections | `:265` |
| `createScoped(?string $userId): self` | Per-user factory | `:54` |
| `Database::fromRawKey(string $userId, string $rawKey): self` | Alexa-mode factory (raw 32-byte key, masterPassword cleared) | `:40–48` |

**Atomic write recipe** (`Database.php:163–172`): `fopen('w')` → `flock(LOCK_EX)` → `fwrite()` → `fflush()` → `flock(LOCK_UN)` → `fclose()`.

**Plain-JSON write:** `fopen('c+')` → `LOCK_EX` → `ftruncate(0)` → `rewind` → `fwrite` (`:336–348`).

**Backup on migration:** `DATA_PATH/migration_backups/{collection}.legacy-YYYYMMdd-HHiiss.bak` (`:417–427`).

### 3.3 `Auth` (`includes/Auth.php`)

| Method | Purpose | Line |
|---|---|---|
| `Auth::check()` | Session + token + fingerprint + ban check | `:1095–1223` |
| `Auth::userId()` | Returns `$_SESSION[SESSION_USER_ID]` or MCP user id | `:1228–1233` |
| `Auth::user()` | Loads user record | `:1238–1291` |
| `Auth::csrfToken()` | Returns session CSRF token | `:1407–1409` |
| `Auth::validateCsrf(string)` | `hash_equals()` compare | `:1400–1402` |
| `Auth::isMcp()` | Detects MCP request mode | (used in api/*) |

**Session fingerprint** — hashes `Accept-Language`; mismatch destroys session and emits `session_fingerprint_mismatch` audit (`Auth.php:1212–1220`). TODO marker remains for fuller fingerprinting.

**MCP authentication paths** (`Auth.php:1004–1090`, loopback-only enforced at `:1006`):
1. `X-MCP-Api-Token` → hash compare against user `mcpApiTokenHash`.
2. Static secret via `X-Master-Password` env-var match.
3. Legacy `X-MCP-User-Email` + `X-Master-Password`.

### 3.4 `Helpers` (`includes/Helpers.php`)

**Master password resolution chain** (`Helpers.php:336–379`):
1. `$_SESSION[SESSION_MASTER_KEY]`
2. `$_SERVER['HTTP_X_MASTER_PASSWORD']` (MCP)
3. `includes/master_password.php` if `MASTER_PASSWORD` constant defined
4. `ROOT_PATH/.user.ini` containing `MASTER_PASSWORD=...`
5. `getenv('MASTER_PASSWORD')` → `getenv('LAZYMAN_MASTER_PASSWORD')`

**Response helpers** (`Helpers.php:56–91`):
- `successResponse($data, $message)` → JSON `{success, data, message, timestamp}`
- `errorResponse($error, $statusCode, $errorCode)` → JSON `{success:false, error:{code,message}, timestamp}`; 401 may redirect.

### 3.5 `AIAgent` (`includes/AIAgent.php`)

- Orchestrates chat → function-call loop with provider client (`GroqAPI`, `OpenRouterAPI`, `GeminiAPI`, `OllamaAPI`).
- Hard limits: **max 4 tool-call loops per chat**, **~45s wall-clock budget**.
- Persists conversation via `ConversationMemory`.
- `finalizeAssistantMessage()` appends a recap + 2-3 suggested next actions.

### 3.6 `FunctionExecutor` (`includes/FunctionExecutor.php`)

- `validateCall($name, $params)` — checks required args, types (`:33–76`).
- Routes to internal API classes (`TasksAPI`, `ProjectsAPI`, `ClientsAPI`, etc.).
- Returns `['valid' => bool, 'errors' => [...]]`.

### 3.7 `DeviceDetector` (`includes/DeviceDetector.php`)

Mobile/desktop decision order (`:7–54`):
1. URL contains `/mobile/` → mobile.
2. Explicit `?device=mobile|desktop` (and persisted to cookie).
3. Cookie preference.
4. `Sec-CH-UA-Mobile: ?1` client hint.
5. UA regex fallback: `android|iphone|ipod|ipad|mobile|webos`.

---

## 4. Data Schema (Collections)

**31 known collections** (deduped from grep of `$db->load|insert|save`):

| Collection | Storage | Notes |
|---|---|---|
| `projects` | per-user enc | Contains nested `tasks[]` |
| `clients` | per-user enc | |
| `invoices` | per-user enc | Standard |
| `advanced_invoices` | per-user enc | Modern templates |
| `finance` | per-user enc | Transactions journal |
| `notes` | per-user enc | |
| `habits` + `habit_completions` + `habit_timer_sessions` | per-user enc | |
| `todos` | per-user enc | |
| `inventory` | per-user enc | |
| `meetings` | per-user enc | |
| `water_tracker` + `water_plans` + `water_plan_history` | per-user enc | |
| `pomodoro_sessions` | per-user enc | |
| `knowledge-base` | per-user enc | + filesystem assets in `data/users/{u}/kb/` |
| `conversations` | per-user enc | AI chat history |
| `ai_prompts` + `ai_daily_brief` | per-user enc | |
| `models` | per-user enc | AI model settings |
| `config` | per-user enc | Business profile |
| `audit` | per-user enc | |
| `key_check` | per-user enc | Seeded on legacy migration (carries `userId`) |
| `users` | **global plain** JSON | bcrypt password, role, tokens |
| `sessions` | global plain | File-based PHP sessions |
| `rates_limits` | global plain | RateLimiter buckets |
| `subscriptions` | global plain | Admin/billing |
| `alexa_links` | global plain | Multi-user OAuth bridge |
| `scheduler_config` | global plain | Cron config |

**Tasks are NOT a separate collection.** They are nested arrays inside `projects[].tasks[]` (`TasksAPI.php:14,22`; confirmed `Database.php:288` excludes `tasks` from per-user collections list).

**Per-user scoping is filesystem-based, not field-based.** No `userId` on records — isolation comes from `DATA_PATH/users/{userId}/` directories. Exception: `key_check` records carry `userId` for migration audit.

**Canonical record shapes:** see `docs/OpenPlan-Work-Complete-Documentation.md` §"Data Models" for JSON examples (Project, Client, Invoice, Note already documented).

---

## 5. Request Lifecycle

```
HTTP request
  │
  ▼
.htaccess  ─────► (block dotfiles, /data, /includes, /vendor, /cron, /tests)
  │
  ▼
index.php
  ├─ sanitize ?page= (regex whitelist)              [index.php:12–32]
  ├─ DeviceDetector::shouldShowMobile()             [DeviceDetector.php:7–54]
  ├─ session_start() + config.php                   [config.php]
  ├─ ensure $_SESSION['csrf_token']                 [index.php:216–219]
  ├─ Auth::check() → if not authed & not public:
  │     header('Location: ?page=login'); exit       [index.php:50–53]
  ├─ Login flow (2-step: email/pw → master code)   [index.php:223–433]
  └─ include views/{page}.php OR mobile/views/{page}.php
        ├─ layouts/main.php injects:
        │     const CSRF_TOKEN = '<?= Auth::csrfToken() ?>'
        │     const APP_URL    = '<?= APP_URL ?>'
        └─ JS uses `api.get/post/put/delete()` → /api/*.php
              ├─ require config.php
              ├─ Auth::check() or 401
              ├─ if not GET and not MCP: validate CSRF (body or X-CSRF-Token)
              ├─ $db = new Database(getMasterPassword(), Auth::userId())
              ├─ switch (method) { GET|POST|PUT|DELETE → action dispatch }
              └─ successResponse|errorResponse → JSON
```

---

## 6. API Endpoints (51 endpoints)

Authoritative list — see also `CLAUDE.md` for response format and per-endpoint actions.

**Auth/Security**
- `auth.php` (login/logout/register/status/config)
- `admin-shared-keys.php`
- `health.php`

**Business**
- `tasks.php`, `projects.php`, `clients.php`, `meetings.php`, `tickets.php`

**AI**
- `ai.php`, `ai-agent.php`, `ai-generate.php`, `ai-usage.php`, `models.php`

**Billing/Accounting**
- `invoices.php`, `advanced-invoices.php`, `finance.php`
- `payments.php`, `payment-methods.php`, `payment-proof.php`, `payment-qr-upload.php`
- `plans.php`, `subscriptions.php`

**Data/Admin**
- `audit.php`, `backup.php`, `backup_settings.php`
- `data-recovery.php`, `deep-migration.php`
- `export.php`, `wipe-data.php`

**Content/Productivity**
- `notes.php`, `knowledge-base.php`, `attachments.php`
- `todos.php`, `habits.php`, `water.php`, `pomodoro.php`
- `inventory.php`, `task-inventory-link.php`

**Ops/Scheduler**
- `cron.php`, `scheduler_bootstrap.php`, `scheduler_config.php`, `scheduler_status.php`
- `settings.php`, `favicon.php`
- `release-download.php`

**Voice/Integrations**
- `alexa.php`
- `oauth/authorize.php`, `oauth/token.php`, `oauth/revoke.php` (Alexa account-linking)

All responses follow `{success, data, message, timestamp}` or `{success:false, error:{code,message}, timestamp}`.

---

## 7. MCP Server (`mcp-server/index.js`)

| Aspect | Value |
|---|---|
| Transport | `StdioServerTransport` |
| SDK | `@modelcontextprotocol/sdk@0.6.0` |
| Auth modes | (a) `X-MCP-Api-Token` + `X-Master-Password`, (b) legacy email + master pw |
| API base | `process.env.API_URL` (default `http://localhost/taskmanager/api`) |
| Tool count | **81 tools** discovered in last MCP channel smoke (`project_status.md`) |
| KB tools | `kb-text-tools.js` for direct text upload (no base64) |
| Loopback | enforced at server side (`Auth.php:1006`) |

Sample config (from `OpenPlan-Work-Complete-Documentation.md`):
```json
{
  "mcpServers": {
    "openplan-work": {
      "command": "node",
      "args": ["C:/MAMP/htdocs/taskmanager/mcp-server/index.js"],
      "env": {
        "API_URL": "http://localhost/taskmanager/api",
        "MCP_API_TOKEN": "<user-token>"
      }
    }
  }
}
```

---

## 8. AI Provider Integration

| Provider | Class | Env / Key | Default Models |
|---|---|---|---|
| Groq | `includes/GroqAPI.php` | `GROQ_API_KEY` | `llama-3.3-70b-versatile`, `llama-3.1-8b-instant`, `gpt-oss` |
| OpenRouter | `includes/OpenRouterAPI.php` | `OPENROUTER_API_KEY` | any configured `provider/model` slug |
| Gemini | `includes/GeminiAPI.php` | `GEMINI_API_KEY` | Google `v1beta` REST |
| Ollama | `includes/OllamaAPI.php` | `OLLAMA_BASE_URL` (default `http://localhost:11434`) | any locally pulled model |

Anthropic is **not** integrated directly; Claude models are reachable via OpenRouter proxy.

---

## 9. Security Contracts

- **Encryption invariant** — anything written via `Database` for a real user is AES-256-GCM encrypted at rest. Plain JSON is reserved for the `users` table and operational state (sessions, rate-limits).
- **CSRF** — all `POST/PUT/DELETE` to `api/*.php` require either `csrf_token` body field or `X-CSRF-Token` header, validated via `Auth::validateCsrf()`. Bypassed only for authenticated MCP requests (`Auth::isMcp()`).
- **Session cookies** — HTTP-only, `SameSite=Strict`, 1-hour default lifetime, configurable per user.
- **Filesystem protection** — `data/.htaccess` blocks all web access; root `.htaccess` blocks dotfiles + sensitive directories.
- **MCP loopback** — enforced at request-validation time; non-127.0.0.1/::1 callers rejected before token check.
- **Release export** — `views/release-export.php` produces a zip with `data/` regenerated empty, `includes/master_password.php` excluded, `.env` sanitized.

---

## 10. Testing & Validation

| Command | Purpose | Last Known Result |
|---|---|---|
| `php test/smoke_test_comprehensive.php` | App-wide smoke | **28 pass, 0 fail** |
| `php scripts/run_phpunit.php test/SecurityRegressionTest.php` | Security regressions | pass |
| `php scripts/run_phpunit.php test/DatabaseCoreTest.php` | DB core | pass |
| `node test/mcp_channel_smoke.mjs` | MCP full-channel | pass (81 tools) |
| `composer qa:stan` | PHPStan | (see `phpstan.neon`) |
| `composer qa:cs` | Code style | (see config) |
| `php verify_manifest.php` | Manifest integrity | check before release |

Coverage warning currently noisy due to missing PHP coverage driver; not a functional blocker (`project_status.md`).

---

## 11. Known Technical Debt

From `project_status.md` (2026-03-07 / 2026-03-16) and code grep:
1. Session fingerprint hardening TODO in `Auth.php`.
2. `FILE_MANIFEST.md` counts (36/27/67/59) lag actual counts (51/42/83/94).
3. PHPUnit coverage driver missing — emits warnings in CI.
4. No Anthropic direct provider (currently via OpenRouter).
5. Enterprise compliance/audit depth and formal SLO controls pending.
6. iOS native wrapper not present (Android exists in `android/`).

---

## 12. Reference

- **CLAUDE.md** — agent-facing operating rules (icon system, FTP, common pitfalls).
- **PRD** (`docs/PRD.md`) — product intent.
- **DESIGN** (`docs/DESIGN.md`) — UX flow and routing.
- **memory/MEMORY.md** — per-conversation memory index (FTP creds, Alexa skill, settings hub).
