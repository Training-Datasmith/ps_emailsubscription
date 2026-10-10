#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NETWORK="ps-emailsub-test"
DB_CONTAINER="ps-emailsub-db"
PHP_IMAGE="php@sha256:2fbf149aa1e9ab4fda5e6a82261114543cc3c2419f46bb20d33cc01345ebd7c8"
MARIADB_IMAGE="mariadb@sha256:3a7d3cbc8b6fddf66433d80dc124c1e4e75a73ebab9c6e137529cc270bdadfc0"
DOCKER_TGZ="${DOCKER_TGZ:-/tmp/docker.tgz}"
DOCKER_SHA256="9b4f6fe406e50f9085ee474c451e2bb5adb119a03591f467922d3b4e2ddf31d3"
DOCKER_EXTRACT_ROOT="${DOCKER_EXTRACT_ROOT:-/tmp/docker-full}"
DOCKER_BIN="${DOCKER_BIN:-$DOCKER_EXTRACT_ROOT/docker/docker}"
export DOCKER_HOST="${DOCKER_HOST:-unix:///tmp/docker.sock}"

ensure_docker_bundle() {
  if [ ! -f "$DOCKER_TGZ" ]; then
    echo "Missing static Docker bundle at $DOCKER_TGZ" >&2
    exit 1
  fi
  echo "${DOCKER_SHA256}  ${DOCKER_TGZ}" | sha256sum -c -
  mkdir -p "$DOCKER_EXTRACT_ROOT"
  tar -xzf "$DOCKER_TGZ" -C "$DOCKER_EXTRACT_ROOT"
  if [ ! -x "$DOCKER_BIN" ]; then
    echo "Docker CLI not found at $DOCKER_BIN after extracting $DOCKER_TGZ" >&2
    exit 1
  fi
}

ensure_docker() {
  if "$DOCKER_BIN" info >/dev/null 2>&1; then
    return 0
  fi
  if [ -x "$DOCKER_EXTRACT_ROOT/docker/dockerd" ]; then
    sudo PATH="$DOCKER_EXTRACT_ROOT/docker:$PATH" "$DOCKER_EXTRACT_ROOT/docker/dockerd" \
      --data-root=/tmp/docker-root \
      --exec-root=/tmp/docker-run \
      --host=unix:///tmp/docker.sock \
      --iptables=false --ip6tables=false --userland-proxy=false \
      >/tmp/dockerd.log 2>&1 &
    for _ in $(seq 1 30); do
      if "$DOCKER_BIN" info >/dev/null 2>&1; then
        sudo chown "$(id -u):$(id -g)" /tmp/docker.sock 2>/dev/null || true
        return 0
      fi
      sleep 1
    done
  fi
  echo "Docker daemon is not available" >&2
  exit 1
}

cleanup() {
  "$DOCKER_BIN" rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  "$DOCKER_BIN" network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT

ensure_docker_bundle
ensure_docker

bash "$ROOT/tests/fetch-alpine-apks.sh" /tmp/ps-emailsub-apk

mkdir -p /tmp/phpunit-cache
if [ ! -f /tmp/phpunit-cache/phpunit-7.5.20.phar ]; then
  curl -fsSL -o /tmp/phpunit-cache/phpunit-7.5.20.phar https://phar.phpunit.de/phpunit-7.5.20.phar
  curl -fsSL -o /tmp/phpunit-cache/phpunit-7.5.20.phar.asc https://phar.phpunit.de/phpunit-7.5.20.phar.asc
  curl -fsSL -o /tmp/phpunit-cache/sb.asc https://sebastian-bergmann.de/gpg.asc
fi

"$DOCKER_BIN" network inspect "$NETWORK" >/dev/null 2>&1 || "$DOCKER_BIN" network create --internal "$NETWORK" >/dev/null
"$DOCKER_BIN" rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
"$DOCKER_BIN" run -d --name "$DB_CONTAINER" --network "$NETWORK" \
  -e MARIADB_ROOT_PASSWORD=ps_emailsub_test \
  -e MARIADB_DATABASE=prestashop_test \
  "$MARIADB_IMAGE" >/dev/null

for _ in $(seq 1 60); do
  if "$DOCKER_BIN" exec "$DB_CONTAINER" mariadb-admin ping -uroot -pps_emailsub_test --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

"$DOCKER_BIN" exec "$DB_CONTAINER" mariadb-admin ping -uroot -pps_emailsub_test --silent

COMMIT_SHA="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"

"$DOCKER_BIN" run --rm --network "container:$DB_CONTAINER" \
  -v "$ROOT:/work" \
  -v /tmp/ps-emailsub-apk:/apk-cache:ro \
  -v /tmp/phpunit-cache:/phpunit-cache:ro \
  -v /opt/cursor/artifacts:/opt/cursor/artifacts \
  -w /work/tests \
  -e DB_HOST=127.0.0.1 \
  -e DB_USER=root \
  -e DB_PASSWORD=ps_emailsub_test \
  -e DB_NAME=prestashop_test \
  -e COMMIT_SHA="$COMMIT_SHA" \
  -e RUN_MUTATIONS="${RUN_MUTATIONS:-0}" \
  --entrypoint /bin/sh \
  "$PHP_IMAGE" \
  /work/tests/run-tests-inside.sh

if [ "${RUN_MUTATIONS:-0}" = "1" ]; then
  git -C "$ROOT" diff --exit-code -- ps_emailsubscription.php
fi
