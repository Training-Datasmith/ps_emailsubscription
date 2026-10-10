<?php

require_once __DIR__ . '/Translation.php';
require_once __DIR__ . '/PrestaShopWidgetInterface.php';

class SmartyStub
{
    public function assign($templateVars, $value = null)
    {
        if (is_array($templateVars)) {
            foreach ($templateVars as $key => $assigned) {
                $this->$key = $assigned;
            }

            return;
        }

        $this->$templateVars = $value;
    }
}

class Module
{
    public $name;
    public $tab;
    public $need_instance;
    public $controllers = [];
    public $bootstrap;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $ps_versions_compliancy = [];
    public $version;
    public $author;
    public $table = 'module';
    public $identifier = 'id_module';
    public $id = 42;
    public $smarty;
    public $context;

    public static $parentInstallResult = true;
    public static $installedModules = [];
    public static $registeredHooks = [];
    public static $unregisteredHookIds = [];
    public static $lastFetchTemplate;
    public static $lastDisplayTemplate;

    public function __construct()
    {
        $this->smarty = new SmartyStub();
        $this->context = Context::getContext();
    }

    public function install()
    {
        return self::$parentInstallResult;
    }

    public function uninstall()
    {
        return true;
    }

    public function registerHook($hooks)
    {
        if (!is_array($hooks)) {
            $hooks = [$hooks];
        }
        foreach ($hooks as $hook) {
            self::$registeredHooks[] = $hook;
        }

        return true;
    }

    public function unregisterHook($hookId)
    {
        self::$unregisteredHookIds[] = (int) $hookId;

        return true;
    }

    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return Translation::sentinel($id, $domain === null ? '' : $domain, $locale);
    }

    public function display($file, $template, $cache_id = null, $compile_id = null)
    {
        self::$lastDisplayTemplate = $template;

        return '<display:' . $template . '>';
    }

    public function fetch($template)
    {
        self::$lastFetchTemplate = $template;

        return '<fetch:' . $template . '>';
    }

    public function displayError($message)
    {
        return '<div class="module-error" data-sentinel="error">' . $message . '</div>';
    }

    public function displayConfirmation($message)
    {
        return '<div class="module-confirmation" data-sentinel="confirmation">' . $message . '</div>';
    }

    public static function isInstalled($name)
    {
        return in_array($name, self::$installedModules, true);
    }

    public static function getInstanceByName($name)
    {
        if (!in_array($name, self::$installedModules, true)) {
            return false;
        }

        return new LegacyBlockNewsletterModule();
    }

    public static function resetStatics()
    {
        self::$parentInstallResult = true;
        self::$installedModules = [];
        self::$registeredHooks = [];
        self::$unregisteredHookIds = [];
        self::$lastFetchTemplate = null;
        self::$lastDisplayTemplate = null;
    }
}

class LegacyBlockNewsletterModule extends Module
{
    public function uninstall()
    {
        return false;
    }
}

class Db
{
    private static $instance;
    private $pdo;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getInstance($master = true)
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $dbname = getenv('DB_NAME') ?: 'prestashop_test';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: 'ps_emailsub_test';
            $dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8';
            self::$instance = new self(new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]));
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
    }

    public function getLink()
    {
        return $this->pdo;
    }

    public function execute($sql)
    {
        return $this->pdo->exec($sql) !== false;
    }

    public function getRow($sql)
    {
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: false;
    }

    public function getValue($sql)
    {
        $stmt = $this->pdo->query($sql);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public function executeS($sql)
    {
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }
}

class DbQuery
{
    private $select = [];
    private $from = [];
    private $joins = [];
    private $where = [];

    public function select($clause)
    {
        $this->select[] = $clause;

        return $this;
    }

    public function from($table, $alias = null)
    {
        $this->from[] = '`' . _DB_PREFIX_ . $table . '`' . ($alias ? ' ' . $alias : '');

        return $this;
    }

    public function leftJoin($table, $alias, $on)
    {
        $this->joins[] = 'LEFT JOIN `' . _DB_PREFIX_ . $table . '` ' . $alias . ' ON ' . $on;

        return $this;
    }

    public function where($clause)
    {
        $this->where[] = $clause;

        return $this;
    }

    public function build()
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . implode(', ', $this->from);
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->where) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }
}

