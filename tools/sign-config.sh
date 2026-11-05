#!/bin/sh
set -eu

CONFIG=${1:-portal-config.json}
KEY=${CONFIG_SIGNING_KEY:-config-signing-private.pem}

if [ ! -f "$KEY" ]; then
    echo "Signing key not found: $KEY" >&2
    exit 1
fi

openssl dgst -sha256 -sign "$KEY" -out "$CONFIG.sig.bin" "$CONFIG"
base64 < "$CONFIG.sig.bin" | tr -d '\n' > "$CONFIG.sig"
rm -f "$CONFIG.sig.bin"
echo "Wrote $CONFIG.sig; publish it beside $CONFIG"
