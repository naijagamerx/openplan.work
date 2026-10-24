# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LazyMan Tools is a PHP-based task management system with encrypted JSON storage. It provides task/project management, CRM, invoicing, finance tracking, inventory management, and AI-powered features. The system uses AES-256-GCM encryption for all data at rest and implements session-based authentication with CSRF protection.

## Architecture

- **Type**: PHP monolithic application with RESTful API endpoints
- **Frontend**: Vanilla JavaScript with Tailwind CSS (CDN) + Inter font
- **Backend**: PHP 8.0+ with no external dependencies
- **Data Storage**: Encrypted JSON files in `/data/` directory
- **Authentication**: Session-based with bcrypt password hashing
- **Security**: AES-256-GCM encryption, CSRF tokens, XSS protection, .htaccess data protection

## App Surfaces & Where They Live (read this before any UI/footer/nav work)

This repo ships **four distinct client surfaces** plus the PHP backend that powers them. They look similar but their source lives in different folders — editing the wrong one is the #1 source of "I fixed it but nothing changed" confusion.

| Surface | Folder | What it is | Runs where |
|---|---|---|---|
| **Desktop web** | `views/` (83 files) + `assets/js/app.js` | Vanilla-JS + Tailwind PHP pages; `views/layouts/main.php` is the shell | `index.php?page=` |
| **Mobile web** | `mobile/views/` (94 files) | Parallel PHP view tree for phone-width; `mobile/views/partials/` has `header-mobile.php`, `bottom-nav.php`, `offcanvas-menu.php` | `mobile/index.php?page=` |
| **Android WebView shell** | `android/app/src/main/java/com/taskmanager/app/MainActivity.java` | Thin WebView wrapper. **Hardcoded `APP_URL = "https://openplan.work/mobile/"`** (line 19) — points at the *deployed* mobile web, NOT local files. Package: `com.taskmanager.app`. Bundle id: `com.taskmanager.app`. | Installable APK |
| **Flutter native client** | `flutter_app/` | Real native mobile app. Package: `work.openplan.openplan_app` (this is the "openplan" app on the device). Name in `pubspec.yaml`: `openplan_app`. Built with Riverpod + go_router + dio. | Android + iOS |

### Footer / bottom-nav ownership map (the gotcha)

Each surface has its **own** footer — fixing one does not fix the others:

- **Desktop web** → `views/layouts/main.php` (sidebar-based, no bottom nav)
- **Mobile web** (`mobile/`) → `mobile/views/partials/bottom-nav.php`. Tabs: `Menu / Tasks / Habits / Settings`. Active/inactive color classes are currently identical (both `text-black dark:text-white`) — there is no visual active state.
- **Android WebView** (`com.taskmanager.app`) → renders the deployed `mobile/` bottom-nav at `https://openplan.work/mobile/`. **A stale deploy will show a different footer than your local `mobile/` source.** To change the footer seen in this app you must (a) edit `mobile/views/partials/bottom-nav.php`, then (b) redeploy `openplan.work`.
- **Flutter** (`work.openplan.openplan_app`) → `flutter_app/lib/widgets/app_bottom_nav.dart`. Tabs (5): `Dashboard / Tasks / Notes / Habits / Menu` (different order/labels than the web mobile nav). `notes_screen.dart` uses `AppScaffold` so it renders the footer as a primary tab (also still reachable via the menu hub push). **Known issue:** the active item's label is "DASHBOARD" but a 1:1:1:1 flex split clips it to **"DASH"** on screen (the source comment at `app_bottom_nav.dart:52-53` calls this out; the 3:1 active-flex fix exists but can still clip on narrow devices). The other 3 labels are intentionally hidden until active/pressed.

> **Diagnostic tip:** If the footer you see on a device doesn't match local source, check (1) which package is in the foreground (`adb shell dumpsys activity activities | grep ResumedActivity`), (2) for the WebView app, whether it points at `openplan.work` (production) vs local MAMP, (3) for Flutter, the `app_bottom_nav.dart` clipping behavior.

### Other surface-specific folders

- **MCP server** — `mcp-server/index.js` (Node 18+ ESM, stdio, `@modelcontextprotocol/sdk`). `package.json` name: `openplan-work-mcp-server`. A PHP-side bridge also exists at `api/mcp-server.php`.
- **Alexa skill** — `alexa-skill/` (`interaction-model.json` + `ALEXA-CONSOLE-HANDOFF.md`); backend endpoint `api/alexa.php`.
- **Backend** — `api/*.php` (52 endpoint files) + `includes/` (43 PHP classes). Tasks are embedded in the `projects` collection, not separate — see TRD data model.
- **Data** — `data/users/{userId}/*.json.enc` (encrypted, gitignored). Never touch directly; always go through `Database`.

