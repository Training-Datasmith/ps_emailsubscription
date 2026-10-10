<?php

final class Translation
{
    public static function sentinel($message, $domain, $locale = null)
    {
        $localePart = $locale === null ? '' : '|' . $locale;

        return '§§' . $domain . '|' . $message . $localePart . '§§';
    }

    public static function containsSentinel($haystack, $message, $domain)
    {
        if (!is_string($haystack)) {
            return false;
        }

        return strpos($haystack, '§§' . $domain . '|' . $message) !== false;
    }
}
