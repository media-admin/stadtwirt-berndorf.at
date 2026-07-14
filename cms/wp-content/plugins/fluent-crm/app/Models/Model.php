<?php

namespace FluentCrm\App\Models;

use FluentCrm\Framework\Database\Orm\Model as BaseModel;
use FluentCrm\Framework\Support\Str;

class Model extends BaseModel
{
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
    }

    public function scopeLatest($query, $field = 'created_at')
    {
        return $query->orderBy($field, 'desc');
    }

    public function scopeNewest($query, $field = 'created_at')
    {
        return $query->orderBy($field, 'asc');
    }

    public function getPerPage()
    {
        return (isset($_REQUEST['per_page'])) ? intval($_REQUEST['per_page']) : 15;
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return \DateTime
     */
    public function freshTimestamp()
    {
        return new \FluentCrm\Framework\Support\DateTime(current_time('mysql'));
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getTimezone()
    {
        return wp_timezone();
    }

    protected function asDateTime($value)
    {
        if (is_string($value) && Str::contains($value, 'T')) {
            return new \FluentCrm\Framework\Support\DateTime($value);
        }

        return parent::asDateTime($value);
    }

    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];

        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

}
