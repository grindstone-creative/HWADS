# HWADS dashboards — 0.15.4

This release implements the approved Main / Venue / Devices dashboard structure. It requires only the WordPress plugin update. No Pi update, database migration, new media uploads or device reassignment is required.

## Main HWADS Dashboard

HWADS → Dashboard shows totals for accessible venues, locations and devices, plus requests awaiting the current user's approval when their role has approval access. The venue list includes visible Open venue dashboard links. Accounts without venue access receive a link to their authorized Ads library rather than a list of other venues.

## Venue Dashboard

Open a venue dashboard from the Main Dashboard, the Venues list, or the venue editor's Locations box. Start with All locations in that venue or select one location. Tabs link to the existing native WordPress Playlists, Ads/Templates, Devices and Locations lists, plus venue settings. The Devices tab opens the Devices Dashboard filtered to that venue/location.

The lists retain WordPress's status/date columns, search, date filters, sorting, pagination and available row actions. The template tab uses the same combined Ads library as the main Ads section, filtered to Templates and the selected venue/location. Context remains attached to search forms, status links and date filters. Scope applies to counts, including Mine, as well as table rows. Native permissions are checked before including records; venue/location filters never grant access.

Published dates are the native WordPress post dates. Published playlist snapshots remain independent of those post dates.

Venue and location navigation now leads to the consolidated venue dashboard/editor, with its tab and location selected. The obsolete workspace route and duplicate header have been removed; there is no bookmark redirect layer. Playlist-copy forms remain separate from native post-edit forms. Template copying is available through its native list row action.

## Devices Dashboard

HWADS → Devices shows a status summary above the existing native device list, with Venue and Location selectors. Counts cover the selected scope across all list pages; text searches do not change the status summary.

- Total devices: all devices the current user can read in scope.
- Online: enabled devices reporting within the existing heartbeat freshness threshold.
- Offline / stale: enabled devices whose last heartbeat is older than that threshold.
- Disabled: devices with playback disabled in configuration.
- Never connected: enabled devices with no heartbeat yet.

Categories are exclusive and sum to Total devices. Online is based on recent contact, not confirmation that a TV is switched on. The list includes reported player software, native Date, and existing playback/revision status. Authorized device managers receive Remote shell and Screen share row links when saved URLs exist. The owner-only Player Updates link remains available under Devices.

## Venue editor

Each saved venue has a native Locations metabox showing its associated locations, links to their workspaces, an Open venue dashboard link, a scoped native Locations list link, and Add location when permitted. This includes existing records; no association is inferred from names.

## Validation

Dashboard integration checks cover status categories, scope/count consistency, All locations, mismatched contexts, template visibility, status/search links, editor metaboxes and unauthorized venues. Browser checks cover Main, Venue and Devices dashboards; native playlist/template/device lists; scoped search; location selection; software/date columns; and mobile layout. Existing copy, approval, content-sharing and Ads library suites also pass.

The update is packaged locally and is not automatically installed on the live site.