## Quick Reference

### Authoritative Spec Documents (start here for any non-trivial task)
- **`docs/PRD.md`** — product requirements, personas, scope (what & why)
- **`docs/TRD.md`** — technical contract: classes, schemas, APIs, encryption, request lifecycle (how & where; **file:line evidence**)
- **`docs/DESIGN.md`** — UX flows, routing, layout, theming
- **`docs/OpenPlan-Work-Complete-Documentation.md`** — auto-generated reference (data models, MCP config sample)
- **`docs/OpenPlan-Work-User-Guide.md`** — end-user instructions
- **`project_status.md`** — latest audit score (84/100) + smoke test results + open risks

When PRD/TRD/DESIGN disagrees with this file, the spec docs win — they are evidence-based and re-derivable from code. Update them when behavior changes.

### Critical Recovery Fixes
- **Notes Tag Manager Fix**: See `## Recovery Fixes` section below
- **AI Assistant Fix**: See `## Recovery Fixes` section below
- **PHP/JS Variable Mixing Bug**: See `## Common Pitfalls` section below

### Code Exploration (Use Skills)
- **Page Routing**: Use `CodebaseContextMapper` skill - do not document page tables here
- **UI Generation**: Use `StitchUI` skill for creating new interfaces
- **Specification Creation**: Use `Speckitty` skill for generating requirements
- **Task Management**: Use `DevMarket` MCP for external task tracking

## Common Development Commands

### Setup & Running
```bash
# PHP 8.0+ with openssl, json, mbstring, curl, zip. Local PHP: /c/MAMP/bin/php/php8.3.1/php
# Runtime has no Composer deps; composer.json exists only for dev tooling (PHPUnit/PHPStan/php-cs-fixer in vendor/)
# Local URL via MAMP: http://localhost/taskmanager/   (mobile: /taskmanager/mobile/)
php start_server.php          # alternative: built-in server on http://localhost:8000 (start_server.bat / stop_server.bat)
# Secrets/config come from .env (gitignored), loaded by config.php. First run: ?page=setup
```

### Testing & Verification
```bash
php -l path/to/file.php                       # syntax check after every PHP edit

# Script-style characterization tests in tests/ (e.g. tests/notes_checklist_test.php) run via php-guardian:
php ~/.claude/skills/php-guardian/scripts/test_runner.php C:/MAMP/htdocs/taskmanager

composer qa:test     # PHPUnit (scripts/run_phpunit.php picks a PHP binary with openssl).
                     # phpunit.xml only scans test/*Test.php — currently none exist, so it reports "No tests executed!"
composer qa:stan     # PHPStan per phpstan.neon
composer qa:cs       # php-cs-fixer dry-run on an allowlist of files
node scripts/mcp_test_all_tools.mjs          # exercises every MCP tool against api/mcp-server.php
```
`test/`, `phpunit.xml`, `phpstan.neon` are gitignored (local only). php-guardian's `smoke_test.php` always exits 1 here from pre-existing fixture/backup include failures — judge by your own files.

### Debugging
- `config.php` sets `display_errors=0` and logs to **`data/php_error.log`** — check it first for any 500.
- Data lives per user at `data/users/{userId}/*.json.enc`; never hand-edit or delete it — go through `Database`.

## Request Lifecycle & Auth (big picture)

