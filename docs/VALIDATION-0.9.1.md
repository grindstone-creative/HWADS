# HWADS 0.9.1 validation

76 WordPress integration assertions passed, including disabled-by-default update policy, shared location/device source, WordPress timezone, strict time validation, optimistic concurrency, assigned venue-admin/account-manager access and rejection of unassigned/ordinary venue users. Existing publishing, heartbeat, cleanup and public snapshot checks passed.

Update policy is configuration only. No software updater, TV power detector or reboot schedule is implemented. Pi 0.2.0 need not be reinstalled for this settings-only change.
