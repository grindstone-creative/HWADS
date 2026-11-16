# HWADS 0.15.9 / HWADS Box 0.5.4 validation

## Environment

Disposable local WordPress 6.8 / PHP 8.3 with SQLite integration, local Chromium browser checks, and Python runtime tests. No production installation, licensed Divi theme or physical HWADS Box was modified or used.

## Checks

- PHP syntax: 36 plugin files. JavaScript syntax: 14 runtime files.
- Existing plugin integration suite: native editor and immutable embeds, scoped Main Dashboard, device health, update policies, authenticated Box analytics, thumbnails, player-update authorization/integrity, approvals, visuals, landing layouts/options, simulated Divi starter, scheduled updates, playback mix, connection fixes, playlist limits, venue copying and content access.
- Consolidated navigation: dashboard and editor scope, canonical links, menu/highlight, native filter/search/sort context, removed workspace routes, copy action, read-only routes and denial of native edit capability. Stale assertions for the removed dashboard list selector were replaced with current overview/scoped-venue checks.
- New development integration: demo/content import roundtrip, parents and nested group/source references, main/overlay/custom-content media and shortcode remapping, repeat skip, failed-media rollback, invalid references, public privacy, immutable cached-publication web attribution/deduplication, expected-playback orientation/mix/fallback/override/disabled states, pending snapshot/purge acknowledgement and expiry, reporting retention and scoping.
- Venue browser checks: independent dashboard GET and native POST forms, right sidebar, in-place tab/filter/search switching, Back, unsaved name/email retention, uploader reinitialization, native venue save, responsive layout, authorized read-only navigation and separate copy action.
- Public browser checks: approved desktop/mobile layout, loaded published slideshow, private-field exclusion, Box-specific login return target and branding, no horizontal overflow, preview exclusion from metrics, and a real visible local embed submitting an accepted website event.
- Expected-playback JavaScript simulation: eligibility, house fallback, empty schedule and duration-weighted share convergence over 2000 selections.
- HWADS Box runtime: all 89 tests passed after version 0.5.4 and source-manifest refresh. This includes purge checkpoint/idempotence handling and existing sync/offline/update/bootstrap tests.
- Actual Box 0.5.4 ZIP accepted by the plugin package validator. Its 12 runtime hashes and byte-for-byte source parity were independently checked. Legacy update-flow fixtures test authorization/queue/signature behavior without deploying executable code.

## Release integrity and limits

The distribution ZIPs are checked for archive integrity, manifest hashes where applicable, source parity and matching version identifiers. SHA-256 and byte counts are recorded in `dist/release-checksums-0.15.9.json`.

No real device update, live publication or database import is performed during release building. Real Divi/custom-module and production/physical-Box smoke checks remain rollout work. Website figures are bounded anonymous browser reports, not unique viewers. Content migration is limited by bundle/hosting constraints and is not a whole-site backup. Exact playback frame synchronization and local offline fallback files are not public-preview features. See UPDATE-0.15.9.md for operational behavior and PENDING-WORK.md / ROADMAP.md for deferred work.
