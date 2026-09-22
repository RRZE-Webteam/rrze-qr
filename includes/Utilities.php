<?php

namespace RRZE\QR;
defined('ABSPATH') || exit;

/** Pure color and size validation shared by the settings handlers. */
class Utilities
{
    /**
     * Sanitizes the ForegroundColor and returns it as HEX Value
     * @param $value Foreground HEX Color Value
     *
     * @return string
     */
    public static function sanitizeForegroundHexColor($value): string
    {
        return self::normalizeColor($value) ?? '#000000';
    }

    /**
     * Sanitizes the Background Color and returns it as HEX-Value
     * @param $value Background HEX Color Value
     *
     * @return string
     */
    public static function sanitizeBackgroundHexColor($value): string
    {
        return self::normalizeColor($value, true) ?? '#ffffff';
    }

    /**
     * Accepts opaque hex colors and the original preset tokens.
     *
     * @param $value             hex
     * @param $allow_transparent boolean
     *
     * @return ?string
     */
    public static function normalizeColor($value, $allow_transparent = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($allow_transparent && $value === 'transparent') {
            return $value;
        }
        $legacy = ['white' => '#ffffff', 'black' => '#000000', 'fau' => '#04316a'];
        if (isset($legacy[$value])) {
            return $legacy[$value];
        }
        if (!preg_match('/\A#(?:[0-9a-f]{3}|[0-9a-f]{6})\z/', $value)) {
            return null;
        }
        if (strlen($value) === 4) {
            return '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }
        return $value;
    }

    /**
     * Checks that normalized foreground and background colors differ.
     * @param array $tokens
     *
     * @return bool
     */
    public static function isValidColorPair(array $tokens): bool
    {
        return $tokens['foreground'] !== $tokens['background'];
    }

    /**
     * Normalizes the Size value. Accepts 128 through 4096 pixels, inclusive.
     * @param $value    int
     *
     * @return ?int
     */
    public static function normalizeSize($value): ?int
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value))) {
            return null;
        }
        return $value >= 128 && $value <= 4096 ? (int) $value : null;
    }
}
