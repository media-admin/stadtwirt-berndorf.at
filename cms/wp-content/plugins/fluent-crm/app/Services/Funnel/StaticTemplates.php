<?php

namespace FluentCrm\App\Services\Funnel;

class StaticTemplates
{
    public static function get()
    {
        return [
            [
                'id'          => 1,
                'label'       => __('Welcome Email', 'fluent-crm'),
                'description' => __('Send a welcome email to new subscribers', 'fluent-crm'),
                'category'    => 'email',
                'icon'        => 'el-icon-message',
                'disabled'    => false,
                'depends_on'    => ['crm', 'email'],
                'ribbon'      => __('Free', 'fluent-crm'),
                'funnel_data' => [
                    "id"              => 20,
                    "type"            => "funnels",
                    "title"           => "List Applied (Created at 2024-07-03)",
                    "trigger_name"    => "fluentcrm_contact_added_to_lists",
                    "status"          => "published",
                    "conditions"      => [
                        "run_multiple" => "no",
                    ],
                    "settings"        => [
                        "lists"       => [
                        ],
                        "select_type" => "any",
                    ],
                    "created_by"      => "1",
                    "created_at"      => "2024-07-03 11:10:24",
                    "updated_at"      => "2024-07-03 17:56:07",
                    "settingsFields"  => [
                        "title"     => __("List Applied", 'fluent-crm'),
                        "sub_title" => __("This will run when selected lists have been applied to a contact", 'fluent-crm'),
                        "fields"    => [
                            "lists"       => [
                                "type"        => "option_selectors",
                                "option_key"  => "lists",
                                "is_multiple" => true,
                                "label"       => __("Select Lists", 'fluent-crm'),
                                "placeholder" => __("Select List", 'fluent-crm'),
                                "creatable"   => true,
                            ],
                            "select_type" => [
                                "label"      => __("Run When", 'fluent-crm'),
                                "type"       => "radio",
                                "options"    => [
                                    [
                                        "id"    => "any",
                                        "title" => __("contact added in any of the selected lists", 'fluent-crm'),
                                    ],
                                    [
                                        "id"    => "all",
                                        "title" => __("contact added in all of the selected lists", 'fluent-crm'),
                                    ],
                                ],
                                "dependency" => [
                                    "depends_on" => "lists",
                                    "operator"   => "!=",
                                    "value"      => [
                                    ],
                                ],
                            ],
                        ],
                    ],
                    "conditionFields" => [
                        "run_multiple" => [
                            "type"        => "yes_no_check",
                            "label"       => "",
                            "check_label" => __("Restart the Automation Multiple times for a contact for this event. (Only enable if you want to restart automation for the same contact)", 'fluent-crm'),
                            "inline_help" => __("If you enable, then it will restart the automation for a contact if the contact already in the automation. Otherwise, It will just skip if already exist", 'fluent-crm'),
                        ],
                    ],
                    "sequences"       => [
                        [
                            "id"             => 21,
                            "funnel_id"      => "20",
                            "parent_id"      => "0",
                            "action_name"    => "add_contact_to_company",
                            "condition_type" => null,
                            "type"           => "action",
                            "title"          => __("Apply Company", 'fluent-crm'),
                            "description"    => __("Add contact to the selected company", 'fluent-crm'),
                            "status"         => "published",
                            "conditions"     => [
                            ],
                            "settings"       => [
                                "company" => null,
                            ],
                            "note"           => null,
                            "delay"          => "0",
                            "c_delay"        => "0",
                            "sequence"       => "1",
                            "created_by"     => "1",
                            "created_at"     => "2024-07-03 17:55:55",
                            "updated_at"     => "2024-07-03 17:56:07",
                        ],
                        [
                            "id"             => 22,
                            "funnel_id"      => "20",
                            "parent_id"      => "0",
                            "action_name"    => "fluentcrm_wait_times",
                            "condition_type" => null,
                            "type"           => "action",
                            "title"          => __("Wait X Days/Hours", 'fluent-crm'),
                            "description"    => __("Wait defined timespan before execute the next action", 'fluent-crm'),
                            "status"         => "published",
                            "conditions"     => [
                            ],
                            "settings"       => [
                                "wait_type"         => "unit_wait",
                                "wait_time_amount"  => 0,
                                "wait_time_unit"    => "days",
                                "is_timestamp_wait" => "",
                                "wait_date_time"    => "",
                                "to_day"            => [
                                ],
                                "to_day_time"       => "",
                            ],
                            "note"           => null,
                            "delay"          => "0",
                            "c_delay"        => "0",
                            "sequence"       => "2",
                            "created_by"     => "1",
                            "created_at"     => "2024-07-03 17:56:01",
                            "updated_at"     => "2024-07-03 17:56:07",
                        ],
                    ],
                    "site_hash"       => "74401346bac500b8b0e449bdc75b2255",
                    "export_date"     => "2024-07-03 11:56:26",
                ],
            ],
        ];
    }

}
