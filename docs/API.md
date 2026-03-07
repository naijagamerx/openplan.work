# LazyMan Tools - API Documentation

## Base URL
```
http://localhost/TaskManager/api/
```

## Authentication
All endpoints (except login) require session authentication.

---

## Endpoints

### Authentication

#### Login
```
POST /api/auth.php?action=login
Content-Type: application/json

Request:
{
    "email": "user@example.com",
    "password": "secret"
}

Response:
{
    "success": true,
    "message": "Login successful"
}
```

#### Logout
```
POST /api/auth.php?action=logout

Response:
{
    "success": true
}
```

---

### Tasks

#### List Tasks
```
GET /api/tasks.php?project_id={id}&status={status}

Response:
{
    "success": true,
    "data": [
        {
            "id": "uuid",
            "title": "Task Name",
            "status": "in_progress",
            "priority": "high"
        }
    ]
}
```

#### Create Task
```
POST /api/tasks.php
Content-Type: application/json

Request:
{
    "title": "New Task",
    "projectId": "project-uuid",
    "priority": "high",
    "dueDate": "2024-12-31"
}

Response:
{
    "success": true,
    "data": { "id": "new-uuid" }
}
```

#### Update Task
```
PUT /api/tasks.php?id={task-id}
Content-Type: application/json

Request:
{
    "status": "done"
}

Response:
{
    "success": true
}
```

#### Delete Task
```
DELETE /api/tasks.php?id={task-id}

Response:
{
    "success": true
}
```

---

### AI Integration

#### Generate Tasks
```
POST /api/ai.php?action=generate_tasks
Content-Type: application/json

Request:
{
    "description": "Build an e-commerce website",
    "provider": "groq",
    "model": "llama-3.3-70b-versatile"
}

Response:
{
    "success": true,
    "data": {
        "tasks": [
            {
                "title": "Setup Project",
                "estimatedMinutes": 60,
                "subtasks": []
            }
        ]
    }
}
```

#### Generate PRD
```
POST /api/ai.php?action=generate_prd
Content-Type: application/json

Request:
{
    "idea": "Mobile app for tracking habits",
    "provider": "openrouter"
}

Response:
{
    "success": true,
    "data": {
        "prd": "# Product Requirements Document..."
    }
}
```

---

### Export/Import

#### Export Data
```
GET /api/export.php?format=json
GET /api/export.php?format=zip

Response: File download
```

#### Import Data
```
POST /api/export.php?action=import
Content-Type: multipart/form-data

Request: File upload (backup.json or backup.zip)

Response:
{
    "success": true,
    "message": "Data imported successfully"
}
```

---

## Error Responses

```json
{
    "success": false,
    "error": "Error message",
    "code": 400
}
```

| Code | Meaning |
|------|---------|
| 400 | Bad Request |
| 401 | Unauthorized |
| 404 | Not Found |
| 500 | Server Error |

---

*API v1.0.0*
