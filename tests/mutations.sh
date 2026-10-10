#!/bin/sh
set -eu

if [ "${RUN_MUTATIONS:-0}" != "1" ]; then
  echo "Skipping mutations (set RUN_MUTATIONS=1 to enable)"
  exit 0
fi

ROOT="/work"
MODULE="$ROOT/ps_emailsubscription.php"
PHP_BIN="${PHP_BINARY:-php}"
PHAR="/tmp/phpunit-7.5.20.phar"
export PHP_BINARY="$PHP_BIN"
MODULE_BACKUP=""

restore_module() {
  if [ -n "$MODULE_BACKUP" ] && [ -f "$MODULE_BACKUP" ]; then
    cp -p "$MODULE_BACKUP" "$MODULE"
    rm -f "$MODULE_BACKUP"
    MODULE_BACKUP=""
  fi
}

trap restore_module EXIT INT TERM

begin_mutation() {
  restore_module
  MODULE_BACKUP="$(mktemp)"
  cp -p "$MODULE" "$MODULE_BACKUP"
}

end_mutation() {
  restore_module
}

run_mutation_filter() {
  label="$1"
  filter="$2"
  set +e
  "$PHP_BIN" "$PHAR" --configuration /work/tests/phpunit.xml --filter "$filter" >/tmp/mutation-"$label".log 2>&1
  code=$?
  set -e
  if [ "$code" -eq 0 ]; then
    echo "Mutation $label did not fail" >&2
    return 1
  fi
  return 0
}

mutate_guest_unregister() {
  begin_mutation
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
  run_mutation_filter guest testUnregisterUsesEmailArgumentNotPostForGuest
  end_mutation
}

mutate_customer_unregister() {
  begin_mutation
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
  run_mutation_filter customer testUnregisterUsesEmailArgumentNotPostForCustomer
  end_mutation
}

mutate_export_allow() {
  begin_mutation
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
  run_mutation_filter export-allow testExportRejectsUnsupportedAction
  end_mutation
}

mutate_export_path() {
  begin_mutation
  python3 - <<'PY'
from pathlib import Path
path = Path("/work/ps_emailsubscription.php")
text = path.read_text()
guard = """        if (Tools::getValue('action') !== 'customers') {
            $this->_html .= $this->displayError($this->trans('No result found!', [], 'Modules.Emailsubscription.Admin'));

            return;
        }

"""
if guard not in text:
    raise SystemExit('export customers guard missing')
text = text.replace(guard, '', 1)
replacements = [
(
"dirname(__FILE__) . '/customers_' . $this->file, 'w'",
"dirname(__FILE__) . '/' . strval(preg_replace('#\\.{2,}#', '.', Tools::getValue('action'))) . '_' . $this->file, 'w'",
),
(
"modules/ps_emailsubscription/customers_' . $this->file",
"modules/ps_emailsubscription/' . Tools::safeOutput(strval(Tools::getValue('action'))) . '_' . $this->file",
),
(
"dirname(__FILE__) . '/customers_' . $this->file . ' !'",
"dirname(__FILE__) . '/' . strval(Tools::getValue('action')) . '_' . $this->file . ' !'",
),
]
for old, new in replacements:
    if old not in text:
        raise SystemExit('export path target missing: ' + old)
    text = text.replace(old, new, 1)
path.write_text(text)
PY
  run_mutation_filter export-path testExportRejectsUnsupportedAction
  end_mutation
}

mutate_paginate() {
  begin_mutation
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
  run_mutation_filter paginate testPaginateSubscribersSlicesOnlyWhenLongerThanThePage
  end_mutation
}

mutate_guest_unregister
mutate_customer_unregister
mutate_export_allow
mutate_export_path
mutate_paginate
trap - EXIT INT TERM
restore_module
echo "All mutation checks failed as expected"
