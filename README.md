# SAM Attendance Management

A PHP-based attendance management system for recording student attendance, managing administrator access, and supporting attendance workflows through a browser-based interface.

## Project structure

- `admin-login.php` / `admin_register.php` — administrator authentication flows
- `attendance_in.php` / `attendance_out.php` — attendance entry workflows
- `attendance_rules.php` — attendance-related rules and calculations
- `app_ui.php` — application UI layer
- `assets/` — frontend assets
- `apache/` — Apache configuration assets
- `Dockerfile` — container build configuration
- `PORTABLE_SETUP.md` — portable setup notes

## Local setup

The repository includes both Docker and local setup support. For the portable workflow, follow `PORTABLE_SETUP.md` and use the provided launcher/configuration files.

Before running the application, configure the database and server environment expected by the PHP files. Do not commit passwords, API keys, database credentials, or production configuration.

## Security notes

- Keep runtime credentials outside the repository.
- Use HTTPS for deployed instances.
- Use least-privilege database accounts.
- Review authentication and input validation before exposing the application publicly.

## Status

This repository is maintained as an application codebase and deployment prototype. Verify environment-specific configuration before production use.
