# HWADS 0.14.1 / Pi 0.5.1 — connection fixes

These are local install candidates, not deployed changes. Install ZIPs: `dist/hwads-0.14.1.zip` for WordPress and `dist/hwads-pi-0.5.1.zip` for the Pi/update uploader. Existing releases remain separate. The current 0.14.0 weighted playback-mix work is preserved.

## What changed

- Placement Active, duration and start/end schedules have one owner: the placement. Playlist drafts store ordered placement IDs. On an authorized global administrator's next admin visit, an idempotent upgrade removes duplicated draft fields; already published snapshots are unchanged. Legacy non-placement entries retain their compatibility fields.
- Both playlist and placement editors edit the same Active value. No extra playlist-enabled gate remains. A playlist must still be published/updated to change the TV snapshot.
- Inactive, scheduled and expired placements stay in the editor slideshow and thumbnail strip. A grey treatment, diagonal slash and text badge show why they would not run now. They remain selectable/editable; the editor is not a filtered current-TV preview. Status uses the WordPress timezone and refreshes every 15 seconds.
- Blank image duration inherits the destination location. Individual editors and review previews use that location default; the visible override input stays blank with an inherited-value placeholder. Templates defer to their eventual destination. Image duration is disabled for video.
- Legacy Primary/House playback checks file availability before choosing fallback. Combined rotation plays Primary in its saved order followed by House in its saved order. The newer weighted mix keeps its explicit share/shuffle policy.
- mpv reports its playing filename, protecting it during media cleanup even if subsequent syncs replace both cached playlist manifests. Browser playback already reports its visible files. Cleanup still requires an explicit purge request and only removes unused media older than 24 hours.
- Heartbeats from an old/offline assignment are stored with an assignment-mismatch flag rather than rejected. Status distinguishes local playback disable and fresh player errors from idle. Device health shows the mismatch diagnostic.
- Device Enabled is labeled **Allow device API access**, with an explanation that cached playback continues when access is revoked. This does not add a remote stop command.
- Divi's edit-layout link is placed in the visible landing-layout section.

## Removed Pi wire fields

The Pi playlist response no longer includes location software policy, device settings URL, device/custom type labels, outer location/venue IDs, or orientation-filter annotations. Those features keep their WordPress-side owners; scheduling remains server-side.

Selected manifests are projected to the Pi's actual contract: schema/playlist/version/analytics attribution and the media, schedules, duration, geometry, QR, overlay and transition fields the player consumes. Landing-page HTML, titles, attachment IDs, publication metadata, thumbnail timestamps, placement revisions and other unused stored-publication fields are omitted. Image overlay attachment IDs are omitted too. Stored publications and public website/embed payloads are not rewritten.

Unused analytics server-time and heartbeat server-time/cadence/jitter/URL hints and updater next-poll hints are removed. Pi cadence continues to use the consumed playlist heartbeat_seconds and its existing updater polling rules. Removing unused fields does not require deleting device labels or WordPress update policies.

## Active and enable controls

| Control | Canonical location | What it controls |
|---|---|---|
| Placement Active | Placement settings, available from placement editor or selected playlist card | Whether the placement is included on the next publication; one saved value |
| Venue Active | Venue editor | Administrative venue status, not TV playback |
| Allow device API access | Device editor | Authentication for sync, heartbeat, analytics and updates; not cached playback |
| Playback enabled/disabled | Pi local playback control | Starts/stops browser or mpv locally; separate from API credentials |
| Weighted shuffle enabled | Location Playback mix | Weighted playlist selection, inherited unless a device explicitly uses legacy assignments |
| Include house in normal rotation | Location, legacy playback | Append House to Primary rather than fallback-only |
| Allow house management | Location | Venue permissions to author/manage house media; not a runtime stop |
| Scheduled software updates enabled | Shared location update schedule | Allows SuperAdmin to queue a chosen release for the saved time; no recurring automatic release selection |
| Outside-ad approval required | Venue approval policy | Review before publication |
| Legacy approval required | Location | Compatibility gate for unmigrated media only |
| Show QR | Placement/template visual settings | QR overlay (template setting copies into future placements) |
| Show ad / show title | Placement landing-page settings | Landing-page shortcode rendering only |
| Use text background | Selected text overlay | Appearance only |
| Analytics enabled | Pi device configuration | Local screen-observation recording/upload, not playback |

Rotate device key is a one-time action, not another active switch. WordPress Draft/Publish controls frozen publication, not an additional placement Active value. Template copies remain independent. Distinct controls above were not merged into a misleading universal switch.

## Player engines and cleanup

Chromium is the default in `hwads_launch.py`. mpv is an explicitly selectable alternative, not automatic crash fallback. The physical devices' selected engine is not accessible from this workspace; their next heartbeat's Display engine identifies it. Browser supports QR/overlays/transitions; mpv plays the base media without those visual features. Both remain supported.

Purge means deleting old cached media no longer referenced by current/previous playlists or current playback. It does not delete WordPress uploads, device configuration, placeholders, analytics data or active media.

## Validation

- 71 Python tests passed, including new fallback/order, error/disable and playing-file purge regressions; existing weighted mixing/update/analytics tests retained.
- PHP syntax passed for all 27 plugin PHP files; JavaScript syntax passed for all 11 first-party runtime scripts.
- 293 fresh disposable WordPress checks passed for native saves, embeds, dashboard, health, update policies, analytics, thumbnails, remote updates, approvals, visuals, landing layouts/options, simulated Divi, scheduled updates, playback mixing and connection contracts. The title-propagation PHP check requires its browser naming precondition and is not part of the standalone fixture run.
- Actual Chrome checks passed for inactive/scheduled/expired editor marks, mobile width, visibility of excluded placements, and Active save/reload shared across playlist/placement editors, with no page errors.
- Existing dashboard test now creates an eligible test actor instead of assuming user ID 2. Analytics test reflects current menu-scope authorization; permissions were not loosened.
- Physical Pi hardware, production site and actual Divi installation remain untested. RAM/CPU/temperature collection and heartbeat history are not added by this fix.

Older `source/`, `analytics/`, and `integrated/` directories contain different historical/unmerged work and have not been deleted or overwritten. `updater/` is the candidate used here; do not assume the other copies contain these fixes.
