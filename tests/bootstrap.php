<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}
if (!defined('_PS_USE_SQL_SLAVE_')) {
    define('_PS_USE_SQL_SLAVE_', false);
}

require_once __DIR__ . '/support/PrestaShopWidgetInterface.php';
require_once __DIR__ . '/support/stubs.php';
require_once dirname(__DIR__) . '/ps_emailsubscription.php';
require_once __DIR__ . '/support/DatabaseTestCase.php';
require_once __DIR__ . '/support/TestableEmailsubscription.php';
require_once __DIR__ . '/../controllers/front/subscription.php';
require_once __DIR__ . '/../controllers/front/verification.php';
require_once dirname(__DIR__) . '/upgrade/install-1.1.0.php';
require_once dirname(__DIR__) . '/upgrade/upgrade-2.3.0.php';
require_once dirname(__DIR__) . '/upgrade/install-2.4.0.php';
require_once dirname(__DIR__) . '/upgrade/install-2.5.2.php';
require_once dirname(__DIR__) . '/upgrade/install-2.5.3.php';
