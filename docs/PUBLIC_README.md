# OpenPlan Work

OpenPlan Work is the encrypted PHP workspace for teams and solo builders who want ownership of their data.

## What It Is

- A self-hosted PHP productivity suite for tasks, projects, notes, habits, invoices, inventory, and AI-assisted workflows.
- A local-first system that stores data in encrypted JSON, protected by your master key.
- A portable workspace designed to run on your own infrastructure.

## Core Modules

- Tasks and Projects: Track work, deadlines, and priorities across teams or personal use.
- Notes and Knowledge Base: Keep structured notes and searchable references in one place.
- Habits and Pomodoro: Build routines and stay focused with built-in timers.
- Finance: Quotes, invoices, and inventory management for lightweight operations.

## Security Model

- AES-256-GCM encryption for stored JSON data.
- A master password controls encryption and decryption.
- Sessions expire based on configurable timeouts.

## Local Run

Requirements:
- PHP 8.0+
- json, mbstring, openssl extensions

Run locally:
- `php start_server.php`
- `start_server.bat` on Windows

## Hosted Run

Hosted instances rely on environment configuration for auth and mail features.
Use `.env.example` as a starting point for deployment settings.

## Export and Releases

Release exports intentionally exclude live data, sessions, and secrets.
Generated ZIPs include a clean data structure for safe distribution.

## Branding and Theme

- Clean, high-contrast design language for clarity.
- Supports light and dark mode.
- Logos and icons are intentionally minimal.

## License

Add a LICENSE file before distributing as open source.
