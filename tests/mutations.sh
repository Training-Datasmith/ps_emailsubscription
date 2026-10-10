#!/bin/sh
set -eu

ROOT="/work"
MODULE="$ROOT/ps_emailsubscription.php"
PHP_BIN="${PHP_BINARY:-php}"
PHAR="/tmp/phpunit-7.5.20.phar"
export PHP_BINARY="$PHP_BIN"

run_mutation() {
  label="$1"
  python_script="$2"
  filter="$3"
  cp "$MODULE" "$MODULE.bak"
  python3 - "$MODULE" <<'PY'
import sys
path = sys.argv[1]
code = open(path).read()
mutation = sys.stdin.read()
if mutation not in code:
    raise SystemExit('mutation marker missing')
open(path, 'w').write(code.replace(mutation, sys.argv[2], 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter "$filter" >/tmp/mutation-"$label".log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  if [ "$code" -eq 0 ]; then
    echo "Mutation $label did not fail"
    return 1
  fi
  return 0
}

mutate_guest_unregister() {
  cp "$MODULE" "$MODULE.bak"
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
old = "            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE `email` = \\'' . pSQL($email) . '\\' AND id_shop = ' . $this->context->shop->id;"
new = "            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE `email` = \\'' . pSQL($_POST['email']) . '\\' AND id_shop = ' . $this->context->shop->id;"
if old not in text:
    raise SystemExit('guest unregister target missing')
path.write_text(text.replace(old, new, 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter testUnregisterUsesEmailArgumentNotPostForGuest >/tmp/mutation-guest.log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  test "$code" -ne 0
}

mutate_customer_unregister() {
  cp "$MODULE" "$MODULE.bak"
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
old = "            $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer SET `newsletter` = 0 WHERE `email` = \\'' . pSQL($email) . '\\' AND id_shop = ' . $this->context->shop->id;"
new = "            $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer SET `newsletter` = 0 WHERE `email` = \\'' . pSQL($_POST['email']) . '\\' AND id_shop = ' . $this->context->shop->id;"
if old not in text:
    raise SystemExit('customer unregister target missing')
path.write_text(text.replace(old, new, 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter testUnregisterUsesEmailArgumentNotPostForCustomer >/tmp/mutation-customer.log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  test "$code" -ne 0
}

mutate_export_allow() {
  cp "$MODULE" "$MODULE.bak"
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
old = "if (Tools::getValue('action') !== 'customers') {"
new = "if (false) {"
if old not in text:
    raise SystemExit('export allow-list missing')
path.write_text(text.replace(old, new, 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter testExportRejectsUnsupportedAction >/tmp/mutation-export-allow.log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  test "$code" -ne 0
}

mutate_export_path() {
  cp "$MODULE" "$MODULE.bak"
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
old = "dirname(__FILE__) . '/customers_' . $this->file"
new = "dirname(__FILE__) . '/export_' . $this->file"
if old not in text:
    raise SystemExit('export path literal missing')
path.write_text(text.replace(old, new, 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter testExportCustomersWritesInsideModule >/tmp/mutation-export-path.log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  test "$code" -ne 0
}

mutate_paginate() {
  cp "$MODULE" "$MODULE.bak"
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
old = '$pagination * ($page - 1)'
new = '$pagination * $page'
if old not in text:
    raise SystemExit('paginate offset missing')
path.write_text(text.replace(old, new, 1))
PY
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter testPaginateSubscribersSlicesOnlyWhenLongerThanThePage >/tmp/mutation-paginate.log 2>&1
  code=$?
  set -e
  mv "$MODULE.bak" "$MODULE"
  test "$code" -ne 0
}

mutate_guest_unregister
mutate_customer_unregister
mutate_export_allow
mutate_export_path
mutate_paginate
echo "All mutation checks failed as expected"
