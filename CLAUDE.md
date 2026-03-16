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

## Quick Reference

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

### Setup & Installation
```bash
# Project requires PHP 8.0+ with openssl, json, mbstring, zip extensions
# No package managers needed - pure PHP + vanilla JS

# Ensure data directory is writable
chmod 755 data/
chmod 644 data/*.json.enc  # If they exist

# Configure Apache/Nginx to use .htaccess for routing
# Ensure mod_rewrite is enabled
```

### Testing
```bash
# Run all tests
php test/run_all_tests.php

# Run encryption test only
php test/encryption_test.php

# Manual testing checklist:
# 1. Navigate to /?page=setup for first-time setup
# 2. Create user account
# 3. Login with created credentials
# 4. Test all CRUD operations in dashboard
# 5. Verify data encryption by checking /data/ directory
# 6. Test API endpoints directly
```

### Development Workflow
```bash
# No build process required
# Simply edit files and refresh browser

# To reset system (clear all data):
rm -f data/*.json.enc
rm -rf data/backups/*

# To test encryption functionality:
php test/encryption_test.php
```

### Debugging
```bash
# Enable debug mode in config.php:
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Check PHP error logs
tail -f /path/to/php/error.log
```

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
- **No package.json/composer.json**: Pure PHP + vanilla JS
- **Data encryption**: All data in `/data/` is encrypted with master password
- **Session dependency**: Requires active PHP session for authentication
- **CSRF required**: All non-GET API requests must include CSRF token
- **Environment variables**: Master password can be set via `LAZYMAN_MASTER_PASSWORD`
- **Embedded tasks**: Tasks are stored within project records, not separate entities
- **Multi-currency**: Support for USD, EUR, GBP, ZAR with automatic symbol formatting

## Project Version

v1.0.0 - Complete business suite with task management, CRM, invoicing, finance, inventory, time tracking, and AI integration.
