<?php

class NewsletterRegistrationTest extends DatabaseTestCase
{
    public function testUnknownEmailIsGuestNotRegistered()
    {
        $module = $this->newModule();
        $this->assertSame(Ps_Emailsubscription::GUEST_NOT_REGISTERED, $module->isNewsletterRegistered('guest@example.com'));
    }

    public function testCustomerWithoutFlagIsNotRegistered()
    {
        $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 0]);
        $module = $this->newModule();
        $this->assertSame(Ps_Emailsubscription::CUSTOMER_NOT_REGISTERED, $module->isNewsletterRegistered('customer@example.com'));
    }

    public function testActiveGuestIsRegisteredInTheBlock()
    {
        $this->insertGuest('guest@example.com', 1);
        $module = $this->newModule();
        $this->assertSame(Ps_Emailsubscription::GUEST_REGISTERED, $module->isNewsletterRegistered('guest@example.com'));
    }

    public function testCustomerFlagIsRegisteredOnTheCustomer()
    {
        $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 1]);
        $module = $this->newModule();
        $this->assertSame(Ps_Emailsubscription::CUSTOMER_REGISTERED, $module->isNewsletterRegistered('customer@example.com'));
    }

    public function testGuestInOtherShopDoesNotCount()
    {
        $this->insertGuest('guest@example.com', 1, 2);
        $module = $this->newModule();
        $this->assertSame(Ps_Emailsubscription::GUEST_NOT_REGISTERED, $module->isNewsletterRegistered('guest@example.com'));
    }

    public function testInvalidEmailDoesNotInsertOrRunAfterHook()
    {
        Validate::$emailResults['not-an-email'] = false;
        $_POST = ['email' => 'not-an-email', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSentinel($module->error, 'Invalid email address.', 'Shop.Notifications.Error');
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
        $this->assertSame([], array_filter(Hook::$executions, function ($item) {
            return $item['name'] === 'actionNewsletterRegistrationAfter';
        }));
    }

    public function testBeforeHookErrorAborts()
    {
        Hook::$beforeCallbacks['hookError'] = 'HOOK_STOP_SENTINEL';
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSame('HOOK_STOP_SENTINEL', $module->error);
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testHookNameMismatchDoesNotSubscribe()
    {
        $_POST = ['email' => 'guest@example.com', 'action' => '0', 'blockHookName' => 'other'];
        $module = $this->newModule();
        $this->assertFalse($module->newsletterRegistration('displayFooterBefore'));
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));

        $_POST['blockHookName'] = 'displayFooterBefore';
        $this->assertNotFalse($module->newsletterRegistration('displayFooterBefore'));
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testNullHookNameSubscribesWithoutBlockHookName()
    {
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testImmediateGuestSubscribePersistsRowAndMessage()
    {
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertTrue($module->valid !== false);
        $this->assertSentinel($module->valid, 'You have successfully subscribed to this newsletter.', 'Modules.Emailsubscription.Shop');
        $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $this->assertSame('1', (string) $row['active']);
        $this->assertSame('1', (string) $row['id_lang']);
        $this->assertSame('127.0.0.1', $row['ip_registration_newsletter']);
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['newsletter_date_add']);
        $this->assertSame([], Mail::$sent);
    }

    public function testVerificationFlowKeepsGuestInactive()
    {
        Configuration::updateValue('NW_VERIFICATION_EMAIL', 1);
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $this->assertSame('0', (string) $row['active']);
        $this->assertSentinel($module->valid, 'A verification email has been sent. Please check your inbox.', 'Modules.Emailsubscription.Shop');
        $this->assertCount(1, Mail::$sent);
        $this->assertSame('newsletter_verif', Mail::$sent[0]['template']);
        $this->assertSame('guest@example.com', Mail::$sent[0]['to']);
        $this->assertContains('Email verification', Mail::$sent[0]['subject']);
        $this->assertContains('Emails.Subject', Mail::$sent[0]['subject']);
        $expectedToken = md5('guest@example.com' . $row['newsletter_date_add'] . Configuration::get('NW_SALT'));
        $this->assertSame($expectedToken, Context::getContext()->link->lastModuleLink['params']['token']);
    }

    public function testDuplicateGuestIsRejected()
    {
        $this->insertGuest('guest@example.com', 1);
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSentinel($module->error, 'This email address is already registered.', 'Modules.Emailsubscription.Shop');
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testCustomerSubscribeSetsNewsletterFlag()
    {
        $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 0]);
        $_POST = ['email' => 'customer@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $newsletter = Db::getInstance()->getValue(
            'SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE email = "customer@example.com"'
        );
        $this->assertSame('1', (string) $newsletter);
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testVoucherAndConfirmationMailFollowConfiguration()
    {
        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 1);
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        $_POST = ['email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $templates = array_column(Mail::$sent, 'template');
        $this->assertContains('newsletter_voucher', $templates);
        $this->assertContains('newsletter_conf', $templates);
        $this->assertSame('WELCOME10', Mail::$sent[0]['vars']['{discount}']);
        $this->assertSame(1, Mail::$sent[0]['idShop']);
        $this->assertContains('/mails/', Mail::$sent[0]['templatePath']);
    }

    public function testUnsubscribeUnknownEmail()
    {
        $_POST = ['email' => 'guest@example.com', 'action' => '1'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSentinel($module->error, 'This email address is not registered.', 'Modules.Emailsubscription.Shop');
    }

    public function testUnsubscribeGuestDeletesRow()
    {
        $this->insertGuest('guest@example.com', 1);
        $_POST = ['email' => 'guest@example.com', 'action' => '1'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $this->assertSentinel($module->valid, 'Unsubscription successful.', 'Modules.Emailsubscription.Shop');
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription'));
    }

    public function testUnsubscribeCustomerClearsFlag()
    {
        $this->insertCustomer(['email' => 'customer@example.com', 'newsletter' => 1]);
        $_POST = ['email' => 'customer@example.com', 'action' => '1'];
        $module = $this->newModule();
        $module->newsletterRegistration();
        $newsletter = Db::getInstance()->getValue(
            'SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE email = "customer@example.com"'
        );
        $this->assertSame('0', (string) $newsletter);
    }

    public function testUnregisterUsesEmailArgumentNotPostForGuest()
    {
        $this->insertGuest('arg@example.com', 1);
        $this->insertGuest('post@example.com', 1);
        $_POST['email'] = 'post@example.com';
        $module = $this->newModule();
        $this->assertTrue($module->callUnregister('arg@example.com', Ps_Emailsubscription::GUEST_REGISTERED));
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "arg@example.com"'));
        $this->assertSame(1, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "post@example.com"'));
    }

    public function testUnregisterUsesEmailArgumentNotPostForCustomer()
    {
        $this->insertCustomer(['email' => 'arg@example.com', 'newsletter' => 1]);
        $this->insertCustomer(['email' => 'post@example.com', 'newsletter' => 1]);
        $_POST['email'] = 'post@example.com';
        $module = $this->newModule();
        $this->assertTrue($module->callUnregister('arg@example.com', Ps_Emailsubscription::CUSTOMER_REGISTERED));
        $this->assertSame('0', (string) Db::getInstance()->getValue('SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE email = "arg@example.com"'));
        $this->assertSame('1', (string) Db::getInstance()->getValue('SELECT newsletter FROM ' . _DB_PREFIX_ . 'customer WHERE email = "post@example.com"'));
    }

    public function testConfirmSubscriptionHonorsFlags()
    {
        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 1);
        Configuration::updateValue('NW_VOUCHER_CODE', 'WELCOME10');
        $module = $this->newModule();
        $module->confirmSubscription('guest@example.com');
        $this->assertCount(2, Mail::$sent);
        Mail::reset();
        Configuration::updateValue('NW_CONFIRMATION_EMAIL', 0);
        Configuration::updateValue('NW_VOUCHER_CODE', '');
        $module->confirmSubscription('');
        $this->assertSame([], Mail::$sent);
    }
}
