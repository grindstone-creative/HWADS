# HWADS 0.13.1

Install `dist/hwads-0.13.1.zip` through WordPress → Plugins → Add New → Upload Plugin, replacing HWADS. Reload open editors. This plugin update retains the integrated 0.13.0 analytics/list previews and existing data; it has not been installed on the live site.

## Editor

Placement and template editors now have one native name field at the top. The duplicate field in the settings panel is removed. That saved name feeds WordPress lists, playlist choices/thumbnails/previews, published device and website payloads, and the landing-page title shortcode. A draft name change does not rename the published snapshot until the playlist is published.

Placement settings replaces Slide settings. Active is an on/off switch beside the heading, with visible On/Off text, keyboard focus and Space support. The switch follows the selected placement in a playlist. Template settings has no playback switch because templates are not directly played. No other checkbox has been converted.

## Scheduled player updates

1. Under the location's software update window, enable scheduled updates and choose its preferred daily time. This uses the WordPress timezone. Saving the time alone does not queue or deploy anything.
2. Devices → Player Updates: upload an updater-ready ZIP, choose the release and devices, and select **Schedule Selected Devices**. **Install on Selected Devices** remains the immediate option.
3. A scheduled request uses today's time if queued before or within its 30-minute preferred window; after that window, it uses tomorrow's time. The chosen due time is saved with the request. Changing the location setting later does not move existing requests—cancel/requeue them if needed.
4. The server withholds the signed command until due. If the Pi is off, disconnected or its updater is not ready, the request stays pending. It is delivered at the next ready check-in after the due time, including after a missed boot/window. The TV may be on. Player software briefly restarts; the Pi is not rebooted. Media syncing and analytics retain their own schedules.
5. A previously updater-ready offline Pi can be scheduled. A Pi that never received the one-time updater bootstrap still needs that installation. Once a command is first sent, its signed seven-day expiry applies. Undelivered scheduled requests do not expire while the Pi is off.
6. **Cancel Scheduled Update** is available only before command delivery. Once delivered, cancellation cannot promise to stop an installation. Existing validation, owner-only deployment and startup rollback remain in place.

Uploading a ZIP does not automatically roll it out to all locations. Only explicitly selected devices are scheduled. No TV-power detection is needed. Device queue/check-in/cancel operations are serialized so overlapping requests do not overwrite one another.

No new Pi code is needed for this scheduling change: the server delivers the existing protocol-1 command at the right time. Keep Pi 0.4.2 for the integrated screen analytics features. A device without an updater still needs the manual bootstrap described in UPDATE-0.13.0.md.

Multiple-playlist location rotations are a proposed next change, not part of 0.13.1. Primary/House behavior is retained here.
