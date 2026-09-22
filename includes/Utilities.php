<?php

namespace RRZE\QR;
defined('ABSPATH') || exit;
class Utilities
{
    /**
     * Sanitizes the ForegroundColor and returns it as HEX Value
     * @param $value Foreground HEX Color Value
     *
     * @return string
     */
    public function sanitizeForegroundHexColor($value)
    {
        return $this->normalizeColor($value) ?? '#000000';
    }

    /**
     * Sanitizes the Background Color and returns it as HEX-Value
     * @param $value Background HEX Color Value
     *
     * @return string
     */
    public function sanitizeBackgroundHexColor($value)
    {
        return $this->normalizeColor($value, true) ?? '#ffffff';
    }

    /**
     * Accepts opaque hex colors and the original preset tokens.
     *
     * @param $value             hex
     * @param $allow_transparent boolean
     *
     * @return string|null
     */
    public function normalizeColor($value, $allow_transparent = false): string|null
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
     * Evaluates it the Foreground Background combination is not identical.
     * @param array $tokens
     *
     * @return bool
     */
    public function isValidColorPair(array $tokens): bool
    {
        return $tokens['foreground'] !== $tokens['background'];
    }

    /**
     * Normalizes the Size value. Has to be greater than 128px and smaller than 4096px.
     * @param $value    int
     *
     * @return int|null
     */
    public function normalizeSize($value): int|null
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value))) {
            return null;
        }
        return $value >= 128 && $value <= 4096 ? (int) $value : null;
    }
}