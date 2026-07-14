<?php

namespace FluentCrm\App\Modules\AbandonCart\Drivers\FluentCart;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class FluentCartAutomationTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fc_ab_cart_simulation_fluent_cart';
        $this->priority = 99;
        $this->actionArgNum = 1;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('FluentCart', 'fluent-crm'),
            'label'       => __('Cart Abandoned - FluentCart', 'fluent-crm'),
            'description' => __('This Funnel will be initiated when a cart has been abandoned in FluentCart', 'fluent-crm'),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><g id="surface1"><path style=" stroke:none;fill-rule:nonzero;fill:rgb(100%,100%,100%);fill-opacity:1;" d="M 2.398438 0 L 21.601562 0 C 22.925781 0 24 1.074219 24 2.398438 L 24 21.601562 C 24 22.925781 22.925781 24 21.601562 24 L 2.398438 24 C 1.074219 24 0 22.925781 0 21.601562 L 0 2.398438 C 0 1.074219 1.074219 0 2.398438 0 Z M 2.398438 0 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 10.925781 16.476562 L 3.769531 16.476562 L 4.894531 13.878906 C 5.222656 13.117188 5.972656 12.625 6.804688 12.625 L 15.328125 12.625 L 14.746094 13.964844 C 14.085938 15.488281 12.585938 16.476562 10.925781 16.476562 Z M 10.925781 16.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 16.851562 11.394531 L 6.789062 11.394531 L 7.367188 10.054688 C 8.027344 8.53125 9.53125 7.542969 11.191406 7.542969 L 19.886719 7.542969 L 18.761719 10.140625 C 18.433594 10.902344 17.683594 11.394531 16.851562 11.394531 Z M 16.851562 11.394531 "/></g></svg>'
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Cart Abandoned - FluentCart', 'fluent-crm'),
            'sub_title' => __('This Funnel will be initiated when a cart has been abandoned in FluentCart', 'fluent-crm'),
            'fields'    => [
                'priority' => [
                    'label'       => __('Priority of this abandon cart automation trigger', 'fluent-crm'),
                    'type'        => 'input-number',
                    'placeholder' => __('Automation Priority', 'fluent-crm'),
                    'inline_help' => __('If you have multiple automations for abandoned cart, you can set the priority. The higher the priority means it will match earlier. Only one abandoned cart automation will run per abandonment depending on your conditional logic.', 'fluent-crm')
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
        if (!defined('FLUENTCAMPAIGN_DIR_FILE')) {
            $cartConditionField = [
                'type'  => 'html',
                'label' => '',
                'info'  => '<h4 style="margin: 0; padding: 0;">Conditions by Cart Items</h4><div style="background-color: #FFF3DC !important;border-color: #FFF3DC; padding: 15px; line-height: 120%;">' . __('FluentCRM Pro plugin is required to use the conditional logic for this trigger. Please install and activate FluentCRM Pro to use this feature.', 'fluent-crm') . '</div>'
            ];
        } else {
            $cartConditionField = [
                'type'        => 'condition_block_groups',
                'label'       => __('Specify Matching Conditions', 'fluent-crm'),
                'inline_help' => __('Specify which contact properties need to be matched. If the conditions match then the automation will run.', 'fluent-crm'),
                'labels'      => [
                    'match_type_all_label' => __('True if all conditions match', 'fluent-crm'),
                    'match_type_any_label' => __('True if any of the conditions match', 'fluent-crm'),
                    'data_key_label'       => __('Contact Data', 'fluent-crm'),
                    'condition_label'      => __('Condition', 'fluent-crm'),
                    'data_value_label'     => __('Match Value', 'fluent-crm')
                ],
                'groups'      => $this->getConditionGroups($funnel),
                'add_label'   => __('Add Condition to check your contact\'s properties', 'fluent-crm'),
            ];
        }

        $fields = [
            'cart_conditions'    => $cartConditionField,
            'active_once'        => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Skip this automation if the contact is already in active state.', 'fluent-crm'),
                'inline_help' => __('Enable this to prevent the automation from running multiple times for the same contact if it is currently active in this automation', 'fluent-crm')
            ],
            'require_subscribed' => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Only run this automation for subscribed contacts', 'fluent-crm'),
                'inline_help' => __('If you enable, then it will only run this automation for subscribed contacts', 'fluent-crm')
            ]
        ];


        return $fields;
    }

    public function handle($funnel, $originalArgs)
    {
        // do nothing here - cart processing is handled by AbandonCartRunner
    }

    public function getConditionGroups($funnel)
    {
        $groups = [
            'ab_cart_fluent_cart' => [
                'label'    => __('Cart Data', 'fluent-crm'),
                'value'    => 'ab_cart_fluent_cart',
                'children' => [
                    [
                        'label' => __('Cart Total', 'fluent-crm'),
                        'value' => 'cart_total',
                        'type'  => 'numeric'
                    ],
                    [
                        'label' => __('Cart Items Count', 'fluent-crm'),
                        'value' => 'cart_items_count',
                        'type'  => 'numeric'
                    ],
                    [
                        'label'       => __('Cart Items', 'fluent-crm'),
                        'value'       => 'cart_items',
                        'type'        => 'selections',
                        'component'   => 'ajax_selector',
                        'option_key'  => 'fluent_cart_products',
                        'is_multiple' => true,
                        'help'        => __('Match the products on the cart', 'fluent-crm')
                    ],
                    [
                        'label'       => __('Cart Items Categories', 'fluent-crm'),
                        'value'       => 'cart_items_categories',
                        'type'        => 'selections',
                        'component'   => 'tax_selector',
                        'taxonomy'    => 'product-categories',
                        'is_multiple' => true,
                        'help'        => __('Match the product categories on the cart', 'fluent-crm')
                    ],
                ]
            ],
            'subscriber'          => [
                'label'    => __('Contact', 'fluent-crm'),
                'value'    => 'subscriber',
                'children' => [
                    [
                        'label' => __('First Name', 'fluent-crm'),
                        'value' => 'first_name',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Last Name', 'fluent-crm'),
                        'value' => 'last_name',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Email', 'fluent-crm'),
                        'value' => 'email',
                        'type'  => 'extended_text'
                    ],
                    [
                        'label'             => __('Country', 'fluent-crm'),
                        'value'             => 'country',
                        'type'              => 'selections',
                        'component'         => 'options_selector',
                        'option_key'        => 'countries',
                        'is_multiple'       => true,
                        'is_singular_value' => true
                    ],
                    [
                        'label' => __('Phone', 'fluent-crm'),
                        'value' => 'phone',
                        'type'  => 'nullable_text'
                    ],
                    [
                        'label' => __('Created At', 'fluent-crm'),
                        'value' => 'created_at',
                        'type'  => 'dates',
                    ]
                ],
            ],
            'segment'             => [
                'label'    => __('Contact Segment', 'fluent-crm'),
                'value'    => 'segment',
                'children' => [
                    [
                        'label'       => __('Tags', 'fluent-crm'),
                        'value'       => 'tags',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'tags',
                        'is_multiple' => true,
                    ],
                    [
                        'label'       => __('Lists', 'fluent-crm'),
                        'value'       => 'lists',
                        'type'        => 'selections',
                        'component'   => 'options_selector',
                        'option_key'  => 'lists',
                        'is_multiple' => true,
                    ],
                    [
                        'label'             => __('WP User Role', 'fluent-crm'),
                        'value'             => 'user_role',
                        'type'              => 'selections',
                        'is_singular_value' => true,
                        'options'           => FunnelHelper::getUserRoles(true),
                        'is_multiple'       => true,
                    ]
                ],
            ],
        ];

        if ($customFields = fluentcrm_get_custom_contact_fields()) {
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
                    $item['value_format'] = 'YYYY-MM-DD';
                } else if ($item['type'] == 'date_time') {
                    $item['type'] = 'dates';
                    $item['has_time'] = 'yes';
                    $item['date_type'] = 'datetime';
                    $item['value_format'] = 'YYYY-MM-DD HH:mm:ss';
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
                'label'    => __('Custom Fields', 'fluent-crm'),
                'value'    => 'custom_fields',
                'children' => $children
            ];
        }

        $groups = apply_filters('fluentcrm_automation_condition_groups', $groups, $funnel);

        return array_values($groups);
    }
}
