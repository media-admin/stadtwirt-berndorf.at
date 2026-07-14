<?php

namespace FluentCrm\App\Services\BlockRender;

class RenderValueHelper
{
    /**
     * Normalize mixed values coming from block attrs into bool.
     *
     * @param mixed $value
     * @param bool $default
     * @return bool
     */
    public static function normalizeBool($value, $default = true)
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int)$value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return !in_array($normalized, ['0', 'false', 'no', 'off'], true);
        }

        return (bool)$value;
    }
}
