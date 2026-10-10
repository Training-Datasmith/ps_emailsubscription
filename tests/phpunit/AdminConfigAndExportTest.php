<?php

class AdminConfigAndExportTest extends DatabaseTestCase
{
    public function testGetConfigFieldsValuesPrefersPostedValues()
    {
        $module = $this->newModule();
        $values = $module->getConfigFieldsValues();
        $this->assertSame(0, (int) $values['NW_VERIFICATION_EMAIL']);
        $this->assertSame('CONDITIONS_SENTINEL', $values['NW_CONDITIONS'][1]);
        $_POST['NW_VERIFICATION_EMAIL'] = '1';
        $values = $module->getConfigFieldsValues();
        $this->assertSame('1', $values['NW_VERIFICATION_EMAIL']);
    }

    public function testSubmitUpdateSavesAValidVoucher()
    {
        Validate::$discountResults['WELCOME10'] = true;
        $_POST = ['submitUpdate' => '1', 'NW_VOUCHER_CODE' => 'WELCOME10'];
        $module = $this->newModule();
        $module->getContent();
        $this->assertSame('WELCOME10', Configuration::get('NW_VOUCHER_CODE'));
        $this->assertContains('data-sentinel="confirmation"', $module->_html);
    }

    public function testSubmitUpdateKeepsPreviousVoucherWhenInvalid()
    {
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        Validate::$discountResults['no'] = false;
        $_POST = ['submitUpdate' => '1', 'NW_VOUCHER_CODE' => 'no'];
        $module = $this->newModule();
        $module->getContent();
        $this->assertSame('WELCOME10', Configuration::get('NW_VOUCHER_CODE'));
        $this->assertSentinel($module->_html, 'The voucher code is invalid.', 'Admin.Notifications.Error');
    }

    public function testSearchEmailIsStored()
    {
        $_POST = ['searchEmail' => '1', 'searched_email' => 'find@example.com'];
        $module = $this->newModule();
        $module->getContent();
        $this->assertSame('find@example.com', $module->_searched_email);
    }

    public function testMergedGuestDeactivationAndCustomerToggle()
    {
        $guestId = Db::getInstance()->getValue('SELECT MAX(id) FROM ' . _DB_PREFIX_ . 'emailsubscription');
        $this->insertGuest('guest@example.com', 1);
        $guestId = Db::getInstance()->getValue('SELECT id FROM ' . _DB_PREFIX_ . 'emailsubscription LIMIT 1');
        $customerId = $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 1]);

        $_POST = ['subscribedmerged' => '1', 'id' => 'N' . $guestId];
        $module = $this->newModule();
        try {
            $module->getContent();
            $this->fail('Expected redirect');
        } catch (RedirectAdminException $e) {
            $this->assertContains('configure=ps_emailsubscription', $e->getMessage());
            $this->assertContains('conf=4', $e->getMessage());
        }
        $this->assertSame('0', (string) Db::getInstance()->getValue('SELECT active FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE id = ' . (int) $guestId));

        $_POST = ['subscribedmerged' => '1', 'id' => (string) $customerId];
        $module = $this->newModule();
        try {
            $module->getContent();
        } catch (RedirectAdminException $e) {
            // expected
        }
        $this->assertSame('0', (string) Db::getInstance()->getValue('SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . (int) $customerId));
    }

    public function testRenderListDropsShopColumnWhenMultishopIsOff()
    {
        $this->insertCustomer(['email' => 'sub@example.com', 'newsletter' => 1]);
        $module = $this->newModule();
        $module->renderList();
        $this->assertArrayNotHasKey('shop_name', $module->_helperlist->lastFields);
        Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
        $module->renderList();
        $this->assertArrayHasKey('shop_name', $module->_helperlist->lastFields);
    }

    public function testDisplayLinksCarryTheCustomerId()
    {
        $module = $this->newModule();
        $module->_helperlist = new HelperList();
        $module->_helperlist->currentIndex = 'index';
        $module->_helperlist->identifier = 'id';
        $module->_helperlist->table = 'merged';
        $html = $module->displayViewCustomerLink('token', 0);
        $this->assertContains('list_action_viewcustomer.tpl', $html);
        $html = $module->displayViewCustomerLink('token', 5);
        $this->assertNotContains('disable', strtolower($html));
        $module->displayEnableLink('token', 7, 1, 'subscribed');
        $this->assertContains('token=token', $module->smarty->url_enable);
        $this->assertContains('=7', $module->smarty->url_enable);
        $module->displayUnsubscribeLink('token', 9);
        $this->assertContains('=9', $module->smarty->href);
    }

    public function testExportCustomersWritesInsideModule()
    {
        $this->insertCustomer(['email' => 'sub@example.com', 'newsletter' => 1, 'lastname' => 'ExportMe']);
        $this->insertGuest('guest@example.com', 1);
        $_POST = ['submitExport' => '1', 'action' => 'customers', 'SUSCRIBERS' => '1', 'OPTIN' => '0', 'COUNTRY' => '0'];
        $module = $this->newModule();
        $module->export_csv();
        $path = dirname(__DIR__, 2) . '/customers_export_' . Configuration::get('PS_NEWSLETTER_RAND') . '.csv';
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertContains('sub@example.com', $contents);
        $this->assertNotContains('guest@example.com', $contents);
        $this->assertContains('id;shop_name;gender;lastname;firstname;email;subscribed;subscribed_on;iso_language', $contents);
        $this->assertContains('data-sentinel="confirmation"', $module->_html);
    }

    public function testExportRejectsUnsupportedAction()
    {
        $this->insertCustomer(['email' => 'sub@example.com', 'newsletter' => 1]);
        $_POST = ['submitExport' => '1', 'action' => 'unsupported', 'SUSCRIBERS' => '1', 'OPTIN' => '0', 'COUNTRY' => '0'];
        $module = $this->newModule();
        $module->export_csv();
        $this->assertFileNotExists(dirname(__DIR__, 2) . '/unsupported_export_' . Configuration::get('PS_NEWSLETTER_RAND') . '.csv');
        $this->assertSentinel($module->_html, 'No result found!', 'Modules.Emailsubscription.Admin');
    }

    public function testExportWithNoRowsWritesNoFile()
    {
        $_POST = ['submitExport' => '1', 'action' => 'customers', 'SUSCRIBERS' => '1', 'OPTIN' => '0', 'COUNTRY' => '0'];
        $module = $this->newModule();
        $module->export_csv();
        $this->assertFileNotExists(dirname(__DIR__, 2) . '/customers_export_' . Configuration::get('PS_NEWSLETTER_RAND') . '.csv');
    }
}
