# OpenPlan Work

OpenPlan Work is a highly advanced, encrypted PHP workspace designed for teams and solo builders. It is not just a task manager—it is a comprehensive business management suite deeply integrated with autonomous AI agents and built-in Model Context Protocol (MCP) capabilities.

Core philosophy:
- Local-first, cloud-ready: run locally in one step and deploy online with environment-based controls.
- Composable: use only the modules you need (tasks, finance, invoices, habits, notes, AI, MCP).
- Data ownership first: military-grade AES-256-GCM encrypted storage, user-scoped settings, and export workflows for clean hosted releases.
- Collaboration-ready: built for admin-managed multi-user setups and open-source contribution.

![OpenPlan Dashboard Preview](assets/images/github-preview.png)

## 🚀 Powerful Capabilities

OpenPlan Work is designed to be controlled via traditional UI, API, or autonomous AI.

### 🤖 Autonomous AI Agent & Orchestrator
The built-in AI Assistant isn't just a chatbot—it is an autonomous agent with function-calling capabilities. 
- Powered by Groq or OpenRouter (Claude 3.5, GPT-4o, etc.).
- **Full System Control**: Can autonomously create clients, manage projects, add tasks, generate invoices, log financial transactions, and write notes based on natural language requests.
- **RAG-Lite Integration**: Automatically reads from your Knowledge Base folders to provide context-aware answers.

### 🔌 Native Model Context Protocol (MCP) Server
OpenPlan Work includes a built-in MCP server, allowing external coding agents (like Claude Code, Cursor, or Trae) to interact directly with your workspace.
- **Direct Data Access**: Your local or hosted AI coding tools can fetch tasks, update project statuses, and read specifications directly from your OpenPlan instance via stdio.
- **Secure Bridge**: Authenticates seamlessly using your admin email and master password.

### 💼 Comprehensive Business Suite
- **CRM & Project Management**: Manage clients, multi-tiered projects, subtasks, priorities, and time estimates.
- **Financial Engine**: Generate standard and advanced multi-currency invoices (USD, EUR, GBP, ZAR), track revenue/expenses, and manage inventory SKUs.
- **Productivity & Health**: Integrated Kanban boards, Habit Trackers, Pomodoro timers, and an AI-driven Hydration Tracker.

### 🔒 Zero-Knowledge Security Architecture
- **AES-256-GCM Encryption**: All data at rest in the `data/` directory is encrypted.
- **Flat-File JSON Storage**: No SQL database required. Portable, secure, and incredibly fast.
- **Robust Protection**: Includes CSRF protection, Bcrypt password hashing, session-based auth, and `.htaccess` data protection.

## Requirements

- PHP 8.0 or newer
- `json`, `mbstring`, `curl`, and `openssl` extensions

## Local Run

- `php start_server.php`
- or use `start_server.bat` on Windows

## Hosted Configuration

Hosted-only auth and image-service switches are environment-controlled.

- [Hosted setup](docs/HOSTED_SETUP.md)
- Example env file: [.env.example](.env.example)

## Supported Models

Current AI providers in this project are Groq and OpenRouter.

| Provider | Models |
|---|---|
| Groq | `llama-3.3-70b-versatile`, `llama-3.1-8b-instant`, and any Groq model ID configured in settings |
| OpenRouter | Any OpenRouter model ID you configure (for example Anthropic, OpenAI, Google, and other routed models) |

## Clean Release Export

Maintainers can generate a clean zip for GitHub releases or deployment handoff without shipping live data, secrets, agent folders, or local test/debug artifacts.

- [Export workflow](docs/EXPORT_RELEASE.md)

## Notes

- The export pipeline regenerates an empty `data/` folder in the release artifact.
- `includes/master_password.php` and all live `data/` contents are excluded from the export.
- If you plan to publish this publicly, add a `LICENSE` file before treating it as a true open-source release.
