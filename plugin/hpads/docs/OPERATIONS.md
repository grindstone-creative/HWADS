# HWADS 0.7.0 operations

Fresh activation enables native post storage directly. Create a venue/location, then templates and playlists. Templates and placements use native WordPress post editors; placements show a large preview and optional web content. Playlists have a title/bucket/sidebar without web content. Save Draft does not publish to players; Save & Publish to Players does.

This checkpoint does not implement Pi synchronization. The device API exposes authenticated immutable manifests and media URLs, checksums, UTC schedules and effective duration_seconds. A Pi sync client must verify a complete download before switching and retain the last good offline version. The current shell player expects local file/type/duration JSON, so it still needs that adapter.

Published landing pages read frozen snapshots, while native draft edits stay private. External links are direct; tracking and QR positioning are not yet implemented. Divi is not bundled or verified in this build. Per-location memberships and the rest of the UI overhaul remain on a separate unfinished branch.
