# HWADS 0.13.1 validation

2026-11-15. Changes were checked against a disposable WordPress 6.8.12 / PHP 8.3 site. Nothing was installed on the live site or Pi.

- All 26 plugin PHP modules parse successfully.
- Native editor fixture: 44 existing editor/storage/publication/permission checks passed while preparing this change.
- Real browser editor suite: 29 checks passed for a single clearly labeled placement/template name, removal of the duplicate settings field, preservation while editing other controls, preview/form/reload values, correct playlist settings/thumbnail names, placement/template headings, one header switch, canonical Active synchronization, persistence of both on and off states, Space-key operation, mobile visibility and JavaScript errors.
- Name propagation suite: 8 checks passed for canonical logical/native titles, placement cards, playlist draft preview, frozen old published name before publication, and the new published device manifest, landing snapshot, embed and heading shortcode after publication.
- Existing player-update suite: 16 checks passed after scheduling and serialization changes, including owner-only queueing, package validation, signed commands, matching version acknowledgements, rollback and actual authenticated REST routing.
- Scheduled-update suite: 21 checks passed for site timezone and daylight-saving conversion, today's preferred window / tomorrow selection, known offline-device queueing, immediate-install readiness, queue overwrite protection, withholding before due, missed-time delivery after weeks offline, readiness gating, expiry beginning on first delivery, cancellation before delivery only, owner-only scheduling/cancellation, disabled schedules, no TV-off requirement, overlapping operation exclusion and stale request-lock recovery.
- Real browser scheduled-update forms: 3 checks passed for selecting a known offline device, queueing a schedule with a visible due time, and cancelling the undelivered request.

Scheduling is server-side delivery gating. It uses the existing protocol-1 command and runtime allowlist; no Pi/player code was changed for 0.13.1. Existing integrated 0.13.0 / Pi 0.4.2 analytics validation is documented separately. Actual Pi startup/recovery, hosting MySQL/MariaDB concurrency and proprietary Divi behavior still require staging/hardware checks.

The ordered multi-playlist location rotation is a proposed next feature, not included here.
