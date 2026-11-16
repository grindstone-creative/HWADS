# HWADS 0.12.0 validation — 2026-11-15

All tests used disposable local data. No live website/Pi deployment, GitHub push or credential upload was performed.

## Automated checks

- PHP 8.3 parser: all 25 plugin PHP files parsed successfully.
- Disposable WordPress 6.8.12: native editor/storage saves, immutable publication, embeds, dashboard preferences, device telemetry/cleanup and shared update-policy permissions passed. Visual/overlay, Full-HD selection, site-timezone conversion, shared landing layout and landing text/visibility suites passed.
- Approvals PHP suite: 45 checks for any/all votes, direct submission to selected reviewers, required reviewer configuration, unrelated/unselected access rejection, read-only review without native post edit permission, vote concurrency, unchanged-save preservation, producer publishing, rejection/resubmission, policy changes and removed-reviewer failure, SuperAdmin revision stamps/explicit bypass and read-only role preview.
- Published snapshots were checked before edits, during pending review, after approval and after explicit publish. Pending/unapproved content never replaced the published landing. An unapproved republish was blocked and retained the prior live pointer.
- Browser tests: real reviewer and unrelated-user sessions, native venue policy rendering, personal queue, private player-rendered slide preview, actual review form POST/note/progress, queue removal, direct URL rejection, mobile review/queue width and JavaScript errors. The exact legacy Divi promotion was inserted into the scoped editor and hidden while unrelated controls remained visible.
- Divi starter suite: 5 checks with simulated Divi presence for valid starter structure, activation metadata, shared-layout precedence, repeat-open idempotence and preservation of custom content. Actual Divi theme is not bundled.
- Player update PHP suite: 16 checks for valid/unsafe ZIPs, immutable upload registry, bootstrap readiness, pending-command preservation, version-aware acknowledgements, rollback, owner-only deployment, real bearer-authenticated REST dispatch with a command ID in JSON, and rejection of a wrong device key. Device identity comes from the URL, preventing confusion with the command's JSON id.
- Real browser multipart ZIP upload and nonce-protected deployment form tested against a disposable fixture device. The local HTTP site uses an explicitly installed test-only HTTPS uploads URL filter; the production plugin still rejects non-HTTPS release URLs.
- Python suite: 36 tests passed, including existing sync/browser behavior and new signature/expiry/tamper checks, ZIP paths/allowlist/digests, bootstrap preservation, atomic activation, duplicate-command handling, failed-health rollback, explicit rollback, interrupted activation recovery, disabled-playback preservation and live/fresh health markers. Service/player processes are mocked in activation tests.
- Cross-language check: a real PHP-signed command was accepted by the Python verifier. Both 0.4.0 and 0.4.1 package manifests, runtime versions, allowlists and file hashes were checked by the actual updater.

## Remaining on-site checks

The actual Pi must verify its systemd user services, display-session restart, Chromium/video playback, network interruption and rollback. Simulated process/service tests do not prove hardware or power-loss behavior. One manual 0.4.0 bootstrap is required; thereafter 0.4.1 can verify the plugin deployment path. The stable updater protocol itself is not remotely replaced.

Divi 4.20.4 must verify the classic builder banner removal and absence of the creation selector on initialized ads. Existing custom layouts must be checked on the test site. Scheduled TV-off installation, email notifications, analytics and an editable role-permission matrix remain outside this release.
