#!/usr/bin/env bash
set -euo pipefail

CACHE_DIR="${1:-/tmp/ps-emailsub-apk}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEYS_DIR="$CACHE_DIR/keys"
KEYS_MANIFEST="$ROOT/tests/alpine-keys.sha256"
PHP_IMAGE="php@sha256:2fbf149aa1e9ab4fda5e6a82261114543cc3c2419f46bb20d33cc01345ebd7c8"
DOCKER_BIN="${DOCKER_BIN:-/tmp/docker-full/docker/docker}"
export DOCKER_HOST="${DOCKER_HOST:-unix:///tmp/docker.sock}"

mkdir -p "$KEYS_DIR"
while read -r hash name; do
  [ -z "$hash" ] && continue
  dest="$KEYS_DIR/$name"
  if [ ! -f "$dest" ]; then
    "$DOCKER_BIN" run --rm --entrypoint /bin/sh "$PHP_IMAGE" \
      -c "cat /etc/apk/keys/$name" >"$dest"
  fi
done <"$KEYS_MANIFEST"

(
  cd "$KEYS_DIR"
  sha256sum -c "$KEYS_MANIFEST"
)

if ! ls "$CACHE_DIR"/*.apk >/dev/null 2>&1; then
  echo "No APK packages in $CACHE_DIR" >&2
  exit 1
fi

"$DOCKER_BIN" run --rm \
  -v "$CACHE_DIR:/apk-cache:ro" \
  -v "$KEYS_DIR:/etc/apk/keys:ro" \
  --entrypoint /bin/sh \
  "$PHP_IMAGE" \
  -c 'apk add --no-network /apk-cache/*.apk >/dev/null'

TAMPERED="$(python3 - <<'PY' "$CACHE_DIR"
import gzip, sys, zlib
from pathlib import Path

cache = Path(sys.argv[1])
src = sorted(cache.glob("re2c-*.apk"))[0]
data = src.read_bytes()
pos = 0
members = []
while pos < len(data):
    d = zlib.decompressobj(16 + zlib.MAX_WBITS)
    d.decompress(data[pos:])
    unused = d.unused_data
    consumed = len(data[pos:]) - len(unused)
    members.append(data[pos:pos + consumed])
    pos += consumed

sig_c, ctl_c, data_c = members[0], members[1], members[2]
data_r = zlib.decompressobj(16 + zlib.MAX_WBITS).decompress(data_c)
mut = bytearray(data_r)
mut[2000] ^= 0x01
bad = gzip.compress(bytes(mut))
out = Path("/tmp/ps-emailsub-tampered.apk")
out.write_bytes(sig_c + ctl_c + bad)
print(out)
PY
)"

set +e
"$DOCKER_BIN" run --rm \
  -v "$TAMPERED:/tmp/tampered.apk:ro" \
  -v "$KEYS_DIR:/etc/apk/keys:ro" \
  --entrypoint /bin/sh \
  "$PHP_IMAGE" \
  -c 'apk add --no-network /tmp/tampered.apk' >"$CACHE_DIR/tamper.log" 2>&1
tamper_code=$?
set -e

if [ "$tamper_code" -eq 0 ]; then
  echo "Tampered APK was accepted" >&2
  cat "$CACHE_DIR/tamper.log" >&2
  exit 1
fi
if ! grep -qE 'BAD signature|BAD archive|does not contain expected data' "$CACHE_DIR/tamper.log"; then
  echo "Tampered APK failed for an unexpected reason" >&2
  cat "$CACHE_DIR/tamper.log" >&2
  exit 1
fi

echo "APK cache and tamper checks passed"
