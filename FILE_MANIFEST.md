# OpenPlan Work - File Manifest

This document serves as a protective manifest to track critical project files and prevent accidental deletion.

## Manifest Version
- **Created**: 2026-03-13
- **Total Files**: 196 PHP files + assets
- **Purpose**: Track critical files for integrity verification

## Critical File Inventory

### Root Directory (7 files)
| File | Purpose | Critical |
|------|---------|----------|
| config.php | Main configuration | YES |
| index.php | Application entry point | YES |
| manifest.php | App manifest | YES |
| migrate.php | Database migration | YES |
| mobile-logout.php | Mobile logout handler | YES |
| setup_models.php | Model setup script | YES |
| export.php | Data export utility | Medium |

### API Directory (36 files)
**Core APIs (MUST HAVE):**
| File | Purpose |
|------|---------|
| auth.php | Authentication endpoints |
| tasks.php | Task management API |
| projects.php | Project management API |
| clients.php | Client/CRM API |
| invoices.php | Invoice management API |
| finance.php | Finance tracking API |
| inventory.php | Inventory management API |
| habits.php | Habit tracker API |
| notes.php | Notes management API |
| users.php | User management API |
| settings.php | Application settings API |

**Advanced/Support APIs:**
| File | Purpose |
|------|---------|
| advanced-invoices.php | Advanced invoicing |
| ai-agent.php | AI agent endpoint |
| ai-generate.php | AI generation API |
| ai.php | AI integration API |
| attachments.php | File attachment handling |
| audit.php | Audit logging API |
| backup.php | Backup management |
| backup_settings.php | Backup configuration |
| cron.php | Cron job runner |
| data-recovery.php | Data recovery tools |
| deep-migration.php | Migration utilities |
| export.php | Data export API |
| favicon.php | Dynamic favicon |
| health.php | Health check endpoint |
| index.php | API index |
| knowledge-base.php | Knowledge base API |
| models.php | AI model settings |
| pomodoro.php | Pomodoro timer API |
| scheduler_bootstrap.php | Scheduler bootstrap |
| scheduler_config.php | Scheduler configuration |
| scheduler_status.php | Scheduler status |
| task-inventory-link.php | Task-inventory linking |
| todos.php | Todo management |
| water.php | Water tracker API |
| wipe-data.php | Data wiping utility |

### Includes Directory (27 files)
**Core Classes (MUST HAVE):**
| File | Purpose |
|------|---------|
| AIHelper.php | AI integration helper |
| Auth.php | Authentication class |
| Database.php | Database/JSON storage |
| Encryption.php | AES-256-GCM encryption |
| GroqAPI.php | Groq AI API client |
| Helpers.php | Utility functions |
| Mailer.php | Email functionality |
| OpenRouterAPI.php | OpenRouter AI client |

**Extended Classes:**
| File | Purpose |
|------|---------|
| AIAgent.php | AI agent functionality |
| AIFunctions.php | AI function definitions |
| AIVerifier.php | AI response verification |
| Attachment.php | File attachment handling |
| Audit.php | Audit logging |
| Backup.php | Backup management |
| BaseAPI.php | Base API class |
| ConversationMemory.php | AI conversation memory |
| DeepMigration.php | Migration utilities |
| DeviceDetector.php | Mobile device detection |
| Exceptions.php | Custom exceptions |
| FunctionExecutor.php | AI function execution |
| NotesAPI.php | Notes management |
| ProjectsAPI.php | Project management |
| RateLimiter.php | API rate limiting |
| SEOHelper.php | SEO utilities |
| TasksAPI.php | Task management |
| TodosAPI.php | Todo management |
| Validator.php | Input validation |

### Views Directory (67 files)
**Core Views (MUST HAVE):**
| File | Purpose |
|------|---------|
| 404.php | Error page |
| dashboard.php | Main dashboard |
| login.php | Login page |
| homepage.php | Public homepage |
| setup.php | Initial setup |

**Management Views:**
- tasks.php, task-form.php
- projects.php, project-form.php
- clients.php, client-form.php
- invoices.php, invoice-form.php, invoice-view.php
- inventory.php, product-form.php
- notes.php, note-form.php
- habits.php, habit-form.php
- finance.php, transaction-form.php

**Feature Views:**
- ai-assistant.php
- calendar.php
- kanban-board.php
- pomodoro.php
- water-tracker.php
- settings.php
- users.php
- release-export.php

**Additional Views:**
- advanced-invoices.php, advanced-invoice-form.php, advanced-invoice-view.php, advanced-invoice-view-modern.php
- audit-logs.php
- custom-instruction.php
- data-recovery.php
- docs.php
- forgot-password.php, reset-password.php
- habit-history.php, habits-all.php
- import-data.php
- inventory-history.php
- knowledge-base.php
- model-settings.php
- notes-list.php, notes-three-pane-sample.php
- privacy.php, terms.php
- scheduler-status.php
- shared-music.php
- thank-you.php
- verification-required.php, verify-email.php
- view-client.php, view-habit.php, view-notes.php, view-project.php, view-task.php
- water-plan.php, water-plan-details.php, water-plan-history.php

**Layout Files:**
- layouts/auth.php
- layouts/main.php
- layouts/three-pane.php

**Partial Files:**
- partials/header.php
- partials/sidebar.php

### Mobile Directory (59 files)
**Core Mobile Views:**
- All main feature views (tasks, projects, clients, invoices, etc.)
- app.php - Mobile app shell
- login.php, logout.php, register.php
- dashboard.php
- settings.php
- setup.php

**Mobile Layouts:**
- layouts/mobile.php

**Mobile Partials:**
- partials/bottom-nav.php
- partials/header-mobile.php
- partials/offcanvas-menu.php

## File Integrity Check Script

Use the following command to verify file integrity:

```bash
# Count files in each directory
find . -maxdepth 1 -name "*.php" -type f | wc -l
find api -name "*.php" -type f | wc -l
find includes -name "*.php" -type f | wc -l
find views -name "*.php" -type f | wc -l
find mobile -name "*.php" -type f | wc -l
```

## Expected File Counts
| Directory | Expected Count |
|-----------|----------------|
| Root | 7 |
| api/ | 36 |
| includes/ | 27 |
| views/ | 67 |
| mobile/ | 59 |
| **TOTAL** | **196** |

## Protection Protocol

### Before Any Git Push:
1. Run `php verify_manifest.php` to check file integrity
2. Review the output for any MISSING files
3. If files are missing, restore from backup before pushing

### After Restoration:
1. Run `git status` to see all changes
2. Review each file addition
3. Commit with message: "Restore missing critical files"
4. Push to GitHub

### Monthly Maintenance:
1. Update this manifest if new files are added
2. Run integrity check
3. Document any intentional deletions

## Emergency Contacts
- Primary Developer: [Your Name]
- Backup Location: `release-artifacts/openplan.work-hosted-clean-20260312-070308`
- Last Full Backup: 2026-03-12