- Every entry point `require_once config.php` first (loads `.env`, defines `APP_*`/path constants, starts the session).
- **Pages**: `index.php?page=X` (desktop → `views/X.php` inside `views/layouts/main.php`) and `mobile/index.php?page=X` (→ `mobile/views/X.php`). `index.php` owns auth gating and the two-step login → master-password flow.
- **API**: `api/*.php` are thin: `Auth::check()` → `new Database(getMasterPassword(), Auth::userId())` → `new XxxAPI($db, 'collection')` (extends `includes/BaseAPI.php`) → switch on `requestMethod()` / `?action=` → `successResponse()` / `errorResponse()`.
- **Three auth modes** all resolve inside `Auth` (`includes/Auth.php`): browser session cookie; MCP token (`X-MCP-Api-Token`, `Auth::isMcp()`); native-device bearer token (`Authorization: Bearer`, `DeviceTokens`). Token-auth requests carry no CSRF token, so endpoints must skip CSRF using **`Auth::isTokenAuth()`** (covers both token modes) — gating on `isMcp()` alone 403s the Flutter app.
- The master password never sits on disk in plaintext for token auth: tokens wrap it, and `getMasterPassword()` (`includes/Helpers.php`) unwraps it per request.
- MCP: `api/mcp-server.php` (PHP-native MCP over HTTP, dispatches tools by calling the app's own API) and `mcp-server/index.js` (Node stdio bridge).

## Core Class Usage

### Database Class
```php
$db = new Database($masterPassword);

// CRUD operations
$db->load('collection');           // Load all records
$db->insert('collection', $data);  // Create
$db->findById('collection', $id);  // Read
$db->update('collection', $id, $updates); // Update
$db->delete('collection', $id);    // Delete

// Bulk operations
$db->exportAll();                  // Export all collections
$db->importAll($data);             // Import backup
```

### Encryption Class
```php
$encryption = new Encryption($masterPassword);

// Encrypt/decrypt data
$encrypted = $encryption->encrypt($data);
$decrypted = $encryption->decrypt($encrypted);

// Password handling
$hash = Encryption::hashPassword($password);
$valid = Encryption::verifyPassword($input, $hash);
```

### Auth Class
```php
// Session management
Auth::check();                      // Check if logged in
Auth::userId();                     // Get current user ID
Auth::user();                       // Get current user info
Auth::csrfToken();                  // Get CSRF token
Auth::validateCsrf($token);         // Validate CSRF token

// Login/Register (requires Database instance)
$auth = new Auth($db);
$result = $auth->login($email, $password);
$result = $auth->register($email, $password, $name);
$auth->logout();
```

## API Endpoints

All API endpoints return JSON in format:
```json
{
    "success": true,
    "data": {},
    "message": "Operation completed",
    "timestamp": "2024-12-30T10:00:00Z"
}
```

### Authentication (`api/auth.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `?action=login` | User login with email, password, master_password |
| POST | `?action=logout` | User logout |
| POST | `?action=register` | User registration |
| GET | `?action=status` | Check session status |
| GET | `?action=config` | Load user configuration |
| POST | `?action=update_config` | Update business settings |

### Tasks (`api/tasks.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?id={id}` | Get task by ID |
| GET | `?projectId={id}` | List tasks with filters (status, priority) |
| GET | `?action=templates` | Get task templates |
| POST | `?action=add` | Create new task |
| POST | `?action=template` | Save task template |
| POST | `?action=create_from_template` | Create task from template |
| POST | `?action=bulk` | Bulk operations (delete, status update) |
| POST | `?action=subtask&id={id}&projectId={pid}` | Add subtask |
| PUT | `?id={id}` | Update task |
| DELETE | `?id={id}` | Delete task |

### Projects (`api/projects.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?id={id}` | Get project by ID |
| GET | `?action=list` | List all projects |
| POST | `?action=create` | Create project |
| PUT | `?id={id}` | Update project |
| DELETE | `?id={id}` | Delete project |

### Clients (`api/clients.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?id={id}` | Get client by ID |
| GET | `?action=list` | List all clients |
| POST | `?action=create` | Create client |
| PUT | `?id={id}` | Update client |
| DELETE | `?id={id}` | Delete client |

### Invoices (`api/invoices.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?id={id}` | Get invoice by ID |
| GET | `?action=list` | List invoices (filter by status) |
| POST | `?action=create` | Create invoice |
| PUT | `?id={id}` | Update invoice |
| DELETE | `?id={id}` | Delete invoice |

### Finance (`api/finance.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List transactions |
| POST | `?action=add` | Add transaction (expense/revenue) |
| PUT | `?id={id}` | Update transaction |
| DELETE | `?id={id}` | Delete transaction |

### Inventory (`api/inventory.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List inventory items |
| POST | `?action=add` | Add inventory item |
| PUT | `?id={id}` | Update item |
| DELETE | `?id={id}` | Delete item |

### Notes (`api/notes.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List notes |
| GET | `?action=tag_stats` | Get tag statistics |
| POST | `?action=create` | Create note |
| PUT | `?id={id}` | Update note |
| DELETE | `?id={id}` | Delete note |
| POST | `?action=delete_tag` | Delete tag globally |

### Habits (`api/habits.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List habits |
| POST | `?action=create` | Create habit |
| POST | `?action=complete` | Mark habit complete |
| PUT | `?id={id}` | Update habit |
| DELETE | `?id={id}` | Delete habit |

### Water Tracker (`api/water.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=status` | Get today's status |
| POST | `?action=log` | Log water intake |
| POST | `?action=set_goal` | Set daily goal |
| POST | `?action=generate_plan` | Generate AI hydration plan |

### AI Integration (`api/ai.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `?action=generate_tasks` | Generate tasks from description |
| POST | `?action=generate_prd` | Generate PRD document |
| POST | `?action=chat` | AI chat assistant |
| POST | `?action=verify` | Verify AI connection |

### AI Agent (`api/ai-agent.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `?action=chat` | Agent chat with tool execution |
| POST | `?action=clear_history` | Clear conversation history |

### Export/Import (`api/export.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?format=json` | Export all data as JSON |
| GET | `?format=zip` | Export as ZIP backup |
| POST | `?action=import` | Import data from JSON/ZIP |

### Users (`api/users.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List users (admin) |
| POST | `?action=create` | Create user |
| PUT | `?id={id}` | Update user |
| DELETE | `?id={id}` | Delete user |
| POST | `?action=promote` | Promote to admin |

### Backup (`api/backup.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List backups |
| POST | `?action=create` | Create backup |
| POST | `?action=restore` | Restore from backup |
| DELETE | `?id={id}` | Delete backup |

### Advanced Invoices (`api/advanced-invoices.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List advanced invoices |
| POST | `?action=create` | Create advanced invoice |
| PUT | `?id={id}` | Update invoice |
| DELETE | `?id={id}` | Delete invoice |

### Knowledge Base (`api/knowledge-base.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list_folders` | List folders |
| GET | `?action=list_files` | List files in folder |
| POST | `?action=create_folder` | Create folder |
| POST | `?action=upload` | Upload file |
| DELETE | `?id={id}` | Delete file/folder |

### Todos (`api/todos.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List todos |
| POST | `?action=create` | Create todo |
| PUT | `?id={id}` | Update todo |
| DELETE | `?id={id}` | Delete todo |

### Settings (`api/settings.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=get` | Get settings |
| POST | `?action=update` | Update settings |
| POST | `?action=reset` | Reset to defaults |

### Attachments (`api/attachments.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `?action=upload` | Upload attachment |
| GET | `?id={id}` | Download attachment |
| DELETE | `?id={id}` | Delete attachment |

### Audit Logs (`api/audit.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=list` | List audit logs |
| GET | `?action=export` | Export logs |

### Health Check (`api/health.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | System health status |

### Cron (`api/cron.php`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?action=run` | Execute scheduled tasks |

## Frontend Architecture

### JavaScript API Helper
```javascript
// All API calls use the api helper
const api = {
    get(endpoint),
    post(endpoint, data),
    put(endpoint, data),
    delete(endpoint)
};

// Usage:
const tasks = await api.get('api/tasks.php');
const result = await api.post('api/tasks.php', {
    title: 'New Task',
    projectId: 'uuid',
    csrf_token: CSRF_TOKEN
});
```

### UI Components
- **Toast notifications**: `showToast(message, type)` - success, error, warning, info
- **Modals**: `openModal(htmlContent)`, `closeModal()` - dynamic modal system
- **Confirmation dialogs**: `confirmAction(message, onConfirm)`

### Icon System: Heroicons (CRITICAL!)
**⚠️ IMPORTANT: This app uses ONLY Heroicons (inline SVG). Never use icon fonts or other icon systems.**

#### Standard Pattern
```html
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="PATH_DATA"></path>
</svg>
```

#### Icon Sizing
- `w-4 h-4` - Small (meta info, inline)
- `w-5 h-5` - Standard (buttons, list items)
- `w-6 h-6` - Large (modals, headers)
- `w-8 h-8` - Extra large (empty states)

#### Common Icons Reference
| Icon | Path Data |
|------|-----------|
| Plus | `M12 4v16m8-8H4` |
| Edit | `M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z` |
| Trash | `M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16` |
| Search | `M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z` |
| Check | `M5 13l4 4L19 7` |
| X/Close | `M6 18L18 6M6 6l12 12` |

**Find Heroicons:** https://heroicons.com/ (Always use "Outline" versions)

## Data Models

### Project Structure (with embedded tasks)
```json
{
    "id": "uuid",
    "name": "Project Name",
    "description": "Project description",
    "clientId": "uuid",
    "status": "active|completed|on-hold|cancelled",
    "color": "hex color code for UI",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp",
    "tasks": [
        {
            "id": "uuid",
            "title": "string",
            "description": "string",
            "status": "backlog|todo|in_progress|review|done",
            "priority": "low|medium|high|urgent",
            "dueDate": "ISO timestamp",
            "estimatedMinutes": 0,
            "actualMinutes": 0,
            "subtasks": [{"title": "string", "completed": boolean, "estimatedMinutes": 0}],
            "timeEntries": [{"date": "ISO timestamp", "minutes": integer, "description": "string"}],
            "completedAt": "ISO timestamp",
            "createdAt": "ISO timestamp",
            "updatedAt": "ISO timestamp"
        }
    ]
}
```

### Client Structure
```json
{
    "id": "uuid",
    "name": "Client name",
    "email": "email address",
    "phone": "phone number",
    "company": "Company name",
    "address": {"street": "", "city": "", "state": "", "zip": "", "country": ""},
    "notes": "Additional notes",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

### Invoice Structure
```json
{
    "id": "uuid",
    "invoiceNumber": "2024-0001",
    "clientId": "uuid",
    "projectId": "uuid",
    "lineItems": [{"description": "", "quantity": 0, "unitPrice": 0, "total": 0}],
    "subtotal": 0,
    "taxRate": 0,
    "taxAmount": 0,
    "total": 0,
    "currency": "USD|EUR|GBP|ZAR",
    "status": "draft|sent|paid|overdue|cancelled",
    "dueDate": "ISO date",
    "issueDate": "ISO date",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

### Note Structure
```json
{
    "id": "uuid",
    "title": "Note title",
    "content": "Note content (max 10000 chars)",
    "tags": ["work", "ideas"],
    "color": "#fef3c7",
    "isPinned": false,
    "isFavorite": false,
    "linkedEntityType": "task|project|null",
    "linkedEntityId": "uuid|null",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

## Security Implementation

### Data Encryption
- **Algorithm**: AES-256-GCM
- **Key Derivation**: SHA-256 from master password
- **Storage**: Base64 encoded (IV + Tag + Encrypted data)
- **Master Password**: From session or `LAZYMAN_MASTER_PASSWORD` env var

### Authentication
- **Passwords**: bcrypt with cost 12
- **Sessions**: 1-hour lifetime, HTTP-only cookies, SameSite=Strict
- **CSRF**: 32-byte random token per session, validated on all POST/PUT/DELETE

### File Protection
- `/data/.htaccess` blocks all web access
- `.htaccess` routing for clean URLs
- Session validation for all protected pages

## AI Integration

### Groq API
```php
$groq = new GroqAPI($apiKey);
$response = $groq->chatCompletion($messages, $model);
```
Models: `llama-3.3-70b-versatile`, `mixtral-8x7b-32768`

### OpenRouter API
```php
$openrouter = new OpenRouterAPI($apiKey);
$response = $openrouter->chatCompletion($messages, $model);
```
Models: `anthropic/claude-3.5-sonnet`, `openai/gpt-4-turbo`, `meta-llama/llama-3.3-70b`

### AI Troubleshooting (Common Failures)
- **500 errors**: Check `data/php_error.log` for root cause first
- **Missing cURL**: Enable `php_curl` in `php.ini`
- **API key used as model**: Keep keys in Settings → AI API Keys, use real model IDs in Model Settings
- **Decryption failed**: Wrong master password/session - log out/in

## Email/Mailer Functionality

### Mailer Class
- **File**: `/includes/Mailer.php`
- **Method**: Native PHP `mail()` function
- **Template System**: Professional HTML template with inline CSS
- **Business Integration**: Uses config from encrypted `config.json.enc`

### Configuration
- **Storage**: Business settings in `/data/config.json.enc`
- **Settings**: Business Name, Email, Phone, Address, Currency, Tax Rate
- **UI**: Configurable via Settings page

## Recovery Fixes

### Notes Tag Manager Recovery Fix

**Symptoms**: Manage Tags modal shows `All Tags (0)` even though sidebar has tags; Tag names show as `undefined`; Deleting a tag triggers `500 Internal Server Error`.

**Fixes Applied**:

1. **API route fix** (`api/notes.php`):
```php
if ($action === 'tag_stats') {
    $stats = $notesAPI->getTagStats();
    successResponse($stats);
}
```

2. **Backend hardening** (`includes/NotesAPI.php`):
   - `getTagStats()`: Skip non-array tags, non-scalar entries, empty values
   - `deleteTagGlobal()`: Validate input tag (trim/lowercase), return 0 if empty

3. **Frontend safety** (`views/notes.php`):
   - `showGlobalTagManager()`: Only render rows where `tag` is non-empty string
   - `confirmDeleteTagGlobal()`: Block empty tags with toast message

### AI Assistant Recovery Fix

**Symptom**: Agent mode executes actions but shows no user-friendly recap or follow-up guidance.

**Fix Applied** (`includes/AIAgent.php`):
- Added `finalizeAssistantMessage()` method for structured follow-up
- Added `buildActionFollowUp()` and `buildSuggestedNextSteps()` methods
- Output now includes: what was done, errors, 2-3 concrete next actions

## Common Pitfalls

### ⚠️ PHP/JavaScript Variable Mixing Bug (CRITICAL!)

**The Problem**: When using PHP to output JavaScript variables in mobile views, mixing PHP constant syntax with JavaScript variables breaks the code.

**Example of the Bug**:
```javascript
// Define JavaScript variable from PHP session data
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';

// ❌ WRONG - Trying to use PHP constant syntax in JavaScript
csrf_token: '<?= CSRF_TOKEN ?>'

// ✅ CORRECT - Use the JavaScript variable directly
csrf_token: CSRF_TOKEN
```

**Why It Fails**:
| Code | Language | Result |
|------|----------|--------|
| `<?= CSRF_TOKEN ?>` | PHP | Warning: "Use of undefined constant" |
| `CSRF_TOKEN` | JavaScript | ✅ Works correctly |

**Correct Pattern for Mobile Views**:
```php
<script>
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const APP_URL = '<?= APP_URL ?? '' ?>';
</script>

<script>
    const data = {
        action: 'update',
        csrf_token: CSRF_TOKEN,  // ✅ Correct - JS variable
    };
</script>
```

## Important Notes

- **No build system**: Direct file editing, refresh browser to see changes
- **No runtime package managers**: Pure PHP + vanilla JS (`composer.json` is dev tooling only; `mcp-server/` and `flutter_app/` have their own package files)
- **Data encryption**: All data in `/data/` is encrypted with master password
- **Session dependency**: Requires active PHP session for authentication
- **CSRF required**: All non-GET API requests must include CSRF token
- **Environment variables**: Master password can be set via `LAZYMAN_MASTER_PASSWORD`
- **Embedded tasks**: Tasks are stored within project records, not separate entities
- **Multi-currency**: Support for USD, EUR, GBP, ZAR with automatic symbol formatting

## Deploying to openplan.work (FTP)

Host, username, password and SMTP settings live in **`CLAUDE.local.md`** (gitignored, auto-loaded). Never copy them back into this file — this file is pushed to the public repo.

- **FTP root IS the web root** (chrooted account). Upload to `ftp://<host>/views/file.php`, never `ftp://<host>/public_html/...`, and never create a `public_html/` directory.
- **NEVER** use `--ftp-create-dirs` for directories that already exist. Always **LIST** the target directory first to confirm it exists before uploading.
- **ALWAYS** verify uploads by listing the directory afterward (and fetching the file back) to confirm the timestamp/bytes updated.

## Pushing to GitHub (public repo — read before every push)

`origin` = `https://github.com/naijagamerx/openplan.work.git` is **public** (the self-hosted/OSS edition). Production runs the same code on openplan.work.

- Never commit user data: `data/`, `data_backups/`, `data_backup_*`, `uploads/`, `release-artifacts/`, `.env*`, `.mcp.json*`, `*.log`. Stage files by name — never `git add -A` / `git add .` in this repo (the working tree holds backups, test FTP scripts, and screenshots).
- Before pushing, scan the outgoing diff for secrets and require zero hits — the API-key patterns below **plus** the master password and FTP username from `CLAUDE.local.md`:
  `git diff origin/main..HEAD | grep -E 'gsk_|sk-or-|sk-ant-|AIza|@gmail\.com|\b[0-9a-f]{32,}\b'`
  The last pattern catches bare hex secrets (OAuth client secrets, platform keys) — key-prefix patterns alone missed one. Also read every `*HANDOFF*`/setup doc in the diff by eye: console walkthroughs tend to paste live secrets.
- Known files that have held the master password: `mcp-server/index.js` (header comment), `flutter_app/MAC_SETUP.md`, `flutter_app/README.md`, `test_ftp_diagnostic.py`.
- Push the feature branch onto main with `git push origin <branch>:main` (fast-forward only).

## Project Version

v1.0.0 - Complete business suite with task management, CRM, invoicing, finance, inventory, time tracking, and AI integration.
