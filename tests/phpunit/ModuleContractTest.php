<?php

class ModuleContractTest extends DatabaseTestCase
{
    public function testRegistrationConstantsMatchThePostedAction()
    {
        $this->assertSame(-1, Ps_Emailsubscription::GUEST_NOT_REGISTERED);
        $this->assertSame(0, Ps_Emailsubscription::CUSTOMER_NOT_REGISTERED);
        $this->assertSame(1, Ps_Emailsubscription::GUEST_REGISTERED);
        $this->assertSame(2, Ps_Emailsubscription::CUSTOMER_REGISTERED);
        $this->assertSame(0, Ps_Emailsubscription::NEWSLETTER_SUBSCRIPTION);
        $this->assertSame(1, Ps_Emailsubscription::NEWSLETTER_UNSUBSCRIPTION);
    }

    public function testConstructorPublishesFrontControllers()
    {
        $module = $this->newModule();
        $this->assertSame('ps_emailsubscription', $module->name);
        $controllers = $module->controllers;
        sort($controllers);
        $this->assertSame(['subscription', 'verification'], $controllers);
    }
}
