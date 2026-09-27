<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes admin-authored legal HTML (Quill output) against XSS.
 *
 * Used both when content is saved (defense at write) and when it is
 * rendered publicly (defense in depth for legacy rows).
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * Clean a HTML fragment and return a safe string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    /**
     * Whether a standalone URL is safe to use as an href.
     *
     * Allows only http(s), mailto, and site-relative paths (single leading
     * slash — protocol-relative "//host" is rejected). Rejects javascript:,
     * data:, vbscript:, and every other scheme.
     */
    public static function isSafeUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        return (bool) preg_match(self::SAFE_URL_PATTERN, trim($url));
    }

    /**
     * Validation regex mirroring isSafeUrl (optional so blank form input passes).
     */
    public static function safeUrlRegex(): string
    {
        return self::SAFE_URL_PATTERN_PCRE;
    }

    private const SAFE_URL_PATTERN = '/^(?:https?:\/\/[^\s]+|mailto:[^\s]+|\/(?!\/)[^\s]*)$/i';

    private const SAFE_URL_PATTERN_PCRE = '/^(?:https?:\/\/[^\s]+|mailto:[^\s]+|\/(?!\/)[^\s]*)?$/i';

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();

            // Allowlist matching what the admin Quill editor can produce.
            $config->set('HTML.Allowed',
                'p,br,h2,h3,h4,strong,em,u,s,b,i,a[href|title],ul,ol,li,blockquote,code,pre,span[class]'
            );

            // Only web-safe link schemes; javascript:/data: are rejected.
            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ]);

            // Quill formatting classes (alignment, indentation, direction).
            $classes = ['ql-direction-rtl'];
            foreach (['left', 'center', 'right', 'justify'] as $align) {
                $classes[] = 'ql-align-'.$align;
            }
            for ($i = 1; $i <= 12; $i++) {
                $classes[] = 'ql-indent-'.$i;
            }
            $config->set('Attr.AllowedClasses', $classes);

            // No id attributes in stored content (TOC ids are injected at render time).
            $config->set('Attr.EnableID', false);

            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
