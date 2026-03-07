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

## Key Files Structure
```
/
├── index.php              # Main router and entry point
├── config.php             # App configuration and initialization
├── includes/              # Core PHP classes
│   ├── Database.php       # JSON file handling with encryption
│   ├── Encryption.php     # AES-256-GCM encryption/decryption
│   ├── Auth.php           # Session management and authentication
│   ├── GroqAPI.php        # Groq AI integration
│   ├── OpenRouterAPI.php  # OpenRouter AI integration
│   ├── Mailer.php         # Email functionality
│   └── Helpers.php        # Utility functions
├── api/                   # REST API endpoints
│   ├── auth.php           # Authentication endpoints
│   ├── tasks.php          # Task CRUD operations
│   ├── projects.php       # Project CRUD operations
│   ├── clients.php        # CRM operations
│   ├── invoices.php       # Invoice management
│   ├── finance.php        # Finance tracking
│   ├── inventory.php      # Stock management
│   ├── ai.php             # AI integration endpoints
│   └── export.php         # Data import/export
├── views/                 # HTML templates
│   ├── layouts/           # Main layouts (main.php, auth.php)
│   ├── partials/          # Reusable components (sidebar, header)
│   └── pages/             # Individual page views
├── assets/                # Static assets
│   ├── css/               # Custom CSS
│   └── js/                # Frontend JavaScript
├── data/                  # Encrypted JSON storage (.htaccess protected)
│   ├── *.json.enc         # Encrypted data files
│   └── backups/           # Automatic backups
├── templates/             # Email/Invoice templates
└── docs/                  # Documentation
```

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
# Run encryption test
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

# Test database operations directly:
# Create a test script in root directory
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

