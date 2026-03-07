# PollMonitor — Setup & Deployment Guide

This document captures everything needed to install, configure, test, and deploy the PollMonitor WordPress plugin safely.

## Prerequisites

- WordPress 5.9+ (recommended)
- PHP 7.4+ (PHP 8.0+ recommended)
- MySQL 5.7+ / MariaDB 10.3+
- WP-CLI (optional, recommended for automated installs)
- HTTPS enabled in production

## Local Development

1. Clone or copy the `PollMonitor` folder into your local WordPress site's `wp-content/plugins/` directory.
2. Ensure your local WP environment meets prerequisites and that `WP_DEBUG` is enabled for development.
3. Activate the plugin in the admin Plugins screen or with WP-CLI:

```bash
wp plugin activate PollMonitor
```

## Activation steps performed by the plugin

- Registers Custom Post Types: `poll_station`, `incident_report`.
- Registers taxonomies: `state`, `lga`, `ward`, `incident_type`, `severity`.
- Creates custom DB tables: `{prefix}pollmonitor_results`, `{prefix}pollmonitor_audits` using `dbDelta()`.
- Adds roles: `pollmonitor_observer`, `pollmonitor_validator` and grants admin capabilities.

Verify the activation completed without errors by checking:

- Admin -> Plugins (plugin active)
- Database for new tables (`SHOW TABLES LIKE '%pollmonitor_%';`)
- Roles: confirm `pollmonitor_observer` and `pollmonitor_validator` exist using `get_role()` or a role manager plugin.

## Configuration

- Shortcode: Add `[pollmonitor_incident_form]` to any page to render the incident form.
- Admin dashboard: accessible under the Poll Stations menu; ensure Leaflet assets load for the map.
- If deploying behind authentication/proxies, ensure REST `X-WP-Nonce` is passed correctly to frontend/admin scripts.

## Security & Hardening Checklist (must before production)

1. Enforce HTTPS for all traffic and API requests.
2. Ensure REST endpoints are only accessible by intended roles:
   - `GET /pollmonitor/v1/stations` requires `read`
   - `POST /pollmonitor/v1/incidents` requires `pollmonitor_submit`
   - `POST /pollmonitor/v1/results` requires `pollmonitor_submit`
3. Rate limit submission endpoints or add CAPTCHA to the frontend form to prevent abuse.
4. Verify file upload restrictions: evidence files accepted only PNG/JPEG and <= 2MB (server-side validation applied).
5. Use WP nonces and capability checks for admin actions (validators/approval flows already use capability checks and nonces).
6. Run automated security scans (WPScan) and address issues found.

## Data Validation Recommendations

- For `create_result()` endpoint, validate:
  - `station_id` exists and is a valid `poll_station` post ID.
  - vote counts are non-negative integers.
  - totals are consistent (optional: total_valid equals sum of party votes).
- Sanitize and escape output in all templates; ensure REST responses do not leak sensitive data.

## Testing

1. Unit/Integration: add PHPUnit tests for:
   - REST endpoints (permission checks and payload validation)
   - Database helpers (`PollMonitor_DB::log_action`)
2. Manual tests:
   - Create a `poll_station` post and confirm it appears on the admin map.
   - Log in as a user with `pollmonitor_observer` role and submit an incident with/without evidence.
   - Log in as a validator and approve/reject an incident.
   - Submit `results` via the REST API and confirm rows in `{prefix}pollmonitor_results`.

## CI / Linting / Formatting

- Use PHP CodeSniffer with WordPress ruleset (PHPCS) for coding standards.
- Add a basic Github Actions matrix to run `phpunit` and `phpcs` on PRs.

## Logging & Monitoring

- The plugin writes audit rows to `{prefix}pollmonitor_audits`. Monitor this table for suspicious activity.
- Consider forwarding critical audit events to external logging/alerting systems.

## Backup & Migration

- Back up DB before activating or upgrading the plugin in production.
- For schema upgrades, use a versioned migration approach rather than relying solely on `dbDelta()`; keep a `db_version` option to track applied migrations.

## Uninstall & Cleanup

- The plugin ships `uninstall.php` which drops plugin tables, removes transients, and removes custom roles/caps when uninstalled via the WP admin.
- If you need to preserve data, do not use the admin uninstall; remove the plugin manually after backing up the DB.

## Deployment Checklist

1. Run tests and lint locally or in CI.
2. Run security scans and fix findings.
3. Ensure HTTPS and server hardening are in place.
4. Disable debug logging (`WP_DEBUG = false`) in production.
5. Deploy plugin via your normal release process (SFTP, WP-CLI, or CI/CD).

## Notes for Operators

- The plugin intentionally disables intermediate image sizes for evidence uploads to conserve disk space; review this behavior if you need thumbnails for archived evidence.
- `assets/css/` contains minimal styles; extend them to match your theme.

## Contact / Support

For development or security issues, open an issue in the project's repository or contact the maintainer listed in the plugin header.

## Maintainer

- **AbdulBasit Ajibade (Basit of Africa)** — https://github.com/Basit-of-Africa

