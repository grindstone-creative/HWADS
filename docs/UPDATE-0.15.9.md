# HWADS 0.15.9 / HWADS Box 0.5.4

Combined release, 2026-11-16. Built locally; not installed or deployed to the live site or any Box.

## Packages and installation

- `dist/hwads-0.15.9.zip`: install/update the WordPress plugin through its normal Upload Plugin / replace flow. Existing content and assignments are retained.
- `dist/hwads-pi-0.5.4.zip`: matching HWADS Box runtime package. For updater-ready Boxes, upload it through Devices → Player Updates and explicitly choose the intended Boxes and installation timing. Uploading does not deploy it. Updating briefly restarts playback software.

The public page, editor layout, migration tools and website metrics are plugin features. Pending queue purge requires both packages; older Box runtimes ignore the new purge command. No new runtime dependencies or updater protocol changes are introduced.

## Included since 0.15.8

- Venue Details and the editable venue name now sit below Save in the native right sidebar. Dashboard lists occupy the main column. Tabs, location filters, search, status/sort/pagination links and browser Back update the section in place while retaining unsaved venue fields. Individual records still open their native editors.
- Venue/location links now use one consolidated dashboard with the selected tab and location; the duplicate workspace route/header and redundant standalone Playlists menu for venue-access roles are removed. Authorized viewing users retain read-only access.
- Existing Box settings/QR links show signed-out visitors the approved public page: site branding, login, venue/location heading and responsive expected-playback slideshow. It follows the Box's effective published assignments, orientation, schedules, playback mix, house mode and configured fallback. Credentials, private drafts, diagnostics and authenticated Box telemetry grants are excluded.
- One shared default layout works automatically. Settings can select a published WordPress/Divi page override containing `[hwads_device]`; no per-Box page or manual playlist selection is required. Login branding uses the configured logo/icon or site name.
- Global administrators receive content Import / Export with import preview, embedded media/checksums, relationship and overlay remapping, recognized custom-content media/shortcode rewriting, repeat-import skip or independent duplicate mode, and rollback on handled failure. Imported creatives/playlists remain drafts. Existing records are not overwritten.
- Pending queue recovery now supports acknowledged purge of exact checkpoints from a fresh snapshot, including active-play checkpoints. Each confirmed batch covers up to 50 displayed records. Newer checkpoints and reported history survive; active playback continues and can queue a fresh checkpoint. Purge intentionally discards unreported metrics. Commands expire after one hour.
- Engagement reports distinguish authenticated HWADS Box plays from local, remote and unknown-source website embed impressions. Web events retain immutable publication/revision attribution and deduplicate retries, with bounded ingestion and 90-day retention.

## Limits that matter

- Public expected playback is independent of the physical Box's exact slide, persisted shuffle state, download failures, offline cache and locally stored fallback files. Videos are muted. Server settings refresh at five-minute intervals; schedules are checked between slides.
- Configured-playback previews submit neither Box plays nor website impressions. Regular published playlist/placement website embeds count visible, successfully rendered impressions. They are software reports, not unique people or verified audience counts. QR scans, outbound clicks and landing-page visits are still uncollected.
- Migration is a content bundle, not a whole-site backup. It excludes devices, credentials, users, sharing grants, approval history, publications, analytics, billing and theme/site settings. Maximum 2000 records / 64 MB JSON, further limited by hosting upload/file limits. Unknown theme/font/remote/custom asset references need separate migration. Interrupted PHP imports can require administrator inspection of an abandoned import lock.
- The optional neutral demo bundle is `hpads/docs/demo/content.json` inside the plugin. It is never loaded automatically.
- Commercial Divi and physical Box behavior were not exercised on the production installation; local integration/browser/runtime checks do not replace that rollout smoke check.

See `PENDING-WORK.md` for deferred work and `VALIDATION-0.15.9.md` for release checks.
