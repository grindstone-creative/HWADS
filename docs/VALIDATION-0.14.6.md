# HWADS 0.14.6 validation

Local candidate, not deployed. No Pi source changed.

- 336 WordPress fixture assertions passed, including 43 new playlist-limit checks. Covered exact category defaults, configurable House/Fallback limits, 100/101 totals, Active switches regardless of dates, individual native edits, batch swaps/rollback, publication guards and per-playlist locks.
- 28 plugin PHP files and 11 JavaScript runtime scripts passed syntax checks.
- Actual Chrome verified immediate activation-limit prompts that leave the switch off in both playlist and individual editors, one editable maximum field, saved maximum persistence, category defaults and live counters.
- Actual Chrome verified full-color inactive artwork, red frame/tab, grey/slashed thumbnails, overlay editing while inactive, and desktop/mobile rendering with no page errors. Visuals use sample artwork.
- Plugin ZIP integrity and exact source correspondence passed.

The original schedule-overlap approach was replaced with the clarified rule: count every Active switch that is on. No location-level or cross-playlist cap was added.

Existing live publications are not automatically truncated. Production WordPress, installed Divi and physical devices were not exercised.
