# Venue and location workspace — 0.15.0

Venues represent companies. A venue contains locations; a location contains playlists and devices. Existing logical IDs, ownership, device assignments, publications and metrics stay in place. No data migration or Pi update is needed for this workflow.

## Navigation

Open a venue to see its locations. Open a location's workspace from the venue or the Locations list's **Open workspace** action. The workspace has Playlists, Templates, Devices and Settings tabs. Location and playlist editors remain native WordPress screens, with links back to the location workspace.

## Copy a playlist

Choose **Copy to location** on a playlist in the workspace or native playlist list. Choose a destination venue and location, enter a name, then copy. This uses the last saved source draft, not unsaved browser edits or a previous published revision.

The new playlist is a draft with new placement IDs. It preserves order, authored content, media references, transitions, overlays, QR configuration, active switches, schedule values and duration overrides. Blank overrides inherit the destination location's defaults. Dates are retained even if they are already expired. Divi landing content and settings are copied. Media files are reused, not uploaded again.

Source publications, devices, playlist mix assignments, approval votes and engagement history are not copied. The source remains unchanged. Venue-owned placements become owned by the destination venue. Advertiser-owned placements retain their advertiser, which must be eligible for the destination. Destination approval rules apply; the existing SuperAdmin-authored bypass still applies only to SuperAdmin. Publishing remains an explicit action.

Copying requires access to the source, permission to create a playlist at the destination, and permission to author content for each resulting owner. A source revision check rejects stale forms. Partial failed copies are removed.

## Templates

Templates have an availability setting in their native editor:

- **Venue templates**: available at locations of the selected owner venue; the default for venue-owned templates.
- **This location only**: available at the selected location.
- **My templates**: personal picker organization for the author across eligible locations. This does not grant cross-venue permissions or change underlying ownership.

Existing templates use their existing owner and creator to determine their group without rewriting records. Advertiser-owned legacy templates without an explicit scope remain personal; explicitly configured advertiser venue scopes require a connected venue.

The playlist picker groups these scopes and offers **Browse templates**, with tabs, search and media thumbnails. Selecting **Use template** creates an independent placement. The original template remains unchanged.

The location's Templates tab offers **Copy template to…**. A copied template has its own ID and editable content. Cross-venue copies of venue-owned templates become destination-owned. Existing access checks remain in effect; availability is an organizing filter, not a permission grant.

## Validation

New integration checks cover independent same-venue and cross-venue copies, copied settings and Divi metadata, source isolation, stale forms, template scope filtering, unauthorized destinations, retained advertiser identity, blocked unapproved publication and disconnected advertiser destinations. Browser checks cover actual navigation, the venue/location selector, copy form submission into the native editor, mobile workspace width and the template dialog. PHP and JavaScript syntax are checked separately.
