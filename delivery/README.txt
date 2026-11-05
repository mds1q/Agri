Secure Web Portal delivery

This ZIP contains the signed Release APK, source ZIP, and the signed portal config pair. It does not contain signing private keys.

The APK currently uses the configured GitHub raw JSON endpoint by default. The source ZIP includes an optional PHP 8.1+ admin panel and signed config API. To use that panel, deploy php-control-panel with HTTPS, configure its admin credentials and private signing key, then build and install one APK with the deployed API URL using the remoteConfigUrl Gradle property. After that, config updates saved in the panel are signed automatically and picked up by the foreground app.

See php-control-panel/README.md in the source ZIP for deployment and local development instructions. Keep config-signing-private.pem private; do not put it in the web root or delivery ZIP.
