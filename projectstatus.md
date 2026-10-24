# OpenPlan Work — Project Status
**Date:** 2026-04-06  
**Session:** UI fixes, AI expansion, cron brief, multi-user verification

---

## What Was Completed Today

### 1. Gemini API Key Persistence Fix
- **File:** `views/settings.php`
- **Problem:** `geminiApiKey` and `ollamaUrl` were not being saved when the Settings → AI API Keys form was submitted (only Groq and OpenRouter were saved).
- **Fix:** Added the two missing lines to the `case 'api'` POST handler.
- **Status:** Live on server.

### 2. Gemini 500 Error Fix (Two Root Causes)
**Cause A — Missing exception methods:**
- `api/ai-agent.php` called `$e->getErrorCode()` and `$e->getContext()` on `APIException`, which didn't have those methods → PHP fatal error → HTTP 500.
- **Fix:** Added `getErrorCode()` and `getContext()` to `includes/Exceptions.php`.

**Cause B — SSL certificate verification failure:**
- `includes/GeminiAPI.php` made raw cURL requests without a CA bundle path. MAMP on Windows can't verify Google's SSL cert without an explicit cacert path.
- **Fix:** Added `resolveCaBundlePath()` private method (identical to what `GroqAPI` already had). Project root has `cacert.pem` which is auto-resolved.
- **Status:** Both fixes live on server.

### 3. Task Page — Filter Bar Layout
- **File:** `views/tasks.php`
- **Problem:** All filters were being pushed to the far right with a large empty center gap. The outer container used `justify-between` with an inner wrapper div.
- **Fix:** Removed inner wrapper div, flattened all filters into a single `flex flex-wrap items-center gap-3` row, added `ml-auto` to the stats group so it right-aligns naturally. Search input changed from `w-64` to `w-48 min-w-[140px]`.
- **Status:** Live on server.

### 4. Task Page — Card Column Redistribution
- **File:** `views/tasks.php`
- **Problem:** The right side of task cards was getting cropped. A single `col-span-3` div held deadline badge + timer + estimated time + actual time with `flex-wrap`, causing multi-line overflow and uneven row heights.
- **Fix:** Split the old `col-span-3` into:
  - `col-span-2`: Deadline badge only + timer display (no flex-wrap, single badge always fits)
  - `col-span-1`: Time info stacked vertically (estimated + actual minutes)
  - Updated header row labels to match.
- **Status:** Live on server.

### 5. AI Tool Expansion (7 New Tools)
- **Files:** `includes/AIFunctions.php`, `includes/FunctionExecutor.php`, `includes/AIAgent.php`

| Tool | What It Does |
|------|-------------|
| `list_transactions` | Finance entries, filter by type/category |
| `list_invoices` | Invoices, filter by status/client |
| `log_water` | Add glasses or set total for today |
| `get_water_status` | Today's intake vs goal + 7-day history |
| `update_subtask` | Mark subtask done, rename, update estimate |
| `create_kb_note` | Create a text file in KB "Notes" folder |
| `list_notes` | Notes list with optional tag/search filter |

- Read-only tools registered in `isRetryableAction()` for safe retry.
- **Status:** Live on server.

### 6. AI Tool Status Noise Removed
- **File:** `includes/AIAgent.php`
- **Problem:** Every AI response ended with "Tool status: list_projects: SUCCESS in 0ms." — technical noise the user doesn't need.
- **Fix:** Removed all `buildToolStatusSummary()` calls from `finalizeAssistantMessage()`. All 4 return paths now return clean text.
- **Status:** Live on server.

