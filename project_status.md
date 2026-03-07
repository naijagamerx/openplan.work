# Project Status Audit

Date: 2026-03-07  
Project: OpenPlan (`C:\MAMP\htdocs\taskmanager`)  
Scope: Full app consistency audit + MCP channel validation + smoke/runtime checks

## Executive Summary

Overall score: **84 / 100**  

Current state: **Startup-ready and sale-ready for managed rollout**, with stronger test/runtime consistency than previous audit.  
Still not enterprise-grade yet due hardening/ops gaps.

## What Was Fixed

1. **CLI OpenSSL test/runtime inconsistency**
   - Added runtime-aware PHPUnit launcher: [scripts/run_phpunit.php](C:/MAMP/htdocs/taskmanager/scripts/run_phpunit.php)
   - Updated composer scripts to use it: [composer.json](C:/MAMP/htdocs/taskmanager/composer.json)
   - Result: PHPUnit now runs with a PHP binary that has OpenSSL.

2. **Stale smoke assertions**
   - Updated legacy habits timer checks to current `HabitTimerManager` patterns in [test/smoke_test_comprehensive.php](C:/MAMP/htdocs/taskmanager/test/smoke_test_comprehensive.php)
   - Result: smoke test now passes cleanly.

3. **Release export cleanup residue risk**
   - Added retry-based file/dir cleanup in [views/release-export.php](C:/MAMP/htdocs/taskmanager/views/release-export.php)
   - Result: better resilience against transient file locks during staging cleanup.

4. **Leftover “Monochrome” branding**
   - Replaced remaining references in:
     - [docs/PUBLIC_README.md](C:/MAMP/htdocs/taskmanager/docs/PUBLIC_README.md)
     - [views/docs.php](C:/MAMP/htdocs/taskmanager/views/docs.php)
     - [mobile/views/login.php](C:/MAMP/htdocs/taskmanager/mobile/views/login.php)
     - [mobile/views/clients.php](C:/MAMP/htdocs/taskmanager/mobile/views/clients.php)
     - [mobile/views/invoices.php](C:/MAMP/htdocs/taskmanager/mobile/views/invoices.php)

## MCP Setup + Validation

Configured MCP user context in `.mcp.json` for admin-scoped operations:
- `USER_EMAIL` set to your admin email.
- `MASTER_PASSWORD` and `API_URL` configured for local MCP execution.

Validation script added: [test/mcp_channel_smoke.mjs](C:/MAMP/htdocs/taskmanager/test/mcp_channel_smoke.mjs)

MCP channel test result: **PASS** (81 tools discovered, all tested channels green)
- System: `test_connection`, `get_system_status`
- Todos, Projects, Tasks, Clients, Invoices, Finance, Inventory, Water, Habits, Notes, Knowledge Base, Advanced Invoices, Search

Created through MCP (not direct API):
- `clientId`: `d81714d3-2d69-4ce2-8f5d-2a07d5c18bb6`
- `projectId`: `c34449ca-78b9-428a-8741-aa171b2dc3b6`
- `taskId`: `2ac8bd87-99f2-4977-9ae2-42f62b3bae07`
- `invoiceId`: `ea95e954-2933-459c-8c2f-84ae5e317f33`

Also fixed MCP invoice-channel blocker by hardening invoice API collection handling:
- [api/invoices.php](C:/MAMP/htdocs/taskmanager/api/invoices.php)

## Verification Evidence

- PHP lint: `190 files`, `0 syntax errors` (app runtime scope)
- `php test/smoke_test_comprehensive.php`: **28 pass, 0 fail**
- `php scripts/run_phpunit.php test/SecurityRegressionTest.php --testdox`: pass (with coverage-driver warning)
- `php scripts/run_phpunit.php test/DatabaseCoreTest.php --testdox`: pass (with coverage-driver warning)
- MCP full-channel smoke: pass

## Remaining Risks

1. **Coverage driver warning in PHPUnit**
   - Not a functional blocker, but still noisy in QA runs.

2. **Enterprise hardening backlog**
   - Session fingerprint TODO remains in [includes/Auth.php](C:/MAMP/htdocs/taskmanager/includes/Auth.php)
   - Compliance/audit depth, rate-limit governance, and formal SLO controls still needed for enterprise label.

## Readiness Verdict

- Ready for sale? **Yes** (managed rollout / startup customers)
- Production standard? **Yes for SMB/startup self-hosted deployment**
- Enterprise standard? **Not yet**
- Startup product? **Yes**
- Open source collaboration ready? **Yes**

## Current Classification

**OpenPlan is in a strong startup-production state with MCP operational and core channels verified.**
