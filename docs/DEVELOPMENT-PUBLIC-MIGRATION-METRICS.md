# Public pages, content migration and engagement — development handoff

This historical handoff was integrated into HWADS 0.15.9 / HWADS Box 0.5.4 by HWADS Generate. See UPDATE-0.15.9.md for current installation notes.

No release was built, published, deployed, tagged, or versioned by this development chat. Plugin remains 0.15.8 and Box source remains 0.5.3; the release owner must assign the next versions. The Box source integrity manifest hash was refreshed to match its changed source, without packaging.

## Public HWADS Box page

Existing printed settings/QR links now show a public page to signed-out visitors. Signing in still returns to that specific Box's permission-checked settings. The default is theme-independent (no front-end Divi hooks from admin-post), with site logo/name, slim header, login button, venue/location heading and responsive configured-playback slideshow. Login branding uses the WordPress custom logo, Divi logo option when present, site icon, or site name.

Settings offers one optional published WordPress/Divi page override. Put `[hwads_device]` on that shared page. The selected Box identifier is carried from the original link; no manual playlist choice or per-Box page setup is required. The shortcode resolves published settings automatically. Password-protected, private, draft and uneditable pages cannot be selected.

Expected playback uses effective Box/location primary and house assignments, device override vs inherited mix precedence, orientation, schedules (inclusive start/exclusive end), house-in-rotation, weighted groups and configured published fallback. The public serializer exposes only published media/overlays/links and the configuration needed for selection. Device keys, telemetry grants, health, remote access and private drafts are excluded. Disabled or unavailable Boxes have no public playback payload.

The preview has independent shuffled bags and a time-deficit mixer; it does not mirror the exact live slide, persistent device mixer state, download failures, offline cache or locally stored fallback files. Videos are muted. Changes are polled every five minutes; schedules are rechecked between slides, with an empty schedule retried every 30 seconds. Public device previews do not contribute Box or website metrics. Normal published playlist/placement embeds do contribute website impressions.

## Content Import / Export

Global administrators can export a versioned JSON content bundle and preview an uploaded bundle before importing. Supported records: venues, advertisers, locations, ad templates, placements, playlists, location playback groups/fallbacks and their logical relationships. The bundle contains embedded media bytes and SHA-256 checksums, including image overlays and recognizable WordPress media URLs in custom content. Media URLs and known HWADS shortcode IDs are rewritten. Standard Divi content and the existing builder flags are preserved.

Import modes are create/skip previously imported IDs from the same origin, or duplicate an independent graph. Existing records are never overwritten. New IDs are allocated before relationships are resolved. Failed imports roll back the records and media they created. Media is sideloaded through WordPress validation, not fetched from arbitrary source URLs. No devices, device secrets, users, user sharing grants, approvals, billing, analytics, immutable publications or site/theme settings are imported. Personal templates belong to the importing administrator. Imported creatives/playlists remain drafts and require review/publication; imported assignments do not make drafts playable.

Limits: 2000 records, 64 MB JSON and the host's effective upload/file limits; supported media JPEG/PNG/WebP/MP4/WebM. Legacy unconverted creative records are refused rather than silently reinterpreted. Unrecognized theme/font/remote/custom asset URLs remain references and need separate migration. There is no in-place merge/update mode or whole-site backup. A blocked import lock after an interrupted PHP process requires administrator inspection rather than unsafe automatic deletion.

Demo seed: `plugin/hpads/docs/demo/content.json`. It creates a neutral demo venue/location, template, placement, playlist and a weighted group/fallback relationship with simple placeholder media. It is never imported automatically and includes no real client content.

## Purge pending queue

Purge is a manual fallback for stuck *pending uploads*, not deletion of reported history. A fresh snapshot (no older than 15 minutes) and device write permission are required; impersonation mode cannot purge. The UI shows exactly how many records in its snapshot will be discarded and requires confirmation. Each batch is limited to the 50 displayed snapshot records. Refresh and repeat for larger queues.

The authenticated Box response delivers an expiring one-hour command containing exact play IDs/checkpoint sequences. The Box deletes only those unchanged queued checkpoints; newer checkpoints survive. An idempotent receipt means a lost response/retry cannot delete an item twice. Playback continues, and active plays may queue a new checkpoint. The acknowledged deleted count is returned to the dashboard and the stale snapshot is cleared. Unreported metrics can be lost by intentional purge. Reported history and rejected diagnostic records are not deleted.

This feature needs BOTH the plugin changes and the corresponding Box runtime update. Older runtimes ignore the command and it expires. Existing successful uploads already clear matching queued checkpoints automatically; changing checkpoints can briefly remain after an older acknowledgment.

## Website and screen metrics

The report labels authenticated starts as HWADS Box plays and retains completed plays, completion rate, screen-minutes, last received and queue health. A separate source table shows local website, remote website and unknown-source embed impressions, including when no Box observations exist.

A website impression requires successfully loaded media, at least 50% frame intersection for one second and visible document state. Repeated rotations count again. Source is the embedding document's referrer hostname; unavailable/blocked referrers are unknown. Browser reports are anonymous software observations, not verified human views, unique visitors or audience size. Previews and failed media do not count. Landing-page visits, outbound clicks and QR scans are still uncollected.

Public embed grants expire after two hours and are bound to the published version. The endpoint validates placement revision against that immutable version, including cached earlier publications; UUID event IDs deduplicate retries. No Box telemetry grant is exposed. Bounded ingestion: 2 KB per event, 120 requests/client/minute, 2000/global/minute, and 25000/global/UTC-day. Client quota keys use a salted hash of the direct network address, without trusting proxy headers or storing raw addresses. Website records retain 90 days, with batched cleanup; unlike Box daily history, older website totals are not retained. Counts beyond rate limits are dropped, not estimated.

## Verification

- Full Box suite: 89 tests passed, including two new purge protocol/concurrency tests.
- PHP syntax: all 36 plugin PHP files passed with PHP 8.3.
- WordPress 6.8/PHP 8.3 disposable integration: demo seed and full content roundtrip; parents, source templates, nested group references, media and overlay remapping; repeat skip; failed-media rollback; invalid relationships; public source permissions; immutable website attribution/deduplication; Box orientation, inherited mix, fallback, overrides and disabled state; queue request/ack/expiry; existing pending and analytics suites.
- Public browser checks: loaded published slideshow, desktop/mobile layouts, no private-field leakage or horizontal overflow, retained Box login return target, login branding, preview metric exclusion and a visible local embed posting a real website event. Screenshots: `docs/screenshots/public-box-desktop.png` and `public-box-mobile.png`.
- Selection simulation: schedules, house fallback and weighted playback-time convergence across 2000 selections.
- Existing embed regression checks and updated Main Dashboard checks are included in the combined validation owned by HWADS Generate.

Validation was local and disposable. No production WordPress/Divi installation or physical HWADS Box was modified or used for these tests.

## Source touched by this chat

Plugin: `hpads.php`; `includes/admin.php`, `admin-access.php`, `device-qr.php`, `analytics.php`, `embeds.php`; new `includes/public-device.php`, `migration.php`, `web-metrics.php`; `assets/embed.js`; new `assets/device-selection.js`; new `docs/demo/content.json`.

Box: `player/hwads_sync.py`, source integrity hash in `player/update-manifest.json`; new `player/tests/test_queue_purge.py`.

Tests/docs: new `tests/development-scope.php`, `tests/queue-purge.php`, `tests/device-selection.mjs`, this document and two public-page screenshots. Browser and disposable WordPress runner scripts live in `/private/tmp/hwads-validation/` and were not packaged.
