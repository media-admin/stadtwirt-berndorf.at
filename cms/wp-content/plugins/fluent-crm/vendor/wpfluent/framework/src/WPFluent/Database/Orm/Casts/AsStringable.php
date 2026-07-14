<?php

namespace FluentCrm\Framework\Database\Orm\Casts;

use FluentCrm\Framework\Support\Str;
use FluentCrm\Framework\Database\Orm\Castable;
use FluentCrm\Framework\Database\Orm\CastsAttributes;

class AsStringable implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return object|string
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get($model, $key, $value, $attributes)
            {
                return isset($value) ? Str::of($value) : null;
            }

            public function set($model, $key, $value, $attributes)
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}
