# Android TV activation recovery

Deploy the backend and run `php artisan migrate` before distributing the new APK.
The additive migration creates a nullable, unique `devices.recovery_key_hash`.
No historical device can be recovered until an authenticated installation has enrolled.
Upgrade and open the existing activated app, while online, before uninstalling it.
If that installation was already removed or its credential revoked, normal activation is necessary once.

## API v1

- `POST /api/v1/device/activation/recovery/enroll`: bearer authentication; JSON `recovery_key` (64 lowercase hexadecimal characters); 204 on enrollment or identical retry, 409 on conflict. Enrollment cannot replace an existing capability or claim another device's capability.
- `POST /api/v1/device/activation/recovery`: public JSON `recovery_key`, optional `app_version`; 200 with `token`, `token_type: Bearer`, and original `device` (`id`, `uuid`, `name`, `layout_id`). `device_uuid` is not proof and is rejected.
- Malformed values return 422; missing enrollment, removed/disabled/pending/revoked devices, missing token and nonactive businesses all return the same 404. Attempts are limited to 10/minute per IP and 5/minute per capability fingerprint across IPs; excess requests return 429.

Recovery restores the existing device record and assignment. It does not duplicate a screen,
change its business, location, layout, playlist or saved settings, or reconstruct local cached files.
Expired bearer tokens can be replaced if recovery was previously enrolled; expired tokens cannot enroll.
The app synchronizes the manifest and content again after reinstall and needs a network connection.

## Identity and security boundary

The app derives the capability as lowercase SHA-256 of the UTF-8 string
`alter-tv-activation-recovery-v1:<applicationId>:<normalizedAndroidId>`.
ANDROID_ID has at most 64 bits of entropy. Hashing does not increase that entropy or
provide hardware attestation. The capability is practical possession evidence for this TV deployment;
anyone who obtains it can recover access and invalidate the previous installation's bearer.
Keep it out of HTTP body logging, tracing, diagnostic exports, screenshots and backups.
It is stored only as a further SHA-256 hash in the dedicated hidden column, never device metadata.
Application ID, signing identity, Android user and TV identity must remain unchanged.
Factory reset, another Android profile or signing identity may require normal activation.
Android backup remains unnecessary for this flow.

Every recovery success issues a fresh bearer token and invalidates the previous token.
Retries after a lost response work with the same capability; the latest committed token wins.
Transactions lock the device and business before checking eligibility and issuing that token.
Enrollment rechecks the bearer under the device lock, rather than trusting only middleware.
The unique database index also prevents concurrent enrollment of one capability on two screens.
SQLite regression tests cover stale authorization, conflicts and successive recovery;
production database row-lock contention requires deployment verification on the actual database engine.

Revoking a device clears its recovery hash. Ordinary activation also clears it before
issuing a token, preventing an older TV capability from following a reused assignment.
The newly activated installation must enroll again. Deletion removes the hash with the record;
admin deletion additionally revokes claimed activation codes. Neither recovery nor reenrollment
resurrects a revoked or deleted device. Disabling a screen denies recovery while disabled.

Rollback: stop distributing the new APK before reverting the routes/schema. Older APKs keep
using the existing activation API. Removing the recovery column discards enrollment only;
back up the database through the normal deployment procedure before schema rollback.
