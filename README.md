# PollMonitor

An election monitoring WordPress plugin for real-time incident reporting and result aggregation.

Version: 1.0.0

## Overview

PollMonitor provides a lightweight workflow to register polling stations, allow field observers to submit incident reports (with optional photo evidence), and collect vote results into a custom table for later validation by regional validators.

Key features:
- Custom Post Types: `poll_station`, `incident_report`
- REST API endpoints for stations, incidents, and results
- Role-based access: `pollmonitor_observer`, `pollmonitor_validator`
- Custom DB tables for results and audit logs
- Admin dashboard with interactive map and recent incidents

## Installation

1. Copy the `PollMonitor` folder into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin Plugins screen.
3. On activation the plugin will:
   - Register CPTs and taxonomies
   - Create custom DB tables (`wp_pollmonitor_results`, `wp_pollmonitor_audits`)
   - Add custom roles and capabilities

## Shortcodes

- `[pollmonitor_incident_form]` — renders the frontend incident submission form. The frontend script populates the station select via the REST API.

Place the shortcode on any page where logged-in observers should be able to report incidents.

## REST API

Namespace: `pollmonitor/v1`

- `GET /pollmonitor/v1/stations` — Returns a list of stations (requires `read` capability).
- `POST /pollmonitor/v1/incidents` — Create an incident (multipart/form-data). Requires `pollmonitor_submit` capability. Accepts `title`, `content`, `station_id`, and optional file field `evidence` (PNG/JPEG, max 2MB).
- `POST /pollmonitor/v1/results` — Submit polling station results (JSON). Requires `pollmonitor_submit`. Payload should include `station_id` and numeric party/total fields.

Responses use standard WP REST error objects for failures.

## Roles & Capabilities

- `pollmonitor_observer` — Intended for field observers. Capabilities: `read`, `pollmonitor_submit`, `upload_files`.
- `pollmonitor_validator` — Regional validators. Capabilities: `read`, `pollmonitor_submit`, `pollmonitor_validate`, plus editing/publishing caps to review and publish incidents.
- Administrators are granted `pollmonitor_submit`, `pollmonitor_validate`, and `pollmonitor_manage_all` on activation.

## Database

Creates two custom tables using the WP DB prefix:
- `{prefix}pollmonitor_results` — stores submitted results.
- `{prefix}pollmonitor_audits` — action audit log.

The plugin runs `dbDelta()` on activation to create or update tables.

## Admin Dashboard

Accessible under the Poll Stations menu. The dashboard shows total stations, recent incidents, and an interactive Leaflet map of stations. Markers are loaded from the `stations` REST endpoint.

## File Uploads & Security

- Evidence uploads are validated server-side: only PNG/JPEG, and max file size 2MB. Thumbnail generation is temporarily disabled during evidence uploads to conserve disk/inodes (original image is kept).
- Ensure your WordPress install enforces authentication and appropriate HTTPS.

## Uninstall / Cleanup

The plugin ships `uninstall.php` which will drop the custom tables, delete transients used by the plugin, and remove the custom roles and capabilities when the plugin is uninstalled via the WordPress admin.

## Development & Testing Notes

- The plugin includes minimal CSS in `assets/css/`. You may expand styles to match your theme.
- Recommended hardening before production:
  - Add rate limiting and abuse protection for the API endpoints.
  - Add additional input validation for results (non-negative numbers, station existence checks).
  - Add automated tests (PHPUnit) for REST endpoints and DB operations.
  - Run WP code sniffing (PHPCS) and security scans (e.g., WPScan).

## Contributing

Open issues and pull requests are welcome. Please follow WordPress coding standards.

## License

GPL-2.0+

## Author

- **AbdulBasit Ajibade (Basit of Africa)** — https://github.com/Basit-of-Africa

## Support

For development, security issues, or to report bugs, open an issue on the project's GitHub: https://github.com/Basit-of-Africa/PollMonitor