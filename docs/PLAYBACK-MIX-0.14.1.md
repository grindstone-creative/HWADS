# Playback mix, location address and role navigation — 0.14.1 / Pi 0.5.1

Local test packages. No live WordPress site or Pi has been updated by this chat. These versions incorporate the current shared source, including the configuration-connection fixes developed alongside this feature.

## Install and configure

1. Replace HWADS in WordPress using `dist/hwads-0.14.1.zip`. Reload open editors.
2. Under **Devices → Player Updates**, upload `dist/hwads-pi-0.5.1.zip` and deploy to the test Pi. The existing protocol-1 updater can install it; no new updater bootstrap or OS packages are needed for an already bootstrapped device. For a fresh/unbootstrapped device, extract the player ZIP on its desktop and use its `install.sh`, then verify device.json, start the sync service and enable browser playback as in the player README.
3. Open **Locations → Edit Location → Playback mix**, enable weighted shuffle, add published playlists from this location and assign whole-number percentages totaling 100%. Choose a published fallback playlist or use local placeholders. Save Location. One to twenty distinct playlists are supported; duplicate/unpublished/cross-location entries are rejected. Disabled mix settings retain their previous configuration.
4. Open the device and check **Playback source**. New/unassigned devices inherit the location mix. Existing device-level Primary/House assignments remain legacy overrides until you explicitly choose **Inherit location Playback mix**. Devices without an enabled location mix retain Primary/House behavior.
5. Keep the normal sync service running. At the next successful media sync, all required files are verified before the complete new configuration is activated. Failed or interrupted sync retains the previous playable manifest. Cached mixes continue offline.

Old players ignore the new mix field and continue their existing Primary/House assignments. Update the test player before expecting weighted shuffle. Location settings do not publish draft playlist content: only each playlist's immutable published version is eligible.

## Selection behavior

The player balances **observed active playback seconds**, not slide counts or declared video lengths. With 60/30/10 shares, a long video temporarily puts its playlist ahead; later selections compensate. Only currently playable groups contribute to the active weight total. Expired, future, wrong-orientation and missing-media entries do not consume their playlist's share. If a group becomes unavailable, available shares rebalance rather than building a catch-up debt.

Within a playlist, placements are shuffled in bags so each gets a turn before its next cycle. Consecutive repeats are avoided when another playable placement exists. Videos run naturally to completion unless skipped, expired or failed. Left Arrow revisits actual playback history; automatic playback then returns to the weighted mix. Time spent on manual revisits is still credited.

Shares are targets over many plays, not exact guarantees within every minute/loop. Full videos, manual navigation and anti-repeat rules affect the achievable mix. In particular, a single-placement playlist cannot always reach a very high share while avoiding repeats against alternatives. Add more placements when that matters. Load/transition time and a crashed in-progress play are not reconstructed into precise time quotas.

The persisted state stores balances, bags and bounded history outside release code. Restarts preserve completed-play balance; malformed saved state resets safely. If state cannot be written, playback continues in memory. Requests are idempotent so a lost response does not charge the same time twice or consume extra turns.

Fallback is separate from the weighted mix. It plays only when no mixed group can play. Decoding failures cool down the failing item, allowing other groups or fallback to run. Local placeholders are the last resort if the configured fallback also fails. No new design library or third-party dependency is introduced. The browser uses one private loopback selection request per placement; mpv uses the same Python selector.

## Metrics and security

Every selected item retains its original placement ID, publication version and analytics grant. Plays remain attributed to their original playlist, not a synthetic combined playlist. Existing heartbeat checks accept assigned mix versions. Browser selection remains loopback-only, random-path scoped and origin checked. Media serves only through the existing allowlist; mix state stores no device keys or analytics grants.

## Location address and menus

Locations now have a multiline **Location address** field (500 characters, plain text). It supports street/city/state/postcode/country and is saved independently of Playback mix. An older open form without the new field does not erase an existing address.

HWADS navigation and dashboard choices omit sections unavailable to the effective role/assigned teams. Direct section URLs are checked too. Scoped read-only sections remain visible when a user has legitimate viewing access. Existing per-record checks, user-management restrictions and write nonces remain in place; hiding menus does not grant or remove record ownership. SuperAdmin View-as uses the simulated role and assignments. Advertisers retain placement navigation even when they cannot access Playlists.

## On-device check

Confirm inherited 60/30/10 playback on one Pi, natural video completion, arrow navigation, fallback/expired content, offline playback, restart continuity and original playlist metrics. Real Raspberry Pi decoding, user-service startup, SD performance and sustained playback remain hardware checks. Automated fairness and browser tests do not constitute an advertising-time guarantee.
