<?php

class InstallUninstallTest extends DatabaseTestCase
{
    public function testInstallStopsWhenParentInstallFails()
    {
        $this->resetEnvironment();
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'emailsubscription');
        Module::$parentInstallResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame([], Module::$registeredHooks);
        $this->assertFalse((bool) Db::getInstance()->getValue('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'emailsubscription"'));
    }

    public function testFreshInstallSeedsConfigurationAndSchema()
    {
        $module = $this->newModule();
        $this->assertTrue($module->install());

        $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM ' . _DB_PREFIX_ . 'emailsubscription');
        $names = array_column($columns, 'Field');
        foreach (['id', 'id_shop', 'id_shop_group', 'email', 'newsletter_date_add', 'ip_registration_newsletter', 'http_referer', 'active', 'id_lang'] as $column) {
            $this->assertContains($column, $names);
        }

        foreach ($this->requiredInstallHooks() as $hook) {
            $this->assertContains($hook, Module::$registeredHooks);
        }

        $this->assertSame(16, strlen(Configuration::get('NW_SALT')));
        $this->assertSame(0, (int) Configuration::get('NW_VERIFICATION_EMAIL'));
        $this->assertSame(0, (int) Configuration::get('NW_CONFIRMATION_EMAIL'));
        $this->assertSame('', Configuration::get('NW_VOUCHER_CODE'));
        $this->assertNotEmpty(Configuration::get('PS_NEWSLETTER_RAND'));
        $this->assertSentinel(
            Configuration::get('NW_CONDITIONS')[1],
            'You may unsubscribe at any moment. For that purpose, please find our contact info in the legal notice.',
            'Modules.Emailsubscription.Shop'
        );
    }

    public function testInstallRenamesLegacyNewsletterTable()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'emailsubscription');
        Db::getInstance()->execute(
            'CREATE TABLE ' . _DB_PREFIX_ . 'newsletter (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'newsletter (email) VALUES ("legacy@example.com")'
        );
        Module::$installedModules[] = Ps_Emailsubscription::PS_16_EQUIVALENT_MODULE;

        $module = $this->newModule();
        $this->assertTrue($module->install());

        $email = Db::getInstance()->getValue(
            'SELECT email FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "legacy@example.com"'
        );
        $this->assertSame('legacy@example.com', $email);
    }

    public function testUninstallDropsTheSubscriptionTable()
    {
        $module = $this->newModule();
        $module->install();
        $this->assertTrue($module->uninstall());
        $this->assertFalse((bool) Db::getInstance()->getValue('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'emailsubscription"'));
    }
}
