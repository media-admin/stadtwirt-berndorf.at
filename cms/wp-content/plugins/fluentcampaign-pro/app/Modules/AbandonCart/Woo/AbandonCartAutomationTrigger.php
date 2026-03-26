<?php

namespace FluentCampaign\App\Modules\AbandonCart\Woo;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AbandonCartAutomationTrigger extends BaseTrigger
{

    public function __construct()
    {
        $this->triggerName = 'fc_ab_cart_simulation_woo';
        $this->priority = 99;
        $this->actionArgNum = 1;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('WooCommerce', 'fluentcampaign-pro'),
            'label'       => __('Cart Abandoned', 'fluentcampaign-pro'),
            'description' => __('This Funnel will be initiated when a cart has been abandoned in WooCommerce', 'fluentcampaign-pro'),
            'icon'        => 'fc-icon-woo',
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Cart Abandoned', 'fluentcampaign-pro'),
            'sub_title' => __('This Funnel will be initiated when a cart has been abandoned in WooCommerce', 'fluentcampaign-pro'),
            'fields'    => [
                'priority' => [
                    'label'       => __('Priority of this abandon cart automation trigger', 'fluentcampaign-pro'),
                    'type'        => 'input-number',
                    'placeholder' => __('Automation Priority', 'fluentcampaign-pro'),
                    'inline_help' => __('If you have multiple automation for abandon cart, you can set the priority. The higher the priority means it will match earlier. Only one abandon cart automation will run per abandonment depends on your conditional logics.', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'priority' => 10
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'cart_conditions'    => [[]],
            'active_once'        => 'no',
            'require_subscribed' => 'no'
        ];
    }

    public function getConditionFields($funnel)
    {
        return [
            'cart_conditions'    => [
                'type'        => 'condition_block_groups',
                'label'       => __('Specify Matching Conditions', 'fluentcampaign-pro'),
                'inline_help' => __('Specify which contact properties need to matched. Based on the conditions it will run yes blocks or no blocks', 'fluentcampaign-pro'),
                'labels'      => [
                    'match_type_all_label' => __('True if all conditions match', 'fluentcampaign-pro'),
                    'match_type_any_label' => __('True if any of the conditions match', 'fluentcampaign-pro'),
                    'data_key_label'       => __('Contact Data', 'fluentcampaign-pro'),
                    'condition_label'      => __('Condition', 'fluentcampaign-pro'),
                    'data_value_label'     => __('Match Value', 'fluentcampaign-pro')
                ],
                'groups'      => $this->getConditionGroups($funnel),
                'add_label'   => __('Add Condition to check your contact\'s properties', 'fluentcampaign-pro'),
            ],
            'active_once'        => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Skip this automation if the contact is already in active state.', 'fluentcampaign-pro'),
                'inline_help' => __('Enable this to prevent the automation from running multiple times for the same contact if it is currently active in this automation', 'fluentcampaign-pro')
            ],
            'require_subscribed' => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Only run this automation for subscribed contacts', 'fluentcampaign-pro'),
                'inline_help' => __('If you enable, then it will only run this automation for subscribed contacts', 'fluentcampaign-pro')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        // do nothing here
    }

    public function getConditionGroups($funnel)
    {
        $groups = [
            'ab_cart_woo' => [
                'label'    => __('Cart Data', 'fluentcampaign-pro'),
                'value'    => 'ab_cart_woo',
                'children' => [
                    [
                        'label' => __('Cart Total', 'fluentcampaign-pro'),
                        'value' => 'cart_total',
                        'type'  => 'numeric'
                    ],
                    [
                        'label' => __('Cart Items Count', 'fluentcampaign-pro'),
                        'value' => 'cart_items_count',
                        'type'  => 'numeric'
                    ],
                    [
                        'label'       => __('Cart Items', 'fluentcampaign-pro'),
                        'value'       => 'cart_items',
                        'type'        => 'selections',
                        'component'   => 'ajax_selector',
                        'option_key'  => 'woo_products',
                        'is_multiple' => true,
                        'help'        => 'Match the products on the cart'
                    ],
                    [
                        'label'       => __('Cart Items Categories', 'fluentcampaign-pro'),
                        'value'       => 'cart_items_categories',
                        'type'        => 'selections',
                        'component'   => 'tax_selector',
                        'taxonomy'    => 'product_cat',
                        'is_multiple' => true,
                        'help'        => 'Match the product categories on the cart'
                    ],
                ]
            ],
            'subscriber'  => [
                'label'    => __('Contact', 'fluentcampaign-pro'),
                'value'    => 'subscriber',
                'children' => [
                    [
                        'label' => __('First Name', 'fluentcampaign-pro'),
                        'value' => 'first_name',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Last Name', 'fluentcampaign-pro'),
                        'value' => 'last_name',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Email', 'fluentcampaign-pro'),
                        'value' => 'email',
                        'type'  => 'extended_text'
                    ],
                    [
                        'label' => __('Address Line 1', 'fluentcampaign-pro'),
                        'value' => 'address_line_1',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Address Line 2', 'fluentcampaign-pro'),
                        'value' => 'address_line_2',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('City', 'fluentcampaign-pro'),
                        'value' => 'city',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('State', 'fluentcampaign-pro'),
                        'value' => 'state',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Postal Code', 'fluentcampaign-pro'),
                        'value' => 'postal_code',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label'             => __('Country', 'fluentcampaign-pro'),
                        'value'             => 'country',
                        'type'              => 'selections',
                        'component'         => 'options_selector',
                        'option_key'        => 'countries',
                        'is_multiple'       => true,
                        'is_singular_value' => true
                    ],
                    [
                        'label' => __('Phone', 'fluentcampaign-pro'),
                        'value' => 'phone',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('WP User ID', 'fluentcampaign-pro'),
                        'value' => 'user_id',
                        'type'  => 'numeric',
                    ],
                    [
                        'label'             => __('Type', 'fluentcampaign-pro'),
                        'value'             => 'contact_type',
                        'type'              => 'selections',
                        'component'         => 'options_selector',
                        'option_key'        => 'contact_types',
                        'is_multiple'       => false,
                        'is_singular_value' => true
                    ],
                    [
                        'label'       => __('Name Prefix (Title)', 'fluentcampaign-pro'),
                        'value'       => 'prefix',
                        'type'        => 'selections',
                        'options'     => \FluentCrm\App\Services\Helper::getContactPrefixes(true),
                        'is_multiple' => true,
                        'is_only_in'  => true
                    ],
                    [
                        'label' => __('Date of Birth', 'fluentcampaign-pro'),
                        'value' => 'date_of_birth',
                        'type'  => 'dates',
                    ],
                    [
                        'label' => __('Last Activity', 'fluentcampaign-pro'),
                        'value' => 'last_activity',
                        'type'  => 'dates',
                    ],
                    [
                        'label' => __('Created At', 'fluentcampaign-pro'),
                        'value' => 'created_at',
                        'type'  => 'dates',
                    ]
                ],
            ],
            'segment'     => [
                'label'    => __('Contact Segment', 'fluentcampaign-pro'),
                'value'    => 'segment',
                'children' => [
                    [
                        'label'       => __('Tags', 'fluentcampaign-pro'),
                        'value'       => 'tags',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'tags',
                        'is_multiple' => true,
                    ],
                    [
                        'label'       => __('Lists', 'fluentcampaign-pro'),
                        'value'       => 'lists',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'lists',
                        'is_multiple' => true,
                    ],
                    [
                        'label'             => __('WP User Role', 'fluentcampaign-pro'),
                        'value'             => 'user_role',
                        'type'              => 'selections',
                        'is_singular_value' => true,
                        'options'           => FunnelHelper::getUserRoles(true),
                        'is_multiple'       => true,
                    ]
                ],
            ],
            'activities'  => [
                'label'    => __('Contact Activities', 'fluentcampaign-pro'),
                'value'    => 'activities',
                'children' => [
                    [
                        'label' => __('Last Email Sent', 'fluentcampaign-pro'),
                        'value' => 'email_sent',
                        'type'  => 'dates',
                    ],
                    [
                        'label' => __('Last Email Clicked', 'fluentcampaign-pro'),
                        'value' => 'email_link_clicked',
                        'type'  => 'dates',
                    ],
                    [
                        'label' => __('Last Email Open (approximately)', 'fluentcampaign-pro'),
                        'value' => 'email_opened',
                        'type'  => 'dates',
                    ]
                ]
            ]
        ];

        if ($customFields = fluentcrm_get_custom_contact_fields()) {
            // form data for custom fields in groups
            $children = [];
            foreach ($customFields as $field) {
                $item = [
                    'label' => $field['label'],
                    'value' => $field['slug'],
                    'type'  => $field['type'],
                ];

                if ($item['type'] == 'number') {
                    $item['type'] = 'numeric';
                } else if ($item['type'] == 'date') {
                    $item['type'] = 'dates';
                    $item['date_type'] = 'date';
                    $item['value_format'] = 'yyyy-MM-dd';
                } else if ($item['type'] == 'date_time') {
                    $item['type'] = 'dates';
                    $item['has_time'] = 'yes';
                    $item['date_type'] = 'datetime';
                    $item['value_format'] = 'yyyy-MM-dd HH:mm:ss';
                } else if (isset($field['options'])) {
                    $item['type'] = 'selections';
                    $options = $field['options'];
                    $formattedOptions = [];
                    foreach ($options as $option) {
                        $formattedOptions[$option] = $option;
                    }
                    $item['options'] = $formattedOptions;
                    $isMultiple = in_array($field['type'], ['checkbox', 'select-multi']);
                    $item['is_multiple'] = $isMultiple;
                    if ($isMultiple) {
                        $item['is_singular_value'] = true;
                    }
                } else {
                    $item['type'] = 'extended_text';
                }

                $children[] = $item;

            }

            $groups['custom_fields'] = [
                'label'    => __('Custom Fields', 'fluentcampaign-pro'),
                'value'    => 'custom_fields',
                'children' => $children
            ];
        }

        $groups = apply_filters('fluentcrm_automation_condition_groups', $groups, $funnel);
        $otherConditions = apply_filters('fluentcrm_automation_custom_conditions', [], $funnel);

        if (!empty($otherConditions)) {
            $groups['other'] = [
                'label'    => __('Other', 'fluentcampaign-pro'),
                'value'    => 'other',
                'children' => $otherConditions
            ];
        }

        return array_values($groups);
    }

}