function pSQL($value, $htmlOK = false)
{
    $quoted = Db::getInstance()->getLink()->quote((string) $value, PDO::PARAM_STR);

    return substr($quoted, 1, -1);
}

class Configuration
{
    private static $values = [];

    public static function reset()
    {
        self::$values = [];
    }

    public static function updateValue($key, $value, $html = false)
    {
        self::$values[$key] = $value;
    }

    public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null)
    {
        if ($idLang !== null && is_array(self::$values[$key] ?? null)) {
            return self::$values[$key][$idLang] ?? null;
        }

        return self::$values[$key] ?? null;
    }

    public static function deleteByName($key)
    {
        unset(self::$values[$key]);

        return true;
    }
}

class Shop
{
    public $id = 1;
    public $id_shop_group = 1;

    public static $featureActive = false;
    public static $contextShopIds = [1];

    public static function isFeatureActive()
    {
        return self::$featureActive;
    }

    public static function getContextListShopID()
    {
        return self::$contextShopIds;
    }

    public function getBaseURI()
    {
        return '/';
    }

    public static function resetStatics()
    {
        self::$featureActive = false;
        self::$contextShopIds = [1];
    }
}

class Language
{
    public $id;
    public $locale;

    public function __construct($id)
    {
        $this->id = (int) $id;
        $this->locale = 'en-US';
    }

    public static function getLanguages($active = true)
    {
        return [
            ['id_lang' => 1, 'locale' => 'en-US'],
        ];
    }
}

class Context
{
    public $shop;
    public $language;
    public $employee;
    public $customer;
    public $cookie;
    public $link;
    public $controller;
    public $country;
    public $smarty;

    private static $instance;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->shop = new Shop();
            self::$instance->language = new Language(1);
            self::$instance->employee = (object) ['id_lang' => 1];
            self::$instance->customer = new Customer();
            self::$instance->cookie = new stdClass();
            self::$instance->cookie->shopContext = null;
            self::$instance->link = new Link();
            self::$instance->controller = new AdminModulesControllerStub();
            self::$instance->country = (object) ['id' => 1];
            self::$instance->smarty = new SmartyStub();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }
}

class Link
{
    public $lastModuleLink = [];

    public function getAdminLink($controller, $withToken = true, array $sfParams = [], array $params = [])
    {
        $query = http_build_query(array_merge($params, $sfParams));

        return 'admin:' . $controller . '?' . $query;
    }

    public function getModuleLink($module, $controller = 'default', array $params = [], $ssl = null)
    {
        $this->lastModuleLink = compact('module', 'controller', 'params', 'ssl');

        return 'module:' . $module . '/' . $controller . '?' . http_build_query($params);
    }
}

class AdminModulesControllerStub
{
    public function getLanguages()
    {
        return Language::getLanguages(false);
    }

    public function registerJavascript($id, $path)
    {
        Media::$registered[] = ['id' => $id, 'path' => $path];
    }
}

