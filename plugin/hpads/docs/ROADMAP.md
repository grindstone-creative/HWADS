# After the 0.6.0 testing checkpoint

- Native Ad Template / Ad Placement content editor at top, consistent details card below; optional Divi integration tested against an actual installed version.
- Pi sync client: complete verified downloads before switching; offline last-good playback; translate manifest timing/schedule fields; persistent player and transition/QR renderer support.
- Finish advertiser self-service and optional approval experience, proof-of-play and engagement reporting with separate page-click versus QR redirect references.
- Billing, boosted ads, advanced recurrence and bulk operations after the basic workflow is verified.

This checkpoint includes the CPT storage conversion, native list screens, renamed menus, playlist Media Library and existing-ad copies, editable placement options, timezone handling and private browser playback preview.

## Deferred QR positioning request — 2026-11-14

Add QR anchors: top-left, top-center, top-right, middle-left, center, middle-right, bottom-left, bottom-center, bottom-right. Add Custom X/Y position, preferably normalized percentages within the ad canvas, plus size and safe edge margin. Store these as placement fields and copy defaults from templates; publish them in the immutable manifest for web preview and eventual player rendering. QR enable/disable remains independent.

Investigate Divi module Advanced Position/absolute-location and offset controls as the web editor UI. WordPress core has no built-in signage QR-position field. Do not couple player positioning to Divi-generated markup or responsive CSS; keep renderer-neutral placement metadata. This is explicitly deferred and is not part of the native-editor checkpoint.

## Metrics revision attribution (required when tracking is implemented)

Placements retain durable identities, while material creative/link/QR/landing-content changes produce new published revisions. Store events against placement ID + published revision/manifest version + channel + device/location + event time. Never relabel historical events as current content. Reports should provide lifetime totals and per-revision breakdowns.

Player events must identify what was actually displayed, including cached/offline versions. Tracked click/QR references must map immutably to the published revision and destination so an old QR/link does not credit replacement artwork. Draft saves must not change live attribution. Tracking/event collection itself is not implemented in this release.
