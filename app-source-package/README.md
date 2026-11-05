# Secure Web Portal (Android)

Native Android WebView shell for the supplied portal configuration. The app downloads remote JSON on launch and uses the bundled `app/src/main/assets/portal-config.json` when the endpoint is unavailable.

## Remote configuration

The `remoteConfigUrl` Gradle property sets the remote config endpoint; without it, the app uses the current GitHub raw URL. Remote config requires two responses: JSON at that URL and a Base64 RSA/SHA-256 signature at the same URL plus `.sig`. The app verifies the exact downloaded bytes before parsing or applying them. Missing or invalid signatures are rejected and the bundled config is used. Keep `config-signing-private.pem` private; it is excluded from Git.

The app fetches the remote JSON and its `.sig` signature on launch and checks them every 5 seconds while the app is open and in the foreground. It only applies JSON whose RSA/SHA-256 signature matches the public key embedded in the app. Keep `config-signing-private.pem` private; it is excluded from Git. To sign a config, run `tools/sign-config.sh portal-config.json`, then publish both `portal-config.json` and `portal-config.json.sig` in the same directory. The app pauses checks in the background and resumes them when reopened. This is polling, so updates are normally applied within 5 seconds plus network response time.

The signing key pair was generated for this project. Back up `config-signing-private.pem` securely. `remote-config/portal-config.json` and its `.sig` are a matching signed copy of the currently published remote JSON; upload both files to the configured GitHub path so the released app can accept remote updates. To publish a changed config, edit the JSON, run `tools/sign-config.sh path/to/portal-config.json`, and upload the JSON and resulting `.sig` together. If the private key is lost or compromised, replace the embedded public key and release a new app before trusting a replacement signer.

For a web admin panel that signs updates automatically, see [`php-control-panel/README.md`](php-control-panel/README.md). Build an APK with the deployed API URL once; subsequent config edits can be made in the panel.

## Build

Open this directory in Android Studio and build the `app` configuration. Package ID: `app.agriwealth.portal`. The release app targets Android API 24+ because the current Firebase Messaging SDK requires that minimum.

The release build reads `release-signing.properties` from the project root and uses the keystore named there. Keep `release-signing-key.p12` and `release-signing.properties` backed up in a private location. Android only accepts an update when it is signed with the same key as the installed app.

### Release APK paths

- Gradle output: `app/build/outputs/apk/release/app-release.apk`
- Latest shared copy: `SecureWebPortal-release-latest.apk`

On this ARM build environment, use `-Pandroid.aapt2FromMavenOverride=/workspace/agri/tools/aapt2` when running Gradle.

## Configuration notes

- Links for `allowed_domains` stay in the WebView. Other HTTP/HTTPS links open externally only when `allow_external_links` is true; unsupported URL schemes are blocked. Set `https_only` to block HTTP navigation and mixed content.
- Long-press on links is controlled by the Admin Panel's `Link inspection` setting, including the native Android link preview/context menu.
- The app requires a validated internet connection to open the portal. Without one it shows a No Internet page and retries automatically.
- The welcome popup appears after the splash screen; its enabled state, title, message, button text, and optional image URL come from `popups_and_notifications.welcome_popup` in the JSON config. The image keeps its aspect ratio and is limited to a native 220dp display height.
- For remote control, set `enabled`, `title`, `message`, and `button_text` in the configured remote JSON. The currently configured remote JSON still has `enabled: false`, so it must be changed there to show the popup in the downloaded app.
- `ssl_pinning`, `certificate_pins`, and push notifications are not supported by this WebView build. The admin panel does not expose these unsupported switches; existing values are preserved when saving.
- Root and emulator `warn` policies show a native warning toast. Debugger detection set to `block` exits when Android reports an attached debugger.
- `disable_selection` blocks page text selection. `inspect_links` adds a long-press dialog with open and copy actions. Splash pages can show configured image URLs, background colors, and durations; fullscreen splash behavior is implemented.
- `onboarding` controls the animated launch slider. It supports up to four pages, per-page title/message/image/background/accent, dot indicators, Skip, Next, and the final Get Started action. The PHP admin panel controls these fields and signs the updated JSON automatically; an empty image URL uses the native illustration fallback.
- Push integrations include Firebase Cloud Messaging and OneSignal. The signed config selects `none`, `firebase`, `onesignal`, or `both`; public client identifiers are remote-configurable. Provider server keys are kept in the PHP panel's private `push-credentials.php` and are never shipped in the APK or JSON. The PHP panel can send an all-subscriber notification with title, message, HTTPS image URL, and HTTPS click URL.
