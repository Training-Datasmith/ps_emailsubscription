<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_emailsubscription PrestaShop module.
 *
 * ps_emailsubscription adds a newsletter subscription form to the storefront
 * footer. Subscribers are stored in the ps_emailsubscription table and can
 * optionally receive a confirmation email. The module dispatches GDPR consent
 * hooks to integrate with ps_dataprivacy.
 *
 * This file documents common integration patterns.
 */

// --- Widget invocation in Smarty/Twig template ---
// {widget name="ps_emailsubscription" hook="displayFooter"}

// --- Hook: actionCustomerBeforeUpdateGroup ---
// The module checks newsletter opt-in status during customer updates.

// --- Hook: actionAdminNewsletterRegistrationBefore ---
// Fired before a subscription is saved. Use to validate or modify the email:
//
// Hook::register('actionAdminNewsletterRegistrationBefore', 'MyModule', 'onBeforeSubscribe');
//
// public function onBeforeSubscribe(array &$params): void
// {
//     $email = $params['email'];
//     if (str_ends_with($email, '@spam.com')) {
//         $params['error'] = 'Domain not allowed.';
//     }
// }

// --- Hook: actionAdminNewsletterRegistrationAfter ---
// Fired after a successful subscription. Use to sync with third-party services:
//
// Hook::register('actionAdminNewsletterRegistrationAfter', 'MyModule', 'onAfterSubscribe');
//
// public function onAfterSubscribe(array $params): void
// {
//     $email = $params['email'];
//     MailchimpApi::subscribe($email, listId: 'abc123');
// }

// --- Querying subscribers programmatically ---
// $subscribers = Db::getInstance()->executeS(
//     'SELECT email, newsletter_date_add, active
//      FROM `' . _DB_PREFIX_ . 'emailsubscription`
//      WHERE `active` = 1
//      ORDER BY newsletter_date_add DESC'
// );
//
// foreach ($subscribers as $sub) {
//     echo $sub['email'] . ' — subscribed: ' . $sub['newsletter_date_add'] . "\n";
// }

// --- Back Office configuration ---
// Modules > Newsletter subscription:
//   - Send a confirmation email (yes/no)
//   - Confirmation email subject and text
//   - Voucher code for new subscribers (optional)
//   - GDPR consent integration with ps_dataprivacy
