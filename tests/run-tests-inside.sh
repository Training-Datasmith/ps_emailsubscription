#!/bin/sh
set -eu

cd /work/tests

EXPECTED_TESTS=66

install_offline_apks() {
  if [ ! -d /apk-cache ] || ! ls /apk-cache/*.apk >/dev/null 2>&1; then
    echo "Offline APK cache is missing; network apk fallback is disabled" >&2
    return 1
  fi
  apk add --no-network /apk-cache/*.apk
  return 0
}

verify_phar() {
  cp /phpunit-cache/phpunit-7.5.20.phar /tmp/phpunit-7.5.20.phar
  cp /phpunit-cache/phpunit-7.5.20.phar.asc /tmp/phpunit-7.5.20.phar.asc
  export GNUPGHOME
  GNUPGHOME="$(mktemp -d)"
  gpg --import /phpunit-cache/sb.asc >/dev/null 2>&1
  FINGERPRINT="$(gpg --with-colons --fingerprint D8406D0D82947747293778314AA394086372C20A 2>/dev/null | awk -F: '/^fpr:/ {print $10; exit}')"
  test "$FINGERPRINT" = "D8406D0D82947747293778314AA394086372C20A"
  gpg --status-fd 3 --verify /tmp/phpunit-7.5.20.phar.asc /tmp/phpunit-7.5.20.phar 3>/tmp/gpg-status 2>/dev/null
  grep -q '\[GNUPG:\] VALIDSIG D8406D0D82947747293778314AA394086372C20A' /tmp/gpg-status
  echo '472b6e275e4479bab71f2f7744d2e22ace9566a4e3cef37952e4d9c07f6d3a92  /tmp/phpunit-7.5.20.phar' | sha256sum -c -
}

if ! install_offline_apks; then
  exit 1
fi
if ! php -m | grep -q pdo_mysql; then
  docker-php-ext-install pdo_mysql
fi
verify_phar

PHP_BIN="$(command -v php)"
export PHP_BINARY="$PHP_BIN"
test "$("$PHP_BIN" -r 'echo PHP_VERSION;')" = "7.1.33"
grep -q 'error_reporting(-1);' /work/tests/bootstrap.php
grep -q 'error_reporting(-1);' /work/tests/scripts/ajax_subscription_probe.php

RESULTS="/opt/cursor/artifacts/suite-results.md"
mkdir -p /opt/cursor/artifacts

run_gate() {
  label="$1"
  shift
  output="/tmp/phpunit-${label}.txt"
  set +e
  "$PHP_BIN" /tmp/phpunit-7.5.20.phar --configuration phpunit.xml "$@" >"$output" 2>&1
  code=$?
  set -e
  echo "$code" >"/tmp/phpunit-${label}.exit"
  count="$(grep -E '^OK \(' "$output" | tail -n 1 | sed -n 's/.* (\([0-9][0-9]*\) tests.*/\1/p')"
  if [ -z "$count" ]; then
    count="$(grep -E '^Tests:' "$output" | tail -n 1 | awk '{print $2}')"
  fi
  echo "${count:-0}" >"/tmp/phpunit-${label}.count"
  if [ "${count:-0}" != "$EXPECTED_TESTS" ]; then
    echo "Gate $label expected $EXPECTED_TESTS tests, got ${count:-unknown}" >&2
    code=1
  fi
  cat "$output"
  return "$code"
}

STATUS=0
GATES="default-1 default-2 random-1 random-2"
for gate in $GATES; do
  case "$gate" in
    default-1|default-2) args="" ;;
    random-1|random-2) args="--order-by=random --random-order-seed=20261008" ;;
  esac
  if ! run_gate "$gate" $args; then
    STATUS=1
  fi
done

MUTATION_STATUS=skipped
if [ "${RUN_MUTATIONS:-0}" = "1" ]; then
  if ! sh /work/tests/mutations.sh; then
    STATUS=1
    MUTATION_STATUS=failed
  else
    MUTATION_STATUS=passed
  fi
fi

{
  echo "# ps_emailsubscription suite results"
  echo ""
  echo "- repo: Training-Datasmith/ps_emailsubscription"
  echo "- date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "- branch: remote-test-2026-10-09"
  echo "- commit: ${COMMIT_SHA:-unknown}"
  echo "- php: 7.1.33"
  echo "- php image: php@sha256:2fbf149aa1e9ab4fda5e6a82261114543cc3c2419f46bb20d33cc01345ebd7c8"
  echo "- mariadb image: mariadb@sha256:3a7d3cbc8b6fddf66433d80dc124c1e4e75a73ebab9c6e137529cc270bdadfc0"
  echo "- command: tests/run-tests.sh"
  echo "- aggregated exit: $STATUS"
  echo "- expected tests per gate: $EXPECTED_TESTS"
  if [ -f /tmp/phpunit-default-1.txt ]; then
    summary="$(grep -E '^OK \(|^Tests:' /tmp/phpunit-default-1.txt | tail -n 1)"
    echo "- tests summary (per gate): ${summary:-see gates below}"
  fi
  echo "- mutations: ${MUTATION_STATUS} (RUN_MUTATIONS=${RUN_MUTATIONS:-0})"
  echo ""
  for gate in $GATES; do
    echo "## Gate ${gate}"
    echo "exit: $(cat /tmp/phpunit-${gate}.exit)"
    echo "tests: $(cat /tmp/phpunit-${gate}.count 2>/dev/null || echo unknown)"
    tail -n 5 "/tmp/phpunit-${gate}.txt" || true
    echo ""
  done
} >"$RESULTS"

find /work -maxdepth 1 -name '*export_*.csv' -delete || true

exit "$STATUS"
