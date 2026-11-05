# Live embed origin recovery

Use this runbook only after production configuration/deployment has been separately authorized. Automatic manifest repair requires deploying the new backend code; changing configuration alone cannot repair cached manifests. The application must sign live embed URLs from its configured public HTTPS origin; proxy request hosts and queue-worker context are not authoritative.

## Quick path

1. Deploy the backend version containing the automatic manifest-repair code, then set `APP_URL=https://signage.finespublicidad.com` in the authorized environment. For Docker, recreate the application instance with the same image and updated environment; restarting the old container does not apply changed environment variables.
2. If a preserved `bootstrap/cache/config.php` is present, run `php artisan config:clear` in that instance. Then restart PHP-FPM and queue, scheduler, and Reverb workers so all processes load the same configuration. Do not run `cache:clear` or remove storage data for this repair.
3. Allow each device's next authenticated manifest/sync poll to publish a new manifest version. Confirm its pending manifest contains the canonical HTTPS embed origin; wait for the device's normal successful ACK before treating it as active.

## Recovery safeguards

- A device with a cached noncanonical live embed receives a new pending manifest version; its current manifest remains available until the normal ACK transition.
- If a good pending manifest already exists, polling does not generate another version just because the older current manifest is noncanonical.
- Do not edit an installed manifest in place, reset devices, delete event queues, change provider allowlists, or disable HTTPS/signature verification.
- No volume changes or database resets are required. Production configuration changes and deployment require separate authorization.

## Verification

- Confirm manifest URLs use `https://signage.finespublicidad.com/live/embed/{id}` without exposing signatures in logs or screenshots.
- Confirm the device reports the new version pending, then current only after its successful ACK.
- Confirm the `/live/embed/{id}` signature still validates and a modified query string is rejected.