### Authentication
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=logout` - User logout
- `POST /api/auth.php?action=register` - User registration
- `GET /api/auth.php?action=status` - Check session status

### Tasks & Projects
- `GET /api/tasks.php` - List all tasks (with filters for status, priority, project_id)
- `POST /api/tasks.php` - Create task
- `PUT /api/tasks.php?id={id}` - Update task
- `DELETE /api/tasks.php?id={id}` - Delete task
- `GET /api/projects.php` - List projects
- `POST /api/projects.php` - Create project
- `PUT /api/projects.php?id={id}` - Update project
- `DELETE /api/projects.php?id={id}` - Delete project

### Business Management Features
- `GET/POST/PUT/DELETE /api/clients.php` - CRM operations (client management)
- `GET/POST/PUT/DELETE /api/invoices.php` - Invoice management (with PDF generation)
- `GET/POST/PUT/DELETE /api/finance.php` - Finance tracking (expenses, revenue)
- `GET/POST/PUT/DELETE /api/inventory.php` - Inventory management (stock, reorder points)

### Time Management Features
- `GET /api/tasks.php?action=time_entries` - Time tracking data
- `POST /api/tasks.php?action=time_entry` - Add time entry to task
- Pomodoro timer functionality integrated in JavaScript frontend

### AI Integration
- `POST /api/ai.php?action=generate_tasks` - Generate tasks from project descriptions
- `POST /api/ai.php?action=generate_prd` - Generate product requirement documents
- `POST /api/ai.php?action=chat` - General AI chat assistant
- `POST /api/ai.php?action=generate_project_tasks` - AI project breakdown
- **AI Providers**: Support for both Groq and OpenRouter APIs

### Data Management
- `GET /api/export.php?format=json` - Export all data as JSON
- `GET /api/export.php?format=zip` - Export all data as ZIP backup
- `POST /api/export.php?action=import` - Import data from JSON/ZIP
- Backup functionality with automatic backup creation

### Settings and Configuration
- `GET /api/auth.php?action=config` - Load user configuration
- `POST /api/auth.php?action=update_config` - Update business settings
- Business information, API keys, and preferences management

## Frontend Architecture

### JavaScript Architecture
**Single File Architecture**: All frontend JavaScript in `/assets/js/app.js` with modular organization:
- **API Helper Module**: Centralized `api` object for all HTTP requests
- **UI Utilities**: Toast notifications, modal management, form handling
- **Helper Functions**: Formatting (currency, dates, timeAgo), utilities (debounce)
- **Confirmation System**: `confirmAction(message, onConfirm)` for user confirmations
- **Event Handling**: DOM event delegation for dynamic content

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

### UI Components and Utilities
- **Toast notifications**: `showToast(message, type)` - success, error, warning, info
- **Modals**: `openModal(htmlContent)`, `closeModal()` - dynamic modal system
- **Forms**: JavaScript-enhanced form handling with CSRF tokens
- **Utility functions**:
  - `formatCurrency(amount, currency)` - Currency formatting with symbols (USD $, EUR €, etc.)
  - `formatDate(dateStr)` - Date formatting
  - `timeAgo(dateStr)` - Relative time formatting
  - `debounce(func, wait)` - Function rate limiting
- **Confirmation dialogs**: `confirmAction(message, onConfirm)` - Safe action confirmations

### Frontend Integration with Backend
- **Global Configuration**: `APP_URL` and `CSRF_TOKEN` passed from PHP to JavaScript in `/views/layouts/main.php`
- **API Integration**: All backend communication through centralized `api` helper
- **Security**: CSRF tokens included in all write requests
- **Data Flow**: PHP generates initial HTML, JavaScript provides interactive features

### External Libraries and Assets
- **Tailwind CSS**: CDN version with custom configuration
- **Chart.js**: Data visualization for finance charts
- **Sortable.js**: Drag-and-drop functionality (Kanban boards)
- **html2pdf**: PDF generation for invoices and reports
- **Heroicons**: SVG icons for UI components

### Custom CSS Classes
- Design system uses Tailwind CSS with custom extensions
- Priority badges: `bg-red-100 text-red-800`, `bg-orange-100 text-orange-800`, etc.
- Status badges: `bg-green-100 text-green-800` for completed, etc.
- Animation classes: `animate-fade-in`, hover effects
- Layout classes: Sidebar (256px), responsive breakpoints

## Data Models

### Encrypted Collections
Each collection is stored as encrypted JSON in `/data/`:
- `users.json.enc` - User accounts with bcrypt password hashes
- `projects.json.enc` - Projects with embedded tasks (structured as parent container)
- `clients.json.enc` - CRM contact information with business details
- `invoices.json.enc` - Invoice records with line items, totals, and payment status
- `finance.json.enc` - Financial records including expenses and revenue
- `inventory.json.enc` - Stock items with SKU, pricing, and quantity tracking
- `config.json.enc` - System configuration including business info and API keys
- `templates/` - Not used (templates are inline in code)

### Project Structure (with embedded tasks)
```json
{
    "id": "uuid",
    "name": "Project Name",
    "description": "Project description",
    "clientId": "uuid", // Reference to client
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
            "subtasks": [
                {
                    "title": "string",
                    "completed": "boolean",
                    "estimatedMinutes": 0
                }
            ],
            "timeEntries": [
                {
                    "date": "ISO timestamp",
                    "minutes": "integer",
                    "description": "string"
                }
            ],
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
    "address": {
        "street": "Street address",
        "city": "City",
        "state": "State",
        "zip": "ZIP code",
        "country": "Country"
    },
    "notes": "Additional notes",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

### Invoice Structure
```json
{
    "id": "uuid",
    "invoiceNumber": "Formatted invoice number (e.g. 2024-0001)",
    "clientId": "uuid",
    "projectId": "uuid", // Optional project reference
    "lineItems": [
        {
            "description": "Item description",
            "quantity": "number",
            "unitPrice": "number",
            "total": "number"
        }
    ],
    "subtotal": "number",
    "taxRate": "number", // Percentage
    "taxAmount": "number",
    "total": "number",
    "currency": "USD|EUR|GBP|ZAR",
    "status": "draft|sent|paid|overdue|cancelled",
    "dueDate": "ISO date string",
    "issueDate": "ISO date string",
    "notes": "Additional notes",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

### Inventory Item Structure
```json
{
    "id": "uuid",
    "name": "Product name",
    "sku": "Stock keeping unit",
    "description": "Product description",
    "category": "Product category",
    "cost": "number", // Cost price
    "price": "number", // Selling price
    "quantity": "integer",
    "minQuantity": "integer", // Reorder point
    "supplier": "Supplier name",
    "notes": "Additional notes",
    "createdAt": "ISO timestamp",
    "updatedAt": "ISO timestamp"
}
```

### Finance Record Structure
```json
{
    "id": "uuid",
    "type": "expense|revenue",
    "category": "Expense/revenue category",
    "amount": "number",
    "currency": "USD|EUR|GBP|ZAR",
    "date": "ISO date string",
    "description": "Description of transaction",
    "projectId": "uuid", // Optional project reference
    "clientId": "uuid", // Optional client reference
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

### AI Prompts
- **Task Generation**: Creates structured task breakdowns with subtasks and estimates
- **PRD Generation**: Creates comprehensive product requirement documents
- **Chat**: General AI assistance

## Email/Mailer Functionality

### Mailer Class Implementation
- **File**: `/includes/Mailer.php` - Complete email handling system
- **Method**: Uses native PHP `mail()` function (no external libraries like PHPMailer currently integrated)
- **Template System**: Professional HTML template with inline CSS, responsive design, business branding
- **Business Integration**: Uses business configuration from encrypted `config.json.enc`
- **Features**: Send generic emails and dedicated invoice emails with proper headers

### Configuration and Settings
- **Storage**: Business email configuration stored in encrypted JSON (`/data/config.json.enc`)
- **Manageable Settings**: Business Name, Email, Phone, Address, Currency, Tax Rate
- **UI**: Configurable via Settings page (`/views/settings.php`)
- **Security**: Business settings encrypted at rest with master password
- **Email Headers**: Uses "From" and "Reply-To" based on business configuration

### Email Use Cases
- **Primary Use**: Invoice sending functionality (`sendInvoice()` method in Mailer class)
- **Template System**: Professional HTML email template with business branding
- **Current Status**: Mailer class is implemented but not integrated into UI workflows
- **PDF Integration**: Can combine with PDF generation for invoice attachments (planned)
- **Future Enhancements**: SMTP support, email queue, additional email types

### Email Architecture Notes
- **Transport**: Currently uses native PHP `mail()` function (requires local mail server)
- **Template Location**: Templates are inline in PHP code (not separate files)
- **Security**: XSS protection with proper escaping, CSRF protection on configuration
- **Design**: Professional monochrome theme (black/white) with responsive layout

## Common Development Tasks

### Adding a New Feature
1. Add API endpoint in `/api/`
2. Create Database collection methods if needed
3. Add frontend JavaScript functions
4. Create view template in `/views/`
5. Update sidebar navigation in `/views/partials/sidebar.php`

### Adding Email Functionality to Features
1. Ensure business configuration is loaded via Database class
2. Instantiate Mailer class: `new Mailer($config)`
3. Call appropriate method (`send()` or `sendInvoice()`)
4. Add UI elements to trigger email sending
5. Handle success/failure responses appropriately

### Debugging Data Issues
```php
// Temporarily decrypt data to debug
require_once 'config.php';
$db = new Database(getMasterPassword());
$data = $db->load('projects');
var_dump($data); // Inspect decrypted data
```

### Testing API Endpoints
```bash
# Test authentication
curl -X POST http://localhost/TaskManager/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test tasks endpoint (requires session cookie)
curl http://localhost/TaskManager/api/tasks.php \
  -H "Cookie: lazyman_session=<session_id>"
```

### Creating Backups
```php
$db = new Database(getMasterPassword());
$backup = $db->exportAll();
file_put_contents('backup_' . date('Y-m-d') . '.json', json_encode($backup));
```

## Important Notes

- **No build system**: Direct file editing, refresh browser to see changes
- **No package.json/composer.json**: Pure PHP + vanilla JS
- **Data encryption**: All data in `/data/` is encrypted with master password
- **Session dependency**: Requires active PHP session for authentication
- **CSRF required**: All non-GET API requests must include CSRF token
- **Environment variables**: Master password can be set via `LAZYMAN_MASTER_PASSWORD`
- **Error handling**: Check PHP error logs and browser console for debugging
- **Pomodoro timer**: Built-in time management system with work/break intervals (accessible via `/views/pomodoro.php`)
- **Kanban boards**: Drag-and-drop task management with Sortable.js integration
- **PDF generation**: Invoice PDFs generated client-side with html2pdf.js
- **Time tracking**: Task-specific time entries with estimated vs. actual tracking
- **Business management**: Complete suite including CRM, invoicing, finance, and inventory
- **Multi-currency**: Support for USD, EUR, GBP, ZAR with automatic symbol formatting
- **Embedded tasks**: Tasks are stored within project records rather than as separate entities
- **Automatic backups**: Backup files created in `/data/backups/` directory
- **AI integration**: Both Groq and OpenRouter API support for various business tasks

## First-Time Setup Flow
1. Navigate to `/?page=setup`
2. System will prompt for master password and admin user creation
3. Create first user account
4. Login with new credentials
5. System creates initial encrypted data files
6. Redirect to dashboard

## Project Version
Current: v1.0.0
- **Complete Business Suite**: Task management, CRM, invoicing, finance, inventory, and time tracking
- **Security-First Design**: End-to-end encryption with AES-256-GCM, session-based auth, CSRF protection
- **AI Integration**: Multiple AI provider support (Groq, OpenRouter) with specialized business tools
- **Modern UI**: Responsive design with Tailwind CSS, drag-and-drop Kanban, and professional templates
- **Offline-Ready**: Self-hosted with no external dependencies beyond PHP extensions
- **Scalable Architecture**: Modular design with clean separation of concerns
- **Time Management**: Built-in Pomodoro timer and detailed time tracking capabilities
- **Email System**: Complete but currently unused email infrastructure available (see Mailer.php notes)
- **No Breaking Changes Expected**: Stable API and data structure

## First-Time Setup Flow
1. Navigate to `/?page=setup`
2. System will prompt for master password and admin user creation
3. Create first user account
4. Login with new credentials
5. System creates initial encrypted data files
6. Redirect to dashboard