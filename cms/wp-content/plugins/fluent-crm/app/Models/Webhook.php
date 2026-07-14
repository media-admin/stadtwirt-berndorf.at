<?php

namespace FluentCrm\App\Models;

use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

/**
 *  Webhook Model - DB Model for Webhooks
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */

class Webhook extends Meta
{
    protected $fillable = [
        'id',
        'key',
        'value',
        'object_type'
    ];

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('type', function ($builder) {
            $builder->where(function($query) {
                $query->where('object_type', '=', 'webhook')
                      ->orWhere('object_type', 'LIKE', 'webhook_%');
            });
        });
    }

    public function getFields()
    {
        $contactFields = [
            'fields' => [],
            'custom_fields' => []
        ];

        foreach (Subscriber::mappables() as $key => $column) {
            $contactFields['fields'][] = ['key' => $key, 'field' => $column];
        }

        foreach ((new CustomContactField)->getGlobalFields()['fields'] as $field) {
            $contactFields['custom_fields'][] = ['key' => $field['slug'], 'field' => $field['label']];
        }

        return $contactFields;
    }

    public function getSchema()
    {
        $schema = [
            'name'      => '',
            'lists'     => [],
            'tags'      => [],
            'url'       => '',
            'status'    => ''
        ];

        if (Helper::isCompanyEnabled()) {
            $schema['companies'] = [];
        }

        return $schema;
    }

    public function store($data)
    {
        $key = wp_generate_uuid4();
        $webhookUrl = site_url("?fluentcrm=1&route=contact&hash={$key}");

        return static::create([
            'object_type' => 'webhook',
            'key'         => $key,
            'value'       => array_merge($data, [
                'url' => $webhookUrl
            ]),
        ]);
    }

    public function saveChanges($data)
    {
        $data['tags'] = Arr::get($data, 'tags', []);
        $data['lists'] = Arr::get($data, 'lists', []);
        $data['companies'] = Arr::get($data, 'companies', []);

        $this->value = array_merge(
            $this->value,
            array_diff_key($data, [
                'id' => '', 'url' => ''
            ])
        );

        $this->save();

        return $this;
    }
}
