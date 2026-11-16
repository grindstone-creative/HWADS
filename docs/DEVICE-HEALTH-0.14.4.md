# HWADS 0.14.4 / Pi 0.5.3

## Install

1. Upload `dist/hwads-0.14.4.zip` in WordPress → Plugins → Add New → Upload Plugin and replace HWADS. Reload open admin pages.
2. Open HWADS → Devices → Player Updates. Upload `dist/hwads-pi-0.5.3.zip` and install or schedule it for the test Pi. Existing updater-ready devices need no manual bootstrap or OS packages.
3. Keep the sync service running and allow the next heartbeat, then open the device's Device health panel. The existing site heartbeat interval controls refresh (normally five minutes). The first CPU percentage can be unavailable until there is a measurable counter difference; other available readings can appear immediately.

## Device readings

- RAM used / total and percent: Linux MemAvailable accounts for reclaimable cache. A MemFree/cache fallback supports kernels without MemAvailable.
- CPU usage: aggregate CPU counter delta since the previous sample, without a blocking sampling delay. CPU cores are shown separately.
- System load: operating system 1-, 5- and 15-minute load averages, not CPU percentages.
- CPU temperature: Raspberry Pi's standard thermal-zone sensor, in Celsius.
- Uptime: time since system boot, displayed as a duration.
- Existing disk space, playback state, versions, last contact/sync and errors remain.

Readings travel in the existing authenticated heartbeat; there is no extra polling endpoint, third-party dependency, new database table or history collection. Unsupported/missing probes show Not reported rather than fabricated zeros. Old players continue to work without the new readings. CPU and other probes are independent, and unavailable Linux files do not interrupt playback. These are latest snapshots, not a live monitor or historical chart.

The WordPress endpoint validates numeric ranges and derives RAM usage from total/available before storing the bounded readings in the existing latest-status row. Existing device access and report permissions remain in effect.

## Engagement access fix

The Engagement submenu was registered before its parent HWADS menu, producing an inconsistent WordPress page hook and a permission error despite a valid role. It is now registered after the parent menu. No extra account assignment is required for SuperAdmin.

After updating, open HWADS → Engagement. The canonical direct URL is `/wp-admin/admin.php?page=hwads-engagement`. If View as is active, return to SuperAdmin while checking your own full-access report. Scoped users still see only reports they are authorized to view; unassigned roles remain blocked.

## Validation

- Seven Python hardware tests cover memory/CPU counters, counter reset, temperature/load/uptime, older-kernel memory fallback, missing/malformed probes, and compact heartbeat delivery. Full player regression suite is run for the release, including existing sync, mixing, analytics and updater behavior.
- Fourteen WordPress checks cover bounded telemetry, derived RAM totals, old-client compatibility, actual heartbeat storage and Device health rendering.
- Thirteen real browser checks cover actual Engagement menu/direct navigation for SuperAdmin, scoped advertiser reports, denial of unauthorized access, and the hardware readings in the native device editor.
- Actual Raspberry Pi sensor/CPU readings, user-service startup and sustained on-device operation still need the normal one-device staging check. No live site or Pi deployment was performed by this chat.
