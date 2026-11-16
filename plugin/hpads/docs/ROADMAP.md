# HWADS remaining work

Updated 2026-11-16 from current source. Historical checkpoint notes are not an authoritative unfinished-work list.

## Implemented before 0.15.9

- Native WordPress editors/lists, templates and placements in one Ads library, scoped Designer access and ownership-preserving sharing.
- Placement-owned Active/duration/schedules, playlist limits, editable inactive artwork, immutable publications and weighted location/device playback.
- Venue editor with details in the right sidebar and in-page dashboard sections, filters and search.
- HWADS Box syncing, offline last-good playback, overlays, QR custom positioning/size/colors/edge margin, hardware telemetry and version-attributed Box playback reporting.

## Included in 0.15.9 / HWADS Box 0.5.4

- Shared default public HWADS Box page and editable WordPress/Divi override, branded login and expected published playback based on each Box's effective settings.
- Content import/export, relationships and media, import preview and a demo seed; imported content remains unpublished and existing records are not overwritten.
- Pending analytics queue recovery/purge with acknowledgement from the HWADS Box.
- Website embed impressions separated into local, remote and unknown source, with immutable publication/revision attribution.

These items are included in the 0.15.9 plugin release. Pending-queue purge also requires HWADS Box 0.5.4. No live installation is performed by building these packages.

## Still deferred or requiring a decision

- Finalize role-specific dashboard summaries/approval layouts beyond the shared Main, Venue and Devices screens. Earlier role mockups are proposals, not separately implemented dashboards.
- Invoicing, payment/fee collection and billing workflows.
- Boosted/paid ad features and their scheduling/business rules.
- Advanced recurring schedules beyond the current Active switch and start/end date range.
- Broader bulk editing/operations with defined permissions and validation.
- Separate tracked QR redirects versus ordinary landing-page/link clicks. Existing QR rendering and website impressions do not provide this distinction or unique-person counts.
- Optional named nine-position QR anchor shortcuts. Custom coordinates/size/edge spacing already exist; do not list all QR positioning as unimplemented.

## Validation requiring the installed environment

- Exercise the actual licensed Divi theme/version and custom modules on the hosting site. Disposable tests can simulate theme presence but cannot prove commercial-theme compatibility.
- Production smoke checks and HWADS Box update rollout are separate from building local release ZIPs.

## Rules retained for future reporting and changes

Events must retain the placement identity, immutable published version/revision, channel and actual event time. Draft edits must not relabel historical reports. Box events must describe what was displayed, including offline cached versions. Public web payloads must not expose device credentials or authenticated Box telemetry grants.

Role dashboards, imports and native UI must keep existing scope, review and publication checks; displaying a control is not authority to publish or manage another team.
