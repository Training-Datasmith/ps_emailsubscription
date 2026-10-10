<?php

class SubscribersAndWidgetTest extends DatabaseTestCase
{
    public function testGetSubscribersMergesActiveCustomersAndGuests()
    {
        $this->insertCustomer(['email' => 'sub@example.com', 'newsletter' => 1, 'firstname' => 'Sub']);
        $this->insertCustomer(['email' => 'nosub@example.com', 'newsletter' => 0]);
        $this->insertGuest('guest@example.com', 1);
        $this->insertGuest('inactive@example.com', 0);
        $module = $this->newModule();
        $rows = $module->getSubscribers();
        $emails = array_column($rows, 'email');
        $this->assertContains('sub@example.com', $emails);
        $this->assertContains('guest@example.com', $emails);
        $this->assertNotContains('nosub@example.com', $emails);
        $this->assertNotContains('inactive@example.com', $emails);

        $module->_searched_email = 'guest@';
        $rows = $module->getSubscribers();
        $this->assertSame(['guest@example.com'], array_column($rows, 'email'));
    }

    public function testGetSubscribersRespectsMultishopContext()
    {
        $this->insertGuest('shop2@example.com', 1, 2);
        Shop::$featureActive = true;
        Shop::$contextShopIds = [1];
        $module = $this->newModule();
        $emails = array_column($module->getSubscribers(), 'email');
        $this->assertNotContains('shop2@example.com', $emails);
    }

    public function testPaginateSubscribersSlicesOnlyWhenLongerThanThePage()
    {
        $module = $this->newModule();
        $ten = range(1, 10);
        $this->assertCount(10, $module->paginateSubscribers($ten, 1, 50));
        $sixty = range(1, 60);
        $this->assertCount(50, $module->paginateSubscribers($sixty, 1, 50));
        $this->assertCount(10, $module->paginateSubscribers($sixty, 2, 50));
        $hundred = range(1, 100);
        $pageTwo = $module->paginateSubscribers($hundred, 2, 40);
        $this->assertSame(40, count($pageTwo));
        $this->assertSame(41, $pageTwo[0]);
    }

    public function testWidgetVariablesWithoutSubmit()
    {
        $module = $this->newModule();
        $vars = $module->getWidgetVariables();
        $this->assertSame('', $vars['value']);
        $this->assertSame('', $vars['msg']);
        $this->assertSame('CONDITIONS_SENTINEL', $vars['conditions']);
    }

    public function testWidgetVariablesExposeRegistrationError()
    {
        Validate::$emailResults['bad@example.com'] = false;
        $_POST = [
            'email' => 'bad@example.com',
            'action' => '0',
            'submitNewsletter' => '1',
            'blockHookName' => 'displayFooterBefore',
        ];
        $module = $this->newModule();
        $vars = $module->getWidgetVariables('displayFooterBefore');
        $this->assertTrue($vars['nw_error']);
        $this->assertSentinel($vars['msg'], 'Invalid email address.', 'Shop.Notifications.Error');
        $this->assertSame('bad@example.com', $vars['value']);
    }

    public function testRenderWidgetSelectsColumnTemplate()
    {
        $module = $this->newModule();
        $module->renderWidget('displayLeftColumn');
        $this->assertSame('module:ps_emailsubscription/views/templates/hook/ps_emailsubscription-column.tpl', Module::$lastFetchTemplate);
        $module->renderWidget('displayFooterBefore');
        $this->assertSame('module:ps_emailsubscription/views/templates/hook/ps_emailsubscription.tpl', Module::$lastFetchTemplate);
    }

    public function testFrontMediaHookRegistersTheModuleScript()
    {
        $module = $this->newModule();
        $module->hookActionFrontControllerSetMedia();
        $this->assertArrayHasKey('psemailsubscription_subscription', Media::$jsDef);
        $this->assertSame('modules/ps_emailsubscription/views/js/ps_emailsubscription.js', Media::$registered[0]['path']);
    }
}
