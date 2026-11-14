# HWADS 0.7.2 operations

Configuration records use native save controls: Save Venue, Save Location, Save Device and Save Advertiser. They are usable as soon as saved and do not require approvals. Venue active/inactive is administrative status. Device enablement and published playlist assignments control player operation.

Ad content saves as drafts. Publish a playlist using the standard Publish button, then Update after publication; this freezes the complete manifest/media/content version. Save Draft is shown only before publication. Refresh the embedded preview after saving to inspect the saved draft.

The device key appears once after native device creation/rotation. Settings QR links require login and device-management authorization, and never contain the device API key. Existing device keys are preserved on ordinary saves.

A Pi client still needs to download and verify complete manifests/media before atomic switching and convert duration_seconds to the local player's duration field. Keep the last good version for offline playback. This ZIP does not install a Pi client.

Tracking, QR positioning, Divi-specific rendering and per-location memberships remain pending. Do not treat these as implemented merely because they appear in the roadmap.
