# OpenPlan Work

OpenPlan Work helps teams run tasks, projects, invoices, AI workflows, and MCP integrations from one PHP codebase with desktop and mobile entry points.

Core philosophy:
- Local-first, cloud-ready: run locally in one step and deploy online with environment-based controls.
- Composable: use only the modules you need (tasks, finance, invoices, habits, notes, AI, MCP).
- Data ownership first: encrypted storage, user-scoped settings, and export workflows for clean hosted releases.
- Collaboration-ready: built for admin-managed multi-user setups and open-source contribution.

![OpenPlan Dashboard Preview](assets/images/github-preview.png)

## Requirements

- PHP 8.0 or newer
- `json`, `mbstring`, and `openssl` extensions

## Local Run

- `php start_server.php`
- or use `start_server.bat` on Windows

## Hosted Configuration

Hosted-only auth and image-service switches are environment-controlled.

- [Hosted setup](docs/HOSTED_SETUP.md)
- Example env file: [.env.example](C:\MAMP\htdocs\taskmanager\.env.example)

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
