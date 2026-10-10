<?php

class UpgradeTest extends DatabaseTestCase
{
    public function testUpgrade110RemovesLegacyHookAndOptIn()
    {
        $module = $this->newModule();
        Configuration::updateValue('NW_CONFIRMATION_OPTIN', '1');
        $this->assertTrue(upgrade_module_1_1_0($module));
        $this->assertContains(5, Module::$unregisteredHookIds);
        $this->assertNull(Configuration::get('NW_CONFIRMATION_OPTIN'));

        Configuration::updateValue('NW_CONFIRMATION_OPTIN', '1');
        $this->assertTrue(upgrade_module_1_1_0($module));
        $this->assertNull(Configuration::get('NW_CONFIRMATION_OPTIN'));
    }

    public function testUpgrade230RegistersGdprHooks()
    {
        $module = $this->newModule();
        $this->assertTrue(upgrade_module_2_3_0($module));
        foreach (['registerGDPRConsent', 'actionDeleteGDPRCustomer', 'actionExportGDPRData'] as $hook) {
            $this->assertContains($hook, Module::$registeredHooks);
        }
    }

    public function testUpgrade240AddsLanguageColumn()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'emailsubscription');
        Db::getInstance()->execute(
            'CREATE TABLE ' . _DB_PREFIX_ . 'emailsubscription (
                id INT AUTO_INCREMENT PRIMARY KEY,
                active TINYINT(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
        $this->assertTrue(upgrade_module_2_4_0($this->newModule()));
        $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM ' . _DB_PREFIX_ . 'emailsubscription');
        $this->assertContains('id_lang', array_column($columns, 'Field'));
    }

    public function testUpgrade252And253RegisterAccountHooks()
    {
        $module = $this->newModule();
        $this->assertTrue(upgrade_module_2_5_2($module));
        $this->assertContains('actionFrontControllerSetMedia', Module::$registeredHooks);
        $this->assertTrue(upgrade_module_2_5_3($module));
        $this->assertContains('actionCustomerAccountUpdate', Module::$registeredHooks);
        $this->assertContains('actionObjectCustomerUpdateBefore', Module::$registeredHooks);
    }
}
