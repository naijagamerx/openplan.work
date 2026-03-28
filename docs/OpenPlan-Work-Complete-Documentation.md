# OpenPlan Work - Complete Documentation

**Version:** 1.0.0
**Last Updated:** March 22, 2026
**License:** MIT

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Project Structure](#project-structure)
3. [Installation & Setup](#installation--setup)
4. [API Reference](#api-reference)
5. [Core Classes](#core-classes)
6. [Features](#features)
7. [Security](#security)
8. [MCP Integration](#mcp-integration)
9. [Development Guide](#development-guide)
10. [Troubleshooting](#troubleshooting)

---

## Executive Summary

**OpenPlan Work** is an enterprise-grade PHP task management and business operations suite with advanced AI integration and military-grade encryption. It combines comprehensive CRM, project management, invoicing, finance tracking, inventory management, and productivity tools into a single, secure platform.

### Key Statistics

| Metric | Value |
|--------|-------|
| **Total Files** | 196 PHP files + assets |
| **Lines of Code** | ~50,000+ PHP lines |
| **API Endpoints** | 36 REST APIs |
| **Core Classes** | 27 backend classes |
| **Views** | 67 desktop + 59 mobile |
| **MCP Server** | 2,379 lines of Node.js |
| **Security** | AES-256-GCM encryption |

### Technical Stack

- **Backend:** PHP 8.0+ with no external framework dependencies
- **Frontend:** Vanilla JavaScript with Tailwind CSS (CDN)
- **Storage:** Encrypted JSON flat files (no SQL database)
- **AI:** Groq & OpenRouter integration
- **MCP:** Native Model Context Protocol server

---

## Project Structure

### Directory Layout

```
taskmanager/
├── api/                    # 36 REST API endpoints
├── includes/               # 27 Core backend classes
├── views/                  # 67 Desktop view files
│   ├── layouts/           # Page templates
│   └── partials/          # Reusable components
├── mobile/                 # Mobile application
│   ├── views/             # 59 Mobile-specific views
│   └── assets/            # Mobile-specific assets
├── mcp-server/            # Node.js MCP server
├── data/                  # Encrypted data storage
├── assets/                # Frontend assets
├── cron/                  # Scheduled job system
├── test/                  # Test suites
├── docs/                  # Documentation
├── config.php             # Main configuration
└── index.php              # Application entry point
```

### Configuration Files

| File | Purpose |
|------|---------|
| `config.php` | Main application configuration |
| `.env` | Environment-specific settings |
| `.mcp.json` | MCP server configuration |
| `composer.json` | PHP dependencies |

---

## Installation & Setup

### Requirements

- **PHP Version:** 8.0 or higher
- **PHP Extensions:** `json`, `mbstring`, `openssl`, `curl`
- **Web Server:** Apache (with mod_rewrite) or Nginx
- **Node.js:** 18+ (for MCP server only)

### Local Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/naijagamerx/openplan.work.git
   cd openplan.work
   ```

2. **Start the PHP server:**
   ```bash
   php start_server.php
   # OR on Windows
   start_server.bat
   ```

3. **Navigate to setup page:**
   ```
   http://localhost:8000/?page=setup
   ```

4. **Create admin account:**
   - Enter email address
   - Create password (min 8 characters)
   - Set master password for encryption

### Production Deployment

1. **Upload files to web server**

2. **Configure `.env` file:**
   ```bash
   APP_DISPLAY_NAME=OpenPlan
   EMAIL_VERIFICATION_ENABLED=0
   PASSWORD_RESET_ENABLED=0
   ```

3. **Set file permissions:**
   ```bash
   chmod 755 data/
   chmod 644 data/*.json.enc
   ```

4. **Access setup page to create admin account**

---

## API Reference

### Authentication Endpoints

#### `POST api/auth.php?action=login`

Login with email and password.

**Request:**
```json
{
    "email": "user@example.com",
    "password": "user_password",
    "master_password": "encryption_key",
    "csrf_token": "csrf_token"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {...},
        "redirect": "?page=dashboard"
    }
}
```

#### `POST api/auth.php?action=register`

Create new user account.

**Request:**
```json
{
    "name": "Full Name",
    "email": "user@example.com",
    "password": "secure_password",
    "master_password": "encryption_key",
    "csrf_token": "csrf_token"
}
```

### Task Management Endpoints

#### `GET api/tasks.php?projectId={id}`

List tasks with optional filters.

**Query Parameters:**
- `projectId` - Filter by project
- `status` - Filter by status (todo, in_progress, done)
- `priority` - Filter by priority (low, medium, high, urgent)

#### `POST api/tasks.php?action=add`

Create new task.

**Request:**
```json
{
    "title": "Task title",
    "description": "Task description",
    "projectId": "uuid",
    "status": "todo",
    "priority": "medium",
    "dueDate": "2026-03-30",
    "estimatedMinutes": 120,
    "csrf_token": "csrf_token"
}
```

#### `PUT api/tasks.php?id={id}`

Update existing task.

#### `DELETE api/tasks.php?id={id}`

Delete task.

### Project Endpoints

#### `GET api/projects.php?action=list`

List all projects.

#### `POST api/projects.php?action=create`

Create new project.

**Request:**
```json
{
    "name": "Project Name",
    "description": "Project description",
    "clientId": "uuid",
    "status": "active",
    "color": "#3B82F6",
    "csrf_token": "csrf_token"
}
```

### Invoice Endpoints

#### `POST api/invoices.php?action=create`

Create standard invoice.

**Request:**
```json
{
    "clientId": "uuid",
    "projectId": "uuid",
    "lineItems": [
        {
            "description": "Service description",
            "quantity": 1,
            "unitPrice": 100.00
        }
    ],
    "dueDate": "2026-04-30",
    "currency": "USD",
    "csrf_token": "csrf_token"
}
```

### AI Agent Endpoints

#### `POST api/ai-agent.php?action=chat`

Send message to AI agent with function calling.

**Request:**
```json
{
    "message": "Create a project for website design",
    "provider": "groq",
    "model": "llama-3.3-70b-versatile",
    "stream": false
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "response": "I've created the project...",
        "actions": [
            {
                "function": "create_project",
                "result": {...}
            }
        ]
    }
}
```

---

## Core Classes

### Database Class

**Location:** `includes/Database.php`

The Database class handles all JSON file storage with encryption.

```php
$db = new Database($masterPassword);

// Load all records
$projects = $db->load('projects');

// Find by ID
$project = $db->findById('projects', $id);

// Create new record
$db->insert('projects', $data);

// Update record
$db->update('projects', $id, $updates);

// Delete record
$db->delete('projects', $id);
```

### Encryption Class

**Location:** `includes/Encryption.php`

AES-256-GCM encryption for all user data.

```php
$encryption = new Encryption($masterPassword);

// Encrypt data
$encrypted = $encryption->encrypt($sensitiveData);

// Decrypt data
$decrypted = $encryption->decrypt($encrypted);

// Password hashing
$hash = Encryption::hashPassword($password);
$valid = Encryption::verifyPassword($input, $hash);
```

### Auth Class

**Location:** `includes/Auth.php`

Session management and authentication.

```php
// Check if logged in
Auth::check();

// Get current user ID
$userId = Auth::userId();

// Get current user info
$user = Auth::user();

// Validate CSRF token
if (Auth::validateCsrf($token)) {
    // Process form
}

// Login
$auth = new Auth($db);
$result = $auth->login($email, $password);

// Logout
$auth->logout();
```

### AIAgent Class

**Location:** `includes/AIAgent.php`

AI agent orchestration with function calling.

```php
$agent = new AIAgent($db, $groq, $openrouter);

// Send message to AI
$response = $agent->chat([
    'role' => 'user',
    'content' => 'Create a task for tomorrow'
]);

// Clear conversation history
$agent->clearHistory();

// Set custom system prompt
$agent->setSystemPrompt('You are a helpful assistant...');
```

---

## Features

### Task & Project Management

- **Nested subtasks** with time estimates
- **Priority levels:** low, medium, high, urgent
- **Status workflow:** backlog → todo → in_progress → review → done
- **Task templates** for reuse
- **Kanban board** visualization
- **Time tracking** with entries

### CRM & Invoicing

- **Client management** with contact information
- **Standard invoices** with line items
- **Advanced invoice generator** with custom fields
- **Multi-currency support** (USD, EUR, GBP, ZAR)
- **PDF generation** with html2pdf
- **Invoice status tracking**

### Productivity Tools

| Feature | Description |
|---------|-------------|
| **Habit Tracker** | Daily, weekly, monthly habits with streaks |
| **Water Tracker** | AI-generated hydration plans |
| **Pomodoro Timer** | 25/5/15 minute sessions |
| **Calendar View** | Monthly calendar interface |
| **Notes** | Tag-based organization with AI editing |

### Knowledge Base

- **Folder organization**
- **File upload** (Markdown, XML, images)
- **Batch text upload** without base64
- **Full-text search**
- **AI RAG integration**

---

## Security

### Encryption

- **Algorithm:** AES-256-GCM
- **Key Derivation:** SHA-256 from master password
- **Storage:** Base64 encoded (IV + Tag + Encrypted data)

All user data in `/data/users/{userId}/` is encrypted with the user's master password.

### Authentication

- **Passwords:** Bcrypt hashing with cost 12
- **Sessions:** HTTP-only cookies, SameSite=Strict
- **CSRF:** 32-byte token validation on all POST/PUT/DELETE
- **Rate Limiting:** 5 login attempts per 15 minutes

### Authorization

- Role-based access (admin, user)
- Email verification (optional for hosted deployments)
- Password reset (optional for hosted deployments)
- MCP API token authentication

---

## MCP Integration

### What is MCP?

Model Context Protocol (MCP) allows AI coding agents like Claude Code to directly interact with OpenPlan Work.

### MCP Server

**Location:** `mcp-server/index.js`

### Configuration

Add to your Claude Code settings (`.claude/settings.json`):

```json
{
    "mcpServers": {
        "openplan-work": {
            "command": "node",
            "args": ["C:/MAMP/htdocs/taskmanager/mcp-server/index.js"],
            "env": {
                "API_URL": "http://localhost/taskmanager/api",
                "MCP_API_TOKEN": "your_user_token_here"
            }
        }
    }
}
```

### Getting Your Token

1. Login to OpenPlan Work
2. Navigate to **Settings > Developer Tools > MCP Config**
3. Click **"Generate Token"** (or copy existing)
4. Paste into your Claude Code settings

### Available MCP Tools

- `lazyman_add_todo` / `lazyman_complete_todo`
- `lazyman_add_task` / `lazyman_update_task`
- `lazyman_add_project` / `lazyman_list_projects`
- `lazyman_add_client` / `lazyman_list_clients`
- `lazyman_create_invoice` / `lazyman_list_invoices`
- `lazyman_add_transaction` / `lazyman_get_finance_summary`
- `lazyman_create_note` / `lazyman_search_notes`
- `lazyman_batch_upload_kb_text` (easy text upload)
- And 50+ more tools covering all features

---

## Development Guide

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Static analysis
composer qa:stan

# Code style check
composer qa:cs
```

### Development Server

```bash
# Start PHP built-in server
php start_server.php

# Windows
start_server.bat
```

### Debug Mode

Enable in `config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Error Log

```
/data/php_error.log
```

---

## Troubleshooting

### Common Issues

#### Decryption Failed

**Cause:** Wrong master password
**Solution:** Log out and log in with correct master password

#### 500 Internal Server Error

**Cause:** PHP error
**Solution:** Check `/data/php_error.log` for root cause

#### Missing cURL Extension

**Cause:** php_curl not enabled
**Solution:** Enable `php_curl` in `php.ini`

#### API Key Used as Model

**Cause:** Model setting contains API key instead of model ID
**Solution:** Keep API keys in Settings → AI API Keys, use model IDs in Model Settings

### Getting Help

- **Documentation:** `docs/OpenPlan-Work-User-Guide.md`
- **Issues:** https://github.com/naijagamerx/openplan.work/issues
- **Developer Guide:** `CLAUDE.md`

---

## Data Models

### Project Model

```json
{
    "id": "uuid",
    "name": "Project Name",
    "description": "Project description",
    "clientId": "uuid",
    "status": "active|completed|on-hold|cancelled",
    "color": "#3B82F6",
    "createdAt": "2026-03-22T12:00:00Z",
    "updatedAt": "2026-03-22T12:00:00Z",
    "tasks": [...]
}
```

### Client Model

```json
{
    "id": "uuid",
    "name": "Client Name",
    "email": "client@example.com",
    "phone": "+1234567890",
    "company": "Company Name",
    "address": {
        "street": "123 Main St",
        "city": "City",
        "state": "State",
        "zip": "12345",
        "country": "Country"
    },
    "notes": "Additional notes",
    "createdAt": "2026-03-22T12:00:00Z",
    "updatedAt": "2026-03-22T12:00:00Z"
}
```

### Invoice Model

```json
{
    "id": "uuid",
    "invoiceNumber": "2024-0001",
    "clientId": "uuid",
    "projectId": "uuid",
    "lineItems": [
        {
            "description": "Service description",
            "quantity": 1,
            "unitPrice": 100.00,
            "total": 100.00
        }
    ],
    "subtotal": 100.00,
    "taxRate": 0.10,
    "taxAmount": 10.00,
    "total": 110.00,
    "currency": "USD",
    "status": "draft|sent|paid|overdue|cancelled",
    "dueDate": "2026-04-30",
    "issueDate": "2026-03-22",
    "createdAt": "2026-03-22T12:00:00Z",
    "updatedAt": "2026-03-22T12:00:00Z"
}
```

---

## License & Credits

**License:** MIT
**Author:** naijagamerx
**Repository:** https://github.com/naijagamerx/openplan.work

---

*This documentation was automatically generated on March 22, 2026.*
