# LazyMan Tools - Technical Requirements

## 1. System Requirements

### 1.1 Server Requirements
```
PHP Version:       >= 8.0
Extensions:        openssl, json, mbstring, zip
Memory Limit:      >= 128MB
Max Upload Size:   >= 10MB (for data imports)
```

### 1.2 Browser Requirements
| Browser | Minimum Version |
|---------|-----------------|
| Chrome | 90+ |
| Firefox | 88+ |
| Safari | 14+ |
| Edge | 90+ |

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENT (Browser)                         │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────────┐  │
│  │  HTML/CSS   │  │  JavaScript  │  │  Tailwind CSS     │  │
│  │  Views      │  │  App Logic   │  │  (CDN)            │  │
│  └──────┬──────┘  └──────┬───────┘  └───────────────────┘  │
└─────────┼────────────────┼─────────────────────────────────┘
          │                │
          ▼                ▼
┌─────────────────────────────────────────────────────────────┐
│                     SERVER (PHP)                             │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────────┐  │
│  │  Router     │  │  Controllers │  │  Helpers          │  │
│  │  (index.php)│  │  (api/*.php) │  │  (includes/*.php) │  │
│  └──────┬──────┘  └──────┬───────┘  └───────────────────┘  │
│         │                │                                   │
│         ▼                ▼                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Encryption Layer                        │    │
│  │              (AES-256-GCM)                          │    │
│  └─────────────────────────┬───────────────────────────┘    │
└────────────────────────────┼────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                     DATA LAYER                               │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────────┐  │
│  │  config.enc │  │  users.enc   │  │  projects.enc     │  │
│  │  clients.enc│  │  invoices.enc│  │  finance.enc      │  │
│  │  inventory  │  │  templates   │  │  backups/         │  │
│  └─────────────┘  └──────────────┘  └───────────────────┘  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   EXTERNAL SERVICES                          │
│  ┌─────────────────────────┐  ┌────────────────────────┐    │
│  │  Groq API               │  │  OpenRouter API        │    │
│  │  (chat/completions)     │  │  (multi-model access)  │    │
│  └─────────────────────────┘  └────────────────────────┘    │
│  ┌─────────────────────────┐                                │
│  │  SMTP Server            │                                │
│  │  (email delivery)       │                                │
│  └─────────────────────────┘                                │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Security Implementation

### 3.1 Password Hashing
```php
// Registration
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Login
if (password_verify($inputPassword, $storedHash)) {
    // Success
}
```

### 3.2 Data Encryption
```php
class Encryption {
    private const CIPHER = 'aes-256-gcm';
    
    public static function encrypt(string $data, string $key): string {
        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    public static function decrypt(string $encryptedData, string $key): string|false {
        $decoded = base64_decode($encryptedData);
        $iv = substr($decoded, 0, 16);
        $tag = substr($decoded, 16, 16);
        $encrypted = substr($decoded, 32);
        
        return openssl_decrypt(
            $encrypted,
            self::CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }
}
```

### 3.3 Session Security
```php
// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1 hour

// Regenerate session ID on login
session_regenerate_id(true);
```

### 3.4 CSRF Protection
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validate token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Invalid CSRF token');
}
```

---

## 4. API Endpoint Specifications

### 4.1 Response Format
```json
{
    "success": true,
    "data": {},
    "message": "Operation completed",
    "errors": [],
    "timestamp": "2024-12-30T10:00:00Z"
}
```

### 4.2 Error Codes
| Code | Meaning |
|------|---------|
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Not logged in |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 422 | Validation Error - Input validation failed |
| 500 | Server Error - Internal error |

### 4.3 Endpoints

#### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth.php?action=login` | User login |
| POST | `/api/auth.php?action=logout` | User logout |
| POST | `/api/auth.php?action=register` | User registration |
| GET | `/api/auth.php?action=status` | Check session status |

#### Tasks
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tasks.php` | List all tasks |
| GET | `/api/tasks.php?id={id}` | Get single task |
| POST | `/api/tasks.php` | Create task |
| PUT | `/api/tasks.php?id={id}` | Update task |
| DELETE | `/api/tasks.php?id={id}` | Delete task |

#### Projects
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/projects.php` | List all projects |
| GET | `/api/projects.php?id={id}` | Get single project |
| POST | `/api/projects.php` | Create project |
| PUT | `/api/projects.php?id={id}` | Update project |
| DELETE | `/api/projects.php?id={id}` | Delete project |

#### AI
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/ai.php?action=generate_tasks` | Generate tasks from description |
| POST | `/api/ai.php?action=generate_prd` | Generate PRD |
| POST | `/api/ai.php?action=chat` | General AI chat |

#### Export/Import
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/export.php?format=json` | Download JSON backup |
| GET | `/api/export.php?format=zip` | Download ZIP backup |
| POST | `/api/export.php?action=import` | Import backup file |

---

## 5. Data Models (JSON Schema)

### 5.1 User
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "id": { "type": "string", "format": "uuid" },
        "email": { "type": "string", "format": "email" },
        "passwordHash": { "type": "string" },
        "name": { "type": "string" },
        "createdAt": { "type": "string", "format": "date-time" },
        "lastLogin": { "type": "string", "format": "date-time" }
    },
    "required": ["id", "email", "passwordHash", "name"]
}
```

### 5.2 Task
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "id": { "type": "string", "format": "uuid" },
        "projectId": { "type": "string", "format": "uuid" },
        "parentId": { "type": "string", "format": "uuid", "nullable": true },
        "title": { "type": "string", "maxLength": 255 },
        "description": { "type": "string" },
        "status": { 
            "type": "string",
            "enum": ["backlog", "todo", "in_progress", "review", "done"]
        },
        "priority": {
            "type": "string",
            "enum": ["low", "medium", "high", "urgent"]
        },
        "dueDate": { "type": "string", "format": "date-time", "nullable": true },
        "estimatedMinutes": { "type": "integer", "minimum": 0 },
        "actualMinutes": { "type": "integer", "minimum": 0 },
        "completedAt": { "type": "string", "format": "date-time", "nullable": true },
        "createdAt": { "type": "string", "format": "date-time" },
        "updatedAt": { "type": "string", "format": "date-time" }
    },
    "required": ["id", "title", "status", "priority"]
}
```

### 5.3 Invoice
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "id": { "type": "string", "format": "uuid" },
        "invoiceNumber": { "type": "string" },
        "clientId": { "type": "string", "format": "uuid" },
        "projectId": { "type": "string", "format": "uuid", "nullable": true },
        "lineItems": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "description": { "type": "string" },
                    "quantity": { "type": "number" },
                    "unitPrice": { "type": "number" },
                    "total": { "type": "number" }
                }
            }
        },
        "subtotal": { "type": "number" },
        "taxRate": { "type": "number" },
        "taxAmount": { "type": "number" },
        "total": { "type": "number" },
        "currency": { "type": "string", "default": "USD" },
        "status": {
            "type": "string",
            "enum": ["draft", "sent", "paid", "overdue", "cancelled"]
        },
        "dueDate": { "type": "string", "format": "date" },
        "notes": { "type": "string" },
        "createdAt": { "type": "string", "format": "date-time" }
    },
    "required": ["id", "invoiceNumber", "clientId", "lineItems", "status"]
}
```

---

## 6. External API Integration

### 6.1 Groq API
```php
class GroqAPI {
    private string $apiKey;
    private string $baseUrl = 'https://api.groq.com/openai/v1';
    
    public function chatCompletion(array $messages, string $model = 'llama-3.3-70b-versatile'): array {
        $ch = curl_init($this->baseUrl . '/chat/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2048
            ])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

### 6.2 OpenRouter API
```php
class OpenRouterAPI {
    private string $apiKey;
    private string $baseUrl = 'https://openrouter.ai/api/v1';
    
    public function chatCompletion(array $messages, string $model = 'anthropic/claude-3.5-sonnet'): array {
        $ch = curl_init($this->baseUrl . '/chat/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://lazyman-tools.local',
                'X-Title: LazyMan Tools',
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages
            ])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function getAvailableModels(): array {
        return [
            'anthropic/claude-3.5-sonnet',
            'openai/gpt-4-turbo',
            'meta-llama/llama-3.3-70b-instruct',
            'google/gemini-2.0-flash-exp:free',
            'deepseek/deepseek-chat'
        ];
    }
}
```

---

## 7. AI Prompt Templates

### 7.1 Task Generation
```
System: You are a project planning expert. Generate a detailed task breakdown with subtasks, time estimates, and priorities.

Format your response as JSON:
{
    "tasks": [
        {
            "title": "Task name",
            "description": "Detailed description",
            "priority": "high|medium|low",
            "estimatedMinutes": 60,
            "subtasks": [
                {
                    "title": "Subtask name",
                    "estimatedMinutes": 30
                }
            ]
        }
    ]
}

User: {project_description}
```

### 7.2 PRD Generation
```
System: You are a senior product manager. Create a comprehensive PRD document with the following sections: Overview, Goals, Target Users, Features, Technical Requirements, and Success Metrics.

User: {project_idea}
```

---

## 8. Email Configuration

### 8.1 SMTP Setup
```php
class Mailer {
    private array $config;
    
    public function __construct(array $smtpConfig) {
        $this->config = $smtpConfig;
    }
    
    public function send(string $to, string $subject, string $body): bool {
        // Using built-in mail() or SMTP library
        $headers = [
            'From' => $this->config['from_email'],
            'Reply-To' => $this->config['reply_to'],
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, $headers);
    }
}
```

---

## 9. Performance Optimization

### 9.1 Caching Strategy
- Session-based caching for frequently accessed data
- In-memory caching for current session
- Lazy loading for large data sets

### 9.2 JSON File Handling
- Stream reading for large JSON files
- Batch writes to minimize I/O operations
- Incremental updates instead of full rewrites

---

## 10. Testing Requirements

### 10.1 Unit Tests
- Test all encryption/decryption functions
- Test API endpoint handlers
- Test validation logic

### 10.2 Integration Tests
- Test full user workflows
- Test data import/export
- Test AI integration

### 10.3 Manual Testing
- Browser compatibility testing
- Responsive design testing
- Performance testing

---

*Document Version: 1.0.0*
