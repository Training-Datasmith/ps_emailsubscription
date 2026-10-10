<?php

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->resetEnvironment();
        $this->createSchema();
        $this->seedBaseData();
    }

    protected function tearDown()
    {
        $this->removeExportFiles();
        $this->truncateTables();
        $this->resetEnvironment();
        parent::tearDown();
    }

    protected function resetEnvironment()
    {
        $_GET = [];
        $_POST = [];
        Db::resetInstance();
        Configuration::reset();
        Context::reset();
        Module::resetStatics();
        Hook::reset();
        Mail::reset();
        Media::reset();
        Validate::reset();
        CartRuleCore::reset();
        Order::reset();
        Shop::resetStatics();
    }

    protected function createSchema()
    {
        $pdo = Db::getInstance()->getLink();
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'emailsubscription');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'customer');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'shop');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'gender');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'gender_lang');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lang');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'connections');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'address');
        $pdo->exec('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'newsletter');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'shop (
            id_shop INT PRIMARY KEY,
            id_shop_group INT NOT NULL,
            name VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'gender (
            id_gender INT PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'gender_lang (
            id_gender INT NOT NULL,
            id_lang INT NOT NULL,
            name VARCHAR(20) NOT NULL,
            PRIMARY KEY (id_gender, id_lang)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'lang (
            id_lang INT PRIMARY KEY,
            iso_code VARCHAR(2) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'customer (
            id_customer INT AUTO_INCREMENT PRIMARY KEY,
            id_shop INT NOT NULL,
            id_shop_group INT NOT NULL DEFAULT 1,
            id_gender INT NOT NULL DEFAULT 1,
            firstname VARCHAR(255) NOT NULL,
            lastname VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            newsletter TINYINT(1) NOT NULL DEFAULT 0,
            newsletter_date_add DATETIME NULL,
            ip_registration_newsletter VARCHAR(15) NULL,
            date_add DATETIME NOT NULL,
            id_lang INT NOT NULL DEFAULT 1,
            optin TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'emailsubscription (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_shop INT UNSIGNED NOT NULL DEFAULT 1,
            id_shop_group INT UNSIGNED NOT NULL DEFAULT 1,
            email VARCHAR(255) NOT NULL,
            newsletter_date_add DATETIME NULL,
            ip_registration_newsletter VARCHAR(15) NOT NULL,
            http_referer VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 0,
            id_lang INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'connections (
            id_guest INT NOT NULL,
            http_referer VARCHAR(255) NULL,
            date_add DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $pdo->exec('CREATE TABLE ' . _DB_PREFIX_ . 'address (
            id_address INT AUTO_INCREMENT PRIMARY KEY,
            id_customer INT NOT NULL,
            id_country INT NOT NULL,
            deleted TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    }

    protected function seedBaseData()
    {
        $pdo = Db::getInstance()->getLink();
        $pdo->exec('INSERT INTO ' . _DB_PREFIX_ . 'shop (id_shop, id_shop_group, name) VALUES (1, 1, "ShopOne"), (2, 1, "ShopTwo")');
        $pdo->exec('INSERT INTO ' . _DB_PREFIX_ . 'gender (id_gender) VALUES (1)');
        $pdo->exec('INSERT INTO ' . _DB_PREFIX_ . 'gender_lang (id_gender, id_lang, name) VALUES (1, 1, "Mr")');
        $pdo->exec('INSERT INTO ' . _DB_PREFIX_ . 'lang (id_lang, iso_code) VALUES (1, "en")');

        Configuration::updateValue('NW_SALT', Tools::passwdGen(16));
        Configuration::updateValue('NW_VERIFICATION_EMAIL', 0);
        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 0);
        Configuration::updateValue('NW_VOUCHER_CODE', '');
        Configuration::updateValue('NW_CONDITIONS', [1 => 'CONDITIONS_SENTINEL']);
        Configuration::updateValue('PS_NEWSLETTER_RAND', '12345');
        Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 0);
        Configuration::updateValue('PS_LANG_DEFAULT', 1);
    }

    protected function truncateTables()
    {
        $pdo = Db::getInstance()->getLink();
        foreach (['address', 'connections', 'emailsubscription', 'customer'] as $table) {
            $exists = $pdo->query(
                'SHOW TABLES LIKE ' . $pdo->quote(_DB_PREFIX_ . $table)
            )->fetchColumn();
            if ($exists) {
                $pdo->exec('DELETE FROM ' . _DB_PREFIX_ . $table);
            }
        }
    }

    protected function removeExportFiles()
    {
        $dir = dirname(__DIR__, 2);
        foreach (glob($dir . '/*export_*.csv') as $file) {
            @unlink($file);
        }
        foreach (glob($dir . '/customers_export_*.csv') as $file) {
            @unlink($file);
        }
        foreach (glob($dir . '/unsupported_export_*.csv') as $file) {
            @unlink($file);
        }
    }

    protected function newModule()
    {
        return new TestableEmailsubscription();
    }

    protected function insertGuest($email, $active = 1, $shopId = 1, $date = '2020-01-01 00:00:00')
    {
        $quotedEmail = Db::getInstance()->getLink()->quote($email);
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'emailsubscription
            (id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, active, id_lang)
            VALUES (' . (int) $shopId . ', 1, ' . $quotedEmail . ', "' . $date . '", "127.0.0.1", ' . (int) $active . ', 1)'
        );
    }

    protected function insertCustomer(array $overrides = [])
    {
        $defaults = [
            'id_shop' => 1,
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'customer@example.com',
            'newsletter' => 0,
            'newsletter_date_add' => null,
            'date_add' => '2020-01-01 00:00:00',
            'id_lang' => 1,
            'optin' => 0,
        ];
        $data = array_merge($defaults, $overrides);
        $pdo = Db::getInstance()->getLink();
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'customer
            (id_shop, id_shop_group, id_gender, firstname, lastname, email, newsletter, newsletter_date_add, date_add, id_lang, optin)
            VALUES (' . (int) $data['id_shop'] . ', 1, 1, ' . $pdo->quote($data['firstname']) . ', ' . $pdo->quote($data['lastname']) . ', ' .
            $pdo->quote($data['email']) . ', ' . (int) $data['newsletter'] . ', ' .
            ($data['newsletter_date_add'] ? '"' . $data['newsletter_date_add'] . '"' : 'NULL') . ', "' . $data['date_add'] . '", ' .
            (int) $data['id_lang'] . ', ' . (int) $data['optin'] . ')'
        );

        return (int) Db::getInstance()->getValue('SELECT MAX(id_customer) FROM ' . _DB_PREFIX_ . 'customer');
    }

    protected function assertSentinel($haystack, $message, $domain)
    {
        $this->assertTrue(
            Translation::containsSentinel($haystack, $message, $domain),
            'Expected translation sentinel for ' . $domain . ':' . $message
        );
    }

    protected function requiredInstallHooks()
    {
        return [
            'actionFrontControllerSetMedia',
            'displayFooterBefore',
            'actionCustomerAccountAdd',
            'actionObjectCustomerUpdateBefore',
            'additionalCustomerFormFields',
            'displayAdminCustomersForm',
            'registerGDPRConsent',
            'actionDeleteGDPRCustomer',
            'actionExportGDPRData',
            'actionCustomerAccountUpdate',
        ];
    }
}
