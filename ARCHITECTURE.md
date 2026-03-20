# Architecture: ps_emailsubscription

## Purpose

A PrestaShop module that adds a newsletter subscription form to the front office. Collects
customer email addresses and optionally integrates with the built-in PrestaShop newsletter
system or third-party newsletter services.

## Directory Structure

```
ps_emailsubscription.php   # Main module class (WidgetInterface)
views/templates/            # Smarty/Twig templates for subscription form
mails/                      # Confirmation email templates
translations/               # Translation files
tests/                      # PHPStan and unit tests
```

## Key Design Decisions

Implements `WidgetInterface` for footer/sidebar placement. Validates email format on
submission, optionally sends a confirmation email, and stores subscription in the
`ps_emailsubscription` table. Hooks: `displayGDPRConsent` for GDPR compliance.

## Extension Points

- Hook `actionAdminNewsletterRegistrationBefore` / `After` for custom subscription logic.
- Override templates in theme.
- Integrate with third-party email services by listening to the post-registration hook.
