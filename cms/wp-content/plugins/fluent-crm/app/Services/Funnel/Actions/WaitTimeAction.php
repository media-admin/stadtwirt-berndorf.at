<?php

namespace FluentCrm\App\Services\Funnel\Actions;

use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\Framework\Support\Arr;

class WaitTimeAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'fluentcrm_wait_times';
        $this->priority = 10;
        parent::__construct();

        add_filter('fluentcrm_funnel_sequence_filtered_' . $this->actionName, array($this, 'gettingAction'), 10, 2);
    }

    public function getBlock()
    {
        return [
            'category'    => __('CRM', 'fluent-crm'),
            'title'       => __('Wait X Days/Hours', 'fluent-crm'),
            'description' => __('Wait defined timespan before execute the next action', 'fluent-crm'),
            'icon'        => 'fc-icon-wait_time',
            'settings'    => [
                'wait_type'         => 'unit_wait',
                'wait_time_amount'  => '',
                'wait_time_unit'    => 'days',
                'is_timestamp_wait' => '',
                'wait_date_time'    => '',
                'to_day'            => [],
                'to_day_time'       => ''
            ]
        ];
    }

    public function gettingAction($sequence, $funnel)
    {
        if (empty($sequence['settings']['wait_type'])) {
            if (Arr::get($sequence, 'settings.is_timestamp_wait') == 'yes') {
                $sequence['settings']['wait_type'] = 'timestamp_wait';
            } else {
                $sequence['settings']['wait_type'] = 'unit_wait';
            }

            $sequence['settings']['to_day'] = [];
            $sequence['settings']['to_day_time'] = '';
        }

        if (!empty($sequence['settings']['to_day'])) {
            $sequence['settings']['to_day'] = array_map(function ($day) {
                return substr($day, 0, 3);
            }, $sequence['settings']['to_day']);
        }

        return $sequence;
    }

    public function getBlockFields()
    {

        $customFields = FluentCrmApi('contacts')->getCustomFields(['date_time', 'date'], true);

        // add date of birth field at the beginning
        array_unshift($customFields, [
            'id'    => '__date_of_birth__',
            'title' => __('Contact\'s Next Date of Birth', 'fluent-crm')
        ]);

        return [
            'title'     => __('Wait X Days/Hours', 'fluent-crm'),
            'sub_title' => __('Wait defined timespan before execute the next action', 'fluent-crm'),
            'fields'    => [
                'wait_type'        => [
                    'type'    => 'radio_buttons',
                    'label'   => __('Waiting Type', 'fluent-crm'),
                    'options' => [
                        [
                            'id'    => 'unit_wait',
                            'title' => __('Wait by period', 'fluent-crm')
                        ],
                        [
                            'id'    => 'timestamp_wait',
                            'title' => __('Wait Until Date', 'fluent-crm')
                        ],
                        [
                            'id'    => 'to_day',
                            'title' => __('Wait by Weekday', 'fluent-crm')
                        ],
                        [
                            'id'    => 'by_custom_field',
                            'title' => __('Wait by Custom Field', 'fluent-crm')
                        ]
                    ]
                ],
                'wait_time_amount' => [
                    'label'         => __('Wait Time', 'fluent-crm'),
                    'type'          => 'input-number',
                    'wrapper_class' => 'fc_2col_inline pad-r-20',
                    'dependency'    => [
                        'depends_on' => 'wait_type',
                        'value'      => 'unit_wait',
                        'operator'   => '=',
                    ],
                ],
                'wait_time_unit'   => [
                    'label'         => __('Wait Time Unit', 'fluent-crm'),
                    'type'          => 'select',
                    'wrapper_class' => 'fc_2col_inline',
                    'options'       => [
                        [
                            'id'    => 'months',
                            'title' => __('Months', 'fluent-crm')
                        ],
                        [
                            'id'    => 'days',
                            'title' => __('Days', 'fluent-crm')
                        ],
                        [
                            'id'    => 'hours',
                            'title' => __('Hours', 'fluent-crm')
                        ],
                        [
                            'id'    => 'minutes',
                            'title' => __('Minutes', 'fluent-crm')
                        ]
                    ],
                    'dependency'    => [
                        'depends_on' => 'wait_type',
                        'value'      => 'unit_wait',
                        'operator'   => '=',
                    ],
                ],
                'wait_date_time'   => [
                    'label'       => __('Specify Date and Time', 'fluent-crm'),
                    'type'        => 'date_time',
                    'placeholder' => __('Select Date & Time', 'fluent-crm'),
                    'inline_help' => __('Please input date and time and this step will be executed after that time (TimeZone will be as per your WordPress Date Time Zone)', 'fluent-crm'),
                    'dependency'  => [
                        'depends_on' => 'wait_type',
                        'value'      => 'timestamp_wait',
                        'operator'   => '=',
                    ]
                ],
                'to_day'           => [
                    'type'          => 'checkboxes',
                    'label'         => __('Wait until next day(s) of the week', 'fluent-crm'),
                    'wrapper_class' => 'fc_2col_inline pad-r-20',
                    'options'       => [
                        [
                            'id'    => 'Mon',
                            'title' => __('Mon', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Tue',
                            'title' => __('Tue', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Wed',
                            'title' => __('Wed', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Thu',
                            'title' => __('Thu', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Fri',
                            'title' => __('Fri', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Sat',
                            'title' => __('Sat', 'fluent-crm')
                        ],
                        [
                            'id'    => 'Sun',
                            'title' => __('Sun', 'fluent-crm')
                        ]
                    ],
                    'dependency'    => [
                        'depends_on' => 'wait_type',
                        'value'      => 'to_day',
                        'operator'   => '=',
                    ]
                ],
                'to_day_time'      => [
                    'label'          => __('Time of the day', 'fluent-crm'),
                    'type'           => 'time_selector',
                    'placeholder'    => __('Select Time', 'fluent-crm'),
                    'wrapper_class'  => 'fc_2col_inline',
                    'picker_options' => [
                        'start' => '00:00',
                        'step'  => '00:10',
                        'end'   => '23:59'
                    ],
                    'dependency'     => [
                        'depends_on' => 'wait_type',
                        'value'      => 'to_day',
                        'operator'   => '=',
                    ]
                ],
                'by_custom_field'   => [
                    'label'         => __('Select Contact\'s Custom Field', 'fluent-crm'),
                    'type'          => 'select',
                    'inline_help' => __('If no value is found in the contact\'s custom field or past date then it will wait only 1 minute by default', 'fluent-crm'),
                    'options'       => $customFields,
                    'dependency'    => [
                        'depends_on' => 'wait_type',
                        'value'      => 'by_custom_field',
                        'operator'   => '=',
                    ]
                ],
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        // No-op: delay is handled by the processor via sequence delay settings
    }
}
