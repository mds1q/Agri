# PHP portal admin and signed config API

This panel edits portal, splash, UI colors (status bar, navigation bar, progress, retry buttons, and accent), maintenance, security, and welcome popup settings; it signs the exact JSON bytes on save and serves the JSON and Base64 RSA/SHA-256 signature in the format the Android app already verifies. Existing unrecognized JSON fields are preserved when the panel saves.

## Deploy

Use PHP 8.1+ with the OpenSSL and fileinfo extensions. Set the web server document root to `php-control-panel/public`; keep `php-control-panel/storage` and the signing private key outside the public document root. The included storage directory contains the current remote config and the matching public key. The matching private key is included at `private/config-signing-private.pem`, outside `public/`. Keep this ZIP restricted: anyone who gets the private key can sign remote app configuration.

Splash images can be uploaded as JPEG, PNG, or WebP files up to 5 MB each, or removed from the admin page. Uploads are stored in `storage/splash-images/` and served through the read-only API; deleting or replacing an image removes its old stored file after the config is saved. The public API URL is derived from the admin request. If TLS terminates at a reverse proxy or the panel is published under a URL prefix, set `AGRI_PUBLIC_BASE_URL` to the public base URL corresponding to the `public/` directory (for example `https://admin.example.com` or `https://example.com/portal`). The included `public/.user.ini` sets a 24 MB form limit for up to four image uploads in one save.

You can install from the browser at `/install.php` over HTTPS. The installer detects its package paths and signing key automatically. On first access it generates a random one-time token in `private/.install-token`; copy it from cPanel File Manager, then enter it with the admin credentials. The installer checks PHP/OpenSSL and verifies that the initial config signature and private key match the app. After it writes `private/config.php`, `/install.php` returns 404 permanently. The private settings file is outside the public document root. The admin page requires HTTPS. `AGRI_ALLOW_HTTP_ADMIN=1` is only for local development.

Alternatively, use the one-time CLI installer from the panel root as the same service account that runs PHP. It accepts `AGRI_ADMIN_USER` and `AGRI_ADMIN_PASSWORD`; the signing key path is detected automatically. Both methods save a password hash and signing key path. Never move the private key into `public/` or publish this ZIP. The private key is outside the intended document root in `private/`.

The installer requires a unique admin password of at least 14 characters; a long passphrase is recommended. Login allows five failed attempts from one client IP in a 15-minute window, then blocks that IP for 15 minutes. Attempt state is stored in `storage/.login-attempts.json`, so keep `storage/` writable by PHP and outside the public document root.


Keep the package layout intact. Set the hosting document root to `php-control-panel/public`; an installer cannot change the hosting provider's virtual-host/document-root setting. The installer detects the server absolute path from its own location, so it works under different cPanel home directories.

Make `storage/` writable by the PHP service account. The API is:

- `https://your-domain/api.php?file=portal-config.json`
- `https://your-domain/api.php?file=portal-config.json.sig`
- `https://your-domain/api.php?file=splash-image-<random-id>.jpg` (uploaded splash photos)

The Android app's signature fetcher appends `.sig` to its configured URL, so build the app with the first URL as `remoteConfigUrl`:

```sh
gradle -Pandroid.aapt2FromMavenOverride=/tmp/aapt2-wrapper/aapt2 assembleRelease -PremoteConfigUrl='https://your-domain/api.php?file=portal-config.json'
```

Install that APK once. After that, saves in `admin.php` publish a newly signed config; the app polls while foregrounded and applies it without an APK rebuild.

## One-time installation

From the `php-control-panel` directory, set the installer inputs and run `install.php`. Enter the password at the prompt so it does not become part of shell history:

```sh
exec bash
export AGRI_ADMIN_USER=portaladmin
export AGRI_CONFIG_PRIVATE_KEY=/absolute/path/to/secrets/config-signing-private.pem
read -r -s -p 'Admin password: ' AGRI_ADMIN_PASSWORD; echo
export AGRI_ADMIN_PASSWORD
php install.php
unset AGRI_ADMIN_PASSWORD
```

After installation, the temporary environment variables can be cleared; the private settings file supplies them to the panel. For local development only, set `AGRI_ALLOW_HTTP_ADMIN=1` when starting PHP and use `php -S 127.0.0.1:8080 -t public`. Open `http://127.0.0.1:8080/admin.php`. For the Android emulator, use `http://10.0.2.2:8080/api.php?file=portal-config.json` as the build's `remoteConfigUrl`; on a physical device use the computer's LAN address. Use HTTPS for deployment.
