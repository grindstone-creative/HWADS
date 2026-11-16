# HWADS 0.13.0 / Pi 0.4.2 — integrated candidate

Built 2026-11-15 from the current local 0.12.0 updater/application branch, with screen analytics and native list previews merged in. No live installation performed. Existing 0.12.0 / 0.4.0 / 0.4.1 packages are retained separately.

## Added

- Placement and playlist screen starts, completed plays and cumulative active play time, with date range and device/location breakdowns under HWADS → Engagement.
- Independent placement ownership; no template engagement and no playlist rotation metric.
- Offline Pi queue, cumulative idempotent checkpoints, background upload, queue health, durable daily totals and 90-day replay protection.
- Preview column on both My Ad Templates and My Ad Placements. Images are uncropped and linked to their native editor. Videos use a saved WordPress poster if available; otherwise they show a video icon. Generating/caching a poster from the selected video timestamp is not implemented by this change.
- Existing approvals, Divi initialization, immutable publication and updater behavior are preserved.

## Test installation

1. Upload `dist/hwads-0.13.0.zip` in WordPress → Plugins → Add New → Upload Plugin and replace HWADS. Reload existing editor/list tabs.
2. If the Pi already completed the 0.4.0 updater bootstrap, upload `dist/hwads-pi-0.4.2.zip` under HWADS → Player Updates and deploy it to the test device. The existing stable updater accepts this package without a new bootstrap: no new runtime filename or protocol is introduced.
3. For a player that has NOT completed the updater bootstrap, use the existing manual installation process with the new 0.4.2 package. The installer deliberately disables playback; verify configuration before enabling it. Do not repeat bootstrap just to enable analytics on an already bootstrapped device.
4. Wait for fresh media sync and background analytics delivery, then open a placement/playlist Screen Engagement panel. The normal sync service must stay running; `--once` does not run the uploader.
5. Check locally with `python3 ~/hpads/scripts/hwads_sync.py --root ~/hpads --analytics-status`. Set `"analytics_enabled": false` in config/device.json and restart player/sync for an explicitly excluded test device.

WordPress can be installed first while old players keep running. Analytics starts only after updated players receive signed attribution on sync. No historical starts are reconstructed from heartbeat data.

## Definitions and limits

Time measures active image/video playback, excluding transitions, preloads, frozen video and outgoing-image loading holds. Partial plays contribute saved time without a completion. Plays crossing midnight belong wholly to the start date. Daily buckets use the WordPress timezone when first recorded; changing the timezone does not rebucket old totals.

Network failures retain data on the Pi. A crash/power cut can lose the last unsaved checkpoint interval and in-memory backlog. Queue capacity is 100,000 pending/rejected plays per device; overflow is reported as loss rather than interrupting playback. Very old uploads beyond 90 days are rejected so pruned duplicate records cannot be counted again. Daily totals survive raw cleanup. Normal WP-Cron execution is needed for raw cleanup.

Website displays, landing loads, outbound clicks and QR visits are not implemented in this screen-only candidate and remain labeled as not reporting. No automatic update window or TV power detection is added.

## Validation and acceptance

See VALIDATION-0.13.0.md. Actual Pi playback/SD overhead, clock recovery, network catch-up, updater install/rollback, physical output and staging MySQL transaction behavior still require on-device/on-host testing. Existing 0.4.0 or 0.4.1 can be retained as the previous runtime for rollback; rolling back to pre-analytics code stops future recording but retains the SQLite queue in config.
