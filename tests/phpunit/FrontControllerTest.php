<?php

class FrontControllerTest extends DatabaseTestCase
{
    public function testVerificationControllerStoresConfirmEmailMessage()
    {
        $this->insertGuest('guest@example.com', 0);
        $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE email = "guest@example.com"');
        $token = md5('guest@example.com' . $row['newsletter_date_add'] . Configuration::get('NW_SALT'));
        $_GET['token'] = $token;
        $module = $this->newModule();
        $controller = new Ps_EmailsubscriptionVerificationModuleFrontController();
        $controller->module = $module;
        $controller->postProcess();
        $controller->initContent();
        $this->assertSentinel($controller->context->smarty->message, 'Thank you for subscribing to our newsletter.', 'Modules.Emailsubscription.Shop');
        $this->assertSame('module:ps_emailsubscription/views/templates/front/verification_execution.tpl', $controller->template);
    }

    public function testSubscriptionControllerMapsErrorAndSuccess()
    {
        Validate::$emailResults['bad@example.com'] = false;
        $_POST = ['submitNewsletter' => '1', 'email' => 'bad@example.com', 'action' => '0'];
        $module = $this->newModule();
        $controller = new Ps_EmailsubscriptionSubscriptionModuleFrontController();
        $controller->module = $module;
        $controller->postProcess();
        $controller->initContent();
        $this->assertTrue($controller->context->smarty->variables['nw_error']);
        $this->assertSentinel($controller->context->smarty->variables['msg'], 'Invalid email address.', 'Shop.Notifications.Error');

        Validate::$emailResults['guest@example.com'] = true;
        $_POST = ['submitNewsletter' => '1', 'email' => 'guest@example.com', 'action' => '0'];
        $module = $this->newModule();
        $controller = new Ps_EmailsubscriptionSubscriptionModuleFrontController();
        $controller->module = $module;
        $controller->postProcess();
        $controller->initContent();
        $this->assertFalse($controller->context->smarty->variables['nw_error']);
        $this->assertSentinel($controller->context->smarty->variables['msg'], 'You have successfully subscribed to this newsletter.', 'Modules.Emailsubscription.Shop');
        $this->assertSame('module:ps_emailsubscription/views/templates/front/subscription_execution.tpl', $controller->template);
    }

    public function testSubscriptionAjaxEmitsJson()
    {
        $script = dirname(__DIR__) . '/scripts/ajax_subscription_probe.php';
        $php = getenv('PHP_BINARY') ?: 'php';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script);
        $output = shell_exec($cmd . ' 2>&1');
        $this->assertContains('AJAX_BEFORE', $output);
        $this->assertNotContains('AJAX_UNEXPECTED', $output);
        $jsonStart = strpos($output, '{');
        $this->assertNotFalse($jsonStart);
        $payload = json_decode(substr($output, $jsonStart), true);
        $this->assertTrue($payload['nw_error']);
        $this->assertContains('§§', $payload['msg']);
    }
}
