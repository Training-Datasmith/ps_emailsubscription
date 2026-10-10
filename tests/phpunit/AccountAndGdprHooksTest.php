<?php

class AccountAndGdprHooksTest extends DatabaseTestCase
{
    public function testAccountAddWithoutNewCustomerReturnsFalse()
    {
        $module = $this->newModule();
        $this->assertFalse($module->hookActionCustomerAccountAdd([]));
    }

    public function testAccountAddKeepsGuestRowWhenNewsletterIsOff()
    {
        $this->insertGuest('guest@example.com', 1);
        $customer = new Customer();
        $customer->id_shop = 1;
        $customer->email = 'guest@example.com';
        $customer->newsletter = false;
        $module = $this->newModule();
        $module->hookActionCustomerAccountAdd(['newCustomer' => $customer]);
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
        $this->assertSame([], Mail::$sent);
    }

    public function testAccountAddDeletesGuestRowWhenNewsletterIsOn()
    {
        $this->insertGuest('guest@example.com', 1);
        $this->insertGuest('guest@example.com', 1, 2);
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        $customer = new Customer();
        $customer->id_shop = 1;
        $customer->email = 'guest@example.com';
        $customer->newsletter = true;
        $module = $this->newModule();
        $module->hookActionCustomerAccountAdd(['newCustomer' => $customer]);
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE id_shop = 1'));
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE id_shop = 2'));
        $this->assertCount(1, Mail::$sent);
        $this->assertSame('newsletter_voucher', Mail::$sent[0]['template']);
    }

    public function testCustomerUpdateBeforeStoresOriginalFlag()
    {
        $id = $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 0]);
        $module = $this->newModule();
        $object = new Customer($id);
        $object->id_shop = 1;
        $object->email = 'customer@example.com';
        $object->newsletter = 1;
        $module->hookActionObjectCustomerUpdateBefore(['object' => $object]);
        $this->assertSame(0, $module->readOriginNewsletter());
    }

    public function testAccountUpdateSendsOnlyOnNewSubscription()
    {
        $module = $this->newModule();
        $module->setOriginNewsletter(1);
        $customer = new Customer();
        $customer->newsletter = 1;
        $module->hookActionCustomerAccountUpdate(['customer' => $customer]);
        $this->assertSame([], Mail::$sent);

        $module->setOriginNewsletter(0);
        $customer->newsletter = 0;
        $module->hookActionCustomerAccountUpdate(['customer' => $customer]);
        $this->assertSame([], Mail::$sent);

        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 1);
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        CartRuleCore::$rulesByCode['WELCOME10'] = [['id_cart_rule' => 9]];
        Order::$discountsCustomer['5-' . json_encode(['id_cart_rule' => 9])] = 0;
        $customer->newsletter = 1;
        $customer->id = 5;
        $customer->email = 'customer@example.com';
        $module->hookActionCustomerAccountUpdate(['customer' => $customer]);
        $templates = array_column(Mail::$sent, 'template');
        $this->assertContains('newsletter_conf', $templates);
        $this->assertContains('newsletter_voucher', $templates);
    }

    public function testAdditionalCustomerFormFieldUsesConditions()
    {
        $module = $this->newModule();
        $fields = $module->hookAdditionalCustomerFormFields([]);
        $this->assertCount(1, $fields);
        $field = $fields[0]->toArray();
        $this->assertSame('newsletter', $field['name']);
        $this->assertSame('checkbox', $field['type']);
        $this->assertContains('%conditions%', $field['label']);
    }

    public function testAdminCustomerFormReadsNewsletterFlag()
    {
        $id = $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 1]);
        $_GET['id_customer'] = $id;
        $module = $this->newModule();
        $module->hookDisplayAdminCustomersForm();
        $this->assertSame('newsletter', $module->context->smarty->input['name']);
        $this->assertSame('1', (string) $module->context->smarty->input['value']);
    }

    public function testGdprDeleteRemovesExistingEmail()
    {
        $this->insertGuest('guest@example.com', 1);
        $module = $this->newModule();
        $result = $module->hookActionDeleteGDPRCustomer(['email' => 'guest@example.com']);
        $this->assertSame(json_encode(true), $result);
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testGdprDeleteInvalidEmailDoesNotRemoveRows()
    {
        $this->insertGuest('guest@example.com', 1);
        Validate::$emailResults['bad'] = false;
        $module = $this->newModule();
        $module->hookActionDeleteGDPRCustomer(['email' => 'bad']);
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testGdprExportReturnsOnlyThatEmail()
    {
        $this->insertGuest('guest@example.com', 1);
        $this->insertGuest('other@example.com', 1);
        $module = $this->newModule();
        $json = $module->hookActionExportGDPRData(['email' => 'guest@example.com']);
        $this->assertContains('guest@example.com', $json);
        $this->assertNotContains('other@example.com', $json);
    }

    public function testGdprExportInvalidEmailDoesNotLeakData()
    {
        $this->insertGuest('guest@example.com', 1);
        Validate::$emailResults['bad'] = false;
        $module = $this->newModule();
        $module->hookActionExportGDPRData(['email' => 'bad']);
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }
}
