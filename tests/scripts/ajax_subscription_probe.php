<?php

putenv('DB_HOST=' . (getenv('DB_HOST') ?: '127.0.0.1'));
putenv('DB_NAME=' . (getenv('DB_NAME') ?: 'prestashop_test'));
putenv('DB_USER=' . (getenv('DB_USER') ?: 'root'));
putenv('DB_PASSWORD=' . (getenv('DB_PASSWORD') ?: 'ps_emailsub_test'));

error_reporting(E_ALL);
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

require dirname(__DIR__) . '/support/PrestaShopWidgetInterface.php';
require dirname(__DIR__) . '/support/Translation.php';
require dirname(__DIR__) . '/support/stubs.php';
require dirname(__DIR__, 2) . '/ps_emailsubscription.php';
require dirname(__DIR__, 2) . '/controllers/front/subscription.php';
require dirname(__DIR__) . '/support/TestableEmailsubscription.php';

echo "AJAX_BEFORE\n";

Validate::$emailResults['bad@example.com'] = false;
$_POST = ['submitNewsletter' => '1', 'email' => 'bad@example.com', 'action' => '0'];

$module = new TestableEmailsubscription();
$controller = new Ps_EmailsubscriptionSubscriptionModuleFrontController();
$controller->module = $module;
$controller->ajax = true;
$controller->postProcess();
echo "AJAX_UNEXPECTED\n";
