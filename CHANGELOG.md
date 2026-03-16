# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Public MCP Configuration**: All users (including non-admins) can now access the MCP configuration page via Settings > Developer Tools > MCP Config.
- **Dynamic MCP Config**: The MCP configuration JSON is now dynamically generated with the current user's email and correct system paths, making setup easier.

### Changed
- **Settings Page (PC)**: "Developer Tools" section is now visible to all users.
- **Settings Page (Mobile)**: "Developer Tools" section is now visible to all users.
- **Security Hardening**: Specific developer tools (Audit Logs, Scheduler Status, Speckitty, Custom Instructions, Data Recovery, Users, Shared Music, Release Export) are now explicitly restricted to Admin users only in the UI.

### Fixed
- Fixed an issue where non-admin users could not access the MCP configuration needed for their AI agents.
