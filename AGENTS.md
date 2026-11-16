# HWADS project context

- The user's cPanel does not have MultiPHP INI Editor. Do not suggest using it; base PHP configuration guidance on the controls actually available in their hosting account.

# Client-facing terminology

- Use **HWADS Box** for playback hardware in all client/viewer-facing text. Do not expose Raspberry Pi, Pi, operating system, browser, playback engine, or other implementation details in client-facing wording. Technical names may remain in internal code, protocols, paths, maintenance documentation and engineering chats when needed.

# HWADS interface standard

The user's standing preference is to use the standard WordPress interface unless it does not make sense for the task.

- Use real native custom-post-type edit screens, native title/content editors, meta boxes, right-hand sidebar and list tables. Do not redirect editors into imitation post forms.
- Preserve standard draft/publish/update behavior and wording when those concepts apply. Playlists use Save Draft and Publish, then Update after publication. Avoid duplicate custom Save & Publish or Publish to Players controls.
- Venue/location/device/advertiser records do not require approval. Use clear native save controls appropriate to configuration records; never imply that they must be published to players.
- Use custom controls only where WordPress has no useful equivalent: Slidestone-style placement cards/order, playback previews, media presentation and QR positioning. Keep their visual design consistent with WordPress.
- Templates/placements own landing-page web content. Playlists contain placements and have no unrelated web-content editor.
- Keep the placement landing-page editor visible. Keep playlist playback preview embedded on the playlist editor.
- Use the WordPress site timezone for date/time editing and UTC for stored playback schedules.
- Preserve scoped permissions, nonces and immutable published versions. Native UI is not a reason to relax authorization or publish draft content.
- Apply these defaults to new UI and refactors. Explain concrete exceptions when necessary; do not create alternate UI for its own sake.