class Tools
{
    public static function getValue($key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function getIsset($key)
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function isSubmit($key)
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function getAdminTokenLite($tab)
    {
        return 'token-' . $tab;
    }

    public static function passwdGen($length)
    {
        return str_repeat('a', (int) $length);
    }

    public static function getRemoteAddr()
    {
        return '127.0.0.1';
    }

    public static function redirectAdmin($url)
    {
        throw new RedirectAdminException($url);
    }

    public static function htmlentitiesUTF8($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    public static function safeOutput($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    public static function substr($string, $start, $length = null)
    {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }

    public static function isEmpty($value)
    {
        return $value === '' || $value === null;
    }
}

class RedirectAdminException extends Exception
{
}

class Validate
{
    public static $emailResults = [];
    public static $discountResults = [];

    public static function reset()
    {
        self::$emailResults = [];
        self::$discountResults = [];
    }

    public static function isEmail($email)
    {
        if (array_key_exists($email, self::$emailResults)) {
            return self::$emailResults[$email];
        }

        return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email);
    }

    public static function isDiscountName($voucher)
    {
        if (array_key_exists($voucher, self::$discountResults)) {
            return self::$discountResults[$voucher];
        }

        return (bool) preg_match('/^[^!<>,;?=+()@"°{}_$%:]{3,32}$/u', $voucher);
    }
}

class Hook
{
    public static $executions = [];
    public static $beforeCallbacks = [];

    public static function reset()
    {
        self::$executions = [];
        self::$beforeCallbacks = [];
    }

    public static function exec($name, array $params = [])
    {
        if ($name === 'actionNewsletterRegistrationBefore' && isset(self::$beforeCallbacks['hookError']) && array_key_exists('hookError', $params)) {
            $params['hookError'] = self::$beforeCallbacks['hookError'];
        }
        self::$executions[] = ['name' => $name, 'params' => $params];
    }

    public static function getIdByName($name)
    {
        return $name === 'displayAfterBodyOpeningTag' ? 5 : 0;
    }
}

class Mail
{
    public static $sent = [];

    public static function reset()
    {
        self::$sent = [];
    }

    public static function send(
        $idLang,
        $template,
        $subject,
        $vars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $mode_smtp = null,
        $templatePath = null,
        $die = false,
        $idShop = null
    ) {
        self::$sent[] = compact(
            'idLang',
            'template',
            'subject',
            'vars',
            'to',
            'templatePath',
            'idShop'
        );

        return true;
    }
}

class Media
{
    public static $jsDef = [];
    public static $registered = [];

    public static function reset()
    {
        self::$jsDef = [];
        self::$registered = [];
    }

    public static function addJsDef(array $def)
    {
        self::$jsDef = array_merge(self::$jsDef, $def);
    }
}

class FormField
{
    private $data = [];

    public function setName($name)
    {
        $this->data['name'] = $name;

        return $this;
    }

    public function setType($type)
    {
        $this->data['type'] = $type;

        return $this;
    }

    public function setLabel($label)
    {
        $this->data['label'] = $label;

        return $this;
    }

    public function toArray()
    {
        return $this->data;
    }
}

class HelperList
{
    public $module;
    public $title;
    public $shopLinkType;
    public $no_link;
    public $show_toolbar;
    public $simple_header;
    public $identifier;
    public $table;
    public $currentIndex;
    public $token;
    public $actions = [];
    public $listTotal;
    public $lastFields;
    public $lastRows;

    public function generateList($rows, $fields)
    {
        $this->lastFields = $fields;
        $this->lastRows = $rows;

        return '<helper-list/>';
    }
}

class HelperForm
{
    public $show_toolbar;
    public $table;
    public $default_form_language;
    public $allow_employee_form_lang;
    public $identifier;
    public $submit_action;
    public $currentIndex;
    public $token;
    public $tpl_vars = [];

    public function generateForm(array $forms)
    {
        return '<helper-form/>';
    }
}

class Country
{
    public static function getCountries($idLang)
    {
        return [
            ['id_country' => 1, 'name' => 'CountryOne'],
        ];
    }
}

class Customer
{
    public $id;
    public $id_shop = 1;
    public $id_shop_group = 1;
    public $email;
    public $newsletter = 0;
    public $newsletter_date_add;
    public $date_add;
    public $firstname = 'First';
    public $lastname = 'Last';
    public $id_lang = 1;
    public $id_gender = 1;

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = (int) $id;
            $this->load();
        }
    }

    public function load()
    {
        if (!$this->id) {
            return;
        }
        $row = Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . (int) $this->id
        );
        if ($row) {
            foreach ($row as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function update()
    {
        Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'customer SET newsletter = ' . (int) $this->newsletter .
            ' WHERE id_customer = ' . (int) $this->id
        );

        return true;
    }
}

class CartRuleCore
{
    public static $rulesByCode = [];

    public static function reset()
    {
        self::$rulesByCode = [];
    }

    public static function getCartsRuleByCode($name, $id_lang, $extended = false)
    {
        return self::$rulesByCode[$name] ?? [];
    }
}

class Order
{
    public static $discountsCustomer = [];

    public static function reset()
    {
        self::$discountsCustomer = [];
    }

    public static function getDiscountsCustomer($id_customer, $id_cart_rule)
    {
        $key = (int) $id_customer . '-' . json_encode($id_cart_rule);

        return self::$discountsCustomer[$key] ?? 0;
    }
}

class ModuleFrontController
{
    public $module;
    public $context;
    public $ajax = false;
    public $template;

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function initContent()
    {
    }

    public function ajaxRender($payload)
    {
        echo $payload;
        exit;
    }
}
