<?php

namespace App\Page;

/**
 * Decides which addresses may be put in a link or an image on a built page or in
 * the sidebar. Anything that is not clearly harmless is refused, so a value like
 * `javascript:alert(1)` can never become a clickable link.
 *
 * Allowed:
 *   - http:// and https:// addresses (with a host)
 *   - mailto: and tel:
 *   - a page of this site, written without a scheme: `register`, `page/about`,
 *     `admin/users?x=1`, `#section`
 */
final class Links
{
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @return string|null the address, trimmed, or null when it must not be used
     */
    public static function safe($url)
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 2000) {
            return null;
        }
        // Whitespace or control characters inside an address are how filters get fooled
        // (browsers ignore tabs and newlines in "java\tscript:"), and a backslash is read as a slash.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }
        // "//host/path" would leave this site without saying so.
        if (strpos($url, '//') === 0) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
            if (!in_array(strtolower($m[1]), self::SCHEMES, true)) {
                return null;
            }
            if (in_array(strtolower($m[1]), ['http', 'https'], true)) {
                $host = parse_url($url, PHP_URL_HOST);
                if (!is_string($host) || $host === '') {
                    return null;
                }
            }

            return $url;
        }

        return $url;
    }

    /**
     * Whether a (safe) address already says where it goes, as opposed to a page of this site.
     */
    public static function isAbsolute($url)
    {
        return (bool) preg_match('/^(https?|mailto|tel):/i', (string) $url);
    }

    /**
     * The address to put in the page: absolute ones as they are, pages of this site through $site
     * (normally site_url).
     *
     * @param callable(string): string $site
     *
     * @return string|null null when the address must not be used
     */
    public static function resolve($url, callable $site)
    {
        $safe = self::safe($url);
        if ($safe === null) {
            return null;
        }
        if (self::isAbsolute($safe)) {
            return $safe;
        }

        return $safe[0] === '#' ? $safe : $site(ltrim($safe, '/'));
    }
}
