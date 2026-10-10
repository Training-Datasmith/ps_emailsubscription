<?php

class ConfirmEmailTest extends DatabaseTestCase
{
    public function testConfirmEmailActivatesGuestToken()
    {
        $this->insertGuest('guest@example.com', 0);
        $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $token = md5('guest@example.com' . $row['newsletter_date_add'] . Configuration::get('NW_SALT'));
        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 1);
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        $module = $this->newModule();
        $message = $module->confirmEmail($token);
        $this->assertSentinel($message, 'Thank you for subscribing to our newsletter.', 'Modules.Emailsubscription.Shop');
        $active = Db::getInstance()->getValue('SELECT active FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $this->assertSame('1', (string) $active);
        $this->assertNotEmpty(array_filter(Hook::$executions, function ($item) {
            return $item['name'] === 'actionNewsletterRegistrationAfter';
        }));
    }

    public function testConfirmEmailRejectsUnknownToken()
    {
        $this->insertGuest('guest@example.com', 0);
        $module = $this->newModule();
        $message = $module->confirmEmail('bad-token');
        $this->assertSentinel($message, 'This email is already registered and/or invalid.', 'Modules.Emailsubscription.Shop');
        $this->assertSame('0', (string) Db::getInstance()->getValue('SELECT active FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"'));
    }

    public function testSecondConfirmDoesNotActivateAgain()
    {
        $this->insertGuest('guest@example.com', 0);
        $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $token = md5('guest@example.com' . $row['newsletter_date_add'] . Configuration::get('NW_SALT'));
        $module = $this->newModule();
        $module->confirmEmail($token);
        $message = $module->confirmEmail($token);
        $this->assertSentinel($message, 'This email is already registered and/or invalid.', 'Modules.Emailsubscription.Shop');
    }

    public function testConfirmEmailSubscribesCustomerFromDateAdd()
    {
        $id = $this->insertCustomer([
            'email' => 'customer@example.com',
            'newsletter' => 0,
            'date_add' => '2021-02-02 10:00:00',
        ]);
        $token = md5('customer@example.com2021-02-02 10:00:00' . Configuration::get('NW_SALT'));
        $module = $this->newModule();
        $module->confirmEmail($token);
        $newsletter = Db::getInstance()->getValue('SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . (int) $id);
        $this->assertSame('1', (string) $newsletter);
    }
}