### 7. Mermaid Diagram Rendering in AI Chat
- **Files:** `views/layouts/main.php`, `views/ai-assistant.php`, `mobile/views/ai-assistant.php`
- **Problem:** AI could generate Mermaid code blocks but they rendered as raw text, not visual diagrams.
- **Fix:**
  - Loaded `mermaid@11` CDN in main layout and mobile AI page.
  - Updated `renderMarkdown()` custom `marked.js` Renderer to intercept ` ```mermaid ` blocks and output `<div class="mermaid">` containers instead of `<pre><code>`.
  - DOMPurify config updated to allow `div` tags and `class`/`id` attributes so mermaid containers survive sanitization.
  - Added `renderMermaidInEl()` function called after each message is appended to the DOM.
- **Status:** Live on server, both desktop and mobile.

### 8. AI Daily Brief Cron Job
- **Files:** `config.php`, `includes/AIHelper.php`, `api/cron.php`, `views/dashboard.php`

**How it works:**
1. Cron triggers `?job=ai_daily_brief` (or included in `run_daily`).
2. Loads all non-banned users from `data/users.json`.
3. For each user, creates a `createScoped($userId)` DB instance so API keys come from **that user's own** `data/users/{id}/config.json.enc`.
4. `AIHelper::generateDailyBrief()` loads overdue tasks, today's tasks, pending habits, invoice count, low-stock count.
5. Calls `callAIWithFallback()` which tries providers in order: `groq → openrouter → gemini → ollama`, skipping any without a configured API key.
6. Saves the brief to the user's own scoped directory: `data/users/{id}/ai_daily_brief.json.enc`.
7. Dashboard reads from the same scoped path and shows an indigo dismissible widget at the top.
8. Dismiss is stored in `localStorage` with key `ai_brief_dismissed_{userId}_{date}` — day-scoped, so it reappears next day.

**Constants added to `config.php`:**
```php
define('AI_CRON_ENABLED', filter_var(getenv('AI_CRON_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('AI_CRON_PROVIDER_ORDER', ['groq', 'openrouter', 'gemini', 'ollama']);
```

**Status:** Live on server. Multi-user scoping confirmed correct.

---

## Multi-User Architecture — Confirmed Intact

Every API endpoint and view uses:
```php
$db = new Database(getMasterPassword(), Auth::userId());
```

This scopes all data reads/writes to `data/users/{userId}/`. No cross-user data leakage is possible. The cron job was the only exception (it runs without a session) — now fixed to use `createScoped()` per user.

---

## Mobile Parity — Confirmed

`mobile/views/ai-assistant.php` has full parity with the desktop version:

| Feature | Mobile | Desktop |
|---------|--------|---------|
| Groq provider | ✓ | ✓ |
| OpenRouter provider | ✓ | ✓ |
| Gemini provider | ✓ | ✓ |
| Ollama provider | ✓ | ✓ |
| Provider fallback chain | ✓ | ✓ |
| marked.js v9 | ✓ | ✓ |
| DOMPurify v3 | ✓ | ✓ |
| Mermaid.js v11 | ✓ | ✓ |
| Copy buttons on code | ✓ | ✓ |
| Markdown rendering | ✓ | ✓ |
| Agent mode (tool use) | ✓ | ✓ |

---

## Files Modified This Session

| File | Change |
|------|--------|
| `views/settings.php` | Save Gemini key + Ollama URL in POST handler |
| `includes/Exceptions.php` | Add `getErrorCode()` + `getContext()` methods |
| `includes/GeminiAPI.php` | Add SSL CA bundle resolver (same as GroqAPI) |
| `views/tasks.php` | Filter bar flatten + card column split |
| `includes/AIFunctions.php` | 7 new tool definitions |
| `includes/FunctionExecutor.php` | 7 new tool handlers |
| `includes/AIAgent.php` | Register read-only tools; remove tool status noise |
| `config.php` | Add `AI_CRON_ENABLED` + `AI_CRON_PROVIDER_ORDER` constants |
| `includes/AIHelper.php` | Add `callAIWithFallback()` + `generateDailyBrief()` |
| `api/cron.php` | Add `ai_daily_brief` job with per-user scoping |
| `views/dashboard.php` | Add dismissible AI brief widget |
| `views/layouts/main.php` | Load mermaid.js for all desktop pages |
| `views/ai-assistant.php` | Mermaid rendering + tool status noise removal |
| `mobile/views/ai-assistant.php` | Mermaid rendering; full 4-provider support |
| `api/ai.php` | Provider lockout fix (from prior session) |
| `api/ai-generate.php` | Provider lockout fix (from prior session) |
| `views/notes.php` | Notes tag manager recovery fix |
| `views/inventory.php` | Inventory view update |
| `views/habit-form.php` | Habit form update |
| `includes/OllamaAPI.php` | Ollama provider support |

All 20 files uploaded to live server at the live FTP server (see CLAUDE.local.md) and verified by timestamp.

---

## Known Deferred Items

| Item | Reason Deferred |
|------|----------------|
| Calendar AI tools | No `calendar.json.enc` data model found — calendar may use external data or isn't yet a stored collection. Investigate before adding tools. |
| Pomodoro AI tools | Not requested; lower priority. |
| AI brief test on live server | Trigger manually: `GET /api/cron.php?job=ai_daily_brief` — needs at least one user to have an AI provider configured. |

---

## Quick Test Checklist for Next Session

- [ ] Settings → AI API Keys: Save Gemini key, refresh → key still visible
- [ ] AI Assistant (desktop): Ask AI to draw a mermaid flowchart of your projects → should render visually
- [ ] AI Assistant (desktop): Switch to Gemini provider → should not 500
- [ ] AI Assistant: "list my transactions" / "log 2 glasses of water" / "list my invoices" → should work
- [ ] Tasks page: Filter bar spreads naturally, no large center gap
- [ ] Tasks page: Deadline badge has its own column, doesn't wrap with time info
- [ ] Cron brief: Trigger `GET /api/cron.php?job=ai_daily_brief` → reload dashboard → indigo widget appears
- [ ] Mobile AI: All 4 providers visible in provider selector

---

*Generated: 2026-04-06 — Session closed.*
