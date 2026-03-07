# LazyMan Tools - System Architecture

## 1. High-Level Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                        CLIENT                                 │
│  ┌─────────┐  ┌──────────────┐  ┌─────────────────────────┐  │
│  │  Views  │  │  JavaScript  │  │  Tailwind CSS (CDN)     │  │
│  │  (HTML) │  │  (App Logic) │  │  + Custom CSS           │  │
│  └────┬────┘  └──────┬───────┘  └─────────────────────────┘  │
└───────┼──────────────┼───────────────────────────────────────┘
        │              │
        ▼              ▼
┌──────────────────────────────────────────────────────────────┐
│                        SERVER                                 │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  index.php (Router)                                     │ │
│  └─────────────────────────────┬───────────────────────────┘ │
│                                │                              │
│  ┌─────────────────────────────▼───────────────────────────┐ │
│  │  API Layer (/api/*.php)                                 │ │
│  │  ├── auth.php        - Authentication                   │ │
│  │  ├── tasks.php       - Task CRUD                        │ │
│  │  ├── projects.php    - Project CRUD                     │ │
│  │  ├── clients.php     - CRM operations                   │ │
│  │  ├── invoices.php    - Invoice operations               │ │
│  │  ├── finance.php     - Finance tracking                 │ │
│  │  ├── inventory.php   - Stock management                 │ │
│  │  ├── ai.php          - AI integrations                  │ │
│  │  └── export.php      - Data import/export               │ │
│  └─────────────────────────────┬───────────────────────────┘ │
│                                │                              │
│  ┌─────────────────────────────▼───────────────────────────┐ │
│  │  Core Classes (/includes/*.php)                         │ │
│  │  ├── Database.php    - JSON file handler                │ │
│  │  ├── Encryption.php  - AES-256-GCM                      │ │
│  │  ├── Auth.php        - Session management               │ │
│  │  ├── GroqAPI.php     - Groq integration                 │ │
│  │  ├── OpenRouterAPI.php - OpenRouter integration         │ │
│  │  └── Mailer.php      - Email functionality              │ │
│  └─────────────────────────────┬───────────────────────────┘ │
└────────────────────────────────┼─────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────┐
│                      DATA LAYER                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  /data/ (Protected directory)                           │ │
│  │  ├── config.json.enc     - App configuration            │ │
│  │  ├── users.json.enc      - User accounts                │ │
│  │  ├── projects.json.enc   - Projects & tasks             │ │
│  │  ├── clients.json.enc    - CRM data                     │ │
│  │  ├── invoices.json.enc   - Invoices                     │ │
│  │  ├── finance.json.enc    - Financial records            │ │
│  │  ├── inventory.json.enc  - Stock data                   │ │
│  │  └── backups/            - Automatic backups            │ │
│  └─────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

---

## 2. Request Flow

```
1. User Action (click, form submit)
        │
        ▼
2. JavaScript handles event
        │
        ▼
3. Fetch API call to /api/*.php
        │
        ▼
4. Router validates session & CSRF
        │
        ▼
5. API handler processes request
        │
        ▼
6. Database class loads/saves encrypted JSON
        │
        ▼
7. JSON response returned
        │
        ▼
8. JavaScript updates DOM
```

---

## 3. Directory Structure

```
TaskManager/
├── index.php              # Entry point
├── config.php             # Configuration
├── .htaccess              # URL rewriting
│
├── api/                   # REST endpoints
├── includes/              # PHP classes
├── views/                 # HTML templates
│   ├── layouts/
│   └── partials/
├── assets/
│   ├── css/
│   └── js/
├── data/                  # Encrypted storage
│   └── backups/
├── templates/             # Email/Invoice templates
└── docs/                  # Documentation
```

---

## 4. Security Layers

1. **Authentication**: Session-based with bcrypt passwords
2. **Encryption**: AES-256-GCM for all stored data
3. **CSRF**: Token validation on all POST requests
4. **XSS**: Output escaping in all views
5. **File Access**: .htaccess blocks /data/ directory

---

## 5. External Integrations

### Groq API
- Endpoint: `https://api.groq.com/openai/v1/chat/completions`
- Models: llama-3.3-70b-versatile, mixtral-8x7b-32768

### OpenRouter API
- Endpoint: `https://openrouter.ai/api/v1/chat/completions`
- Models: claude-3.5-sonnet, gpt-4-turbo, llama-3.3-70b

### SMTP
- PHPMailer or native mail() function
- User-configurable SMTP settings

---

## 6. Data Flow Diagram

```
┌──────────┐     ┌──────────┐     ┌──────────┐
│  Create  │────▶│ Encrypt  │────▶│  Store   │
│   Data   │     │  (AES)   │     │  (JSON)  │
└──────────┘     └──────────┘     └──────────┘
                                       │
                                       ▼
┌──────────┐     ┌──────────┐     ┌──────────┐
│  Return  │◀────│ Decrypt  │◀────│   Load   │
│   Data   │     │  (AES)   │     │  (JSON)  │
└──────────┘     └──────────┘     └──────────┘
```

---

*Architecture v1.0.0*
