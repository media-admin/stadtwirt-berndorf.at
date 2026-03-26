<?php

namespace FluentCampaign\App\Services;

use FluentCrm\Framework\Support\Arr;
use FluentCampaign\App\Models\RecurringCampaign;

class RecurringCampaignRunner
{

    public static function getNextScheduledAt($schedulingSettings, $cutSeconds = 1800)
    {
        $type = Arr::get($schedulingSettings, 'type');

        $validTypes = ['daily', 'weekly', 'monthly'];

        if (!in_array($type, $validTypes)) {
            return NULL;
        }

        $time = Arr::get($schedulingSettings, 'time', '00:00') . ':00';

        $currentTimeStamp = current_time('timestamp');

        switch ($type) {
            case 'daily':
                $nextDateTime = gmdate('Y-m-d', $currentTimeStamp) . ' ' . $time;
                if ((strtotime($nextDateTime) - $currentTimeStamp) > $cutSeconds) {
                    return $nextDateTime;
                }
                // let's make it to next day
                return gmdate('Y-m-d H:i:s', strtotime($nextDateTime) + 86400);

            case 'weekly':
                $selectedDay = strtolower(Arr::get($schedulingSettings, 'day', 'mon'));
                $currentDay = strtolower(gmdate('D', $currentTimeStamp));

                if ($selectedDay == $currentDay) {
                    // it's today
                    $nextDateTime = gmdate('Y-m-d', $currentTimeStamp) . ' ' . $time; // creates a full date time string at the specified time, if today is 2025-01-01 and time is 15:00, it will be 2025-01-01 15:00:00

                    if (strtotime($nextDateTime) - $currentTimeStamp > $cutSeconds) {
                        // compare the next date time with current timestamp to check if it's after the cut seconds ( 30 minutes )
                        return $nextDateTime; // it's after the 30 minutes
                    }
                }

                // if today is not the selected day, or it's before the cut seconds, we need to find/set the next occurrence of the selected day
                return gmdate('Y-m-d', strtotime('next ' . $selectedDay, $currentTimeStamp)) . ' ' . $time;

            case 'monthly':
                $selectedDay = Arr::get($schedulingSettings, 'day', '1');
                $currentDay = gmdate('j');

                if ($selectedDay == $currentDay) {
                    // it's today
                    $nextDateTime = gmdate('Y-m-d', $currentTimeStamp) . ' ' . $time;
                    if (strtotime($nextDateTime) - $currentTimeStamp > $cutSeconds) {
                        return $nextDateTime; // it's after the cut seconds
                    }
                } else if ($currentDay < $selectedDay) {
                    // add 0 if single digit
                    $selectedDay = str_pad($selectedDay, 2, '0', STR_PAD_LEFT);
                    return gmdate('Y-m-'.$selectedDay, $currentTimeStamp) . ' ' . $time;
                }

                $nextMonth = strtotime("first day of next month", $currentTimeStamp);
                $advanceDay = absint($selectedDay - 1);

                return gmdate('Y-m-d', $nextMonth + ($advanceDay * 86400)) . ' ' . $time;
        }

        return NULL;
    }

    public static function setCalculatedScheduledAt()
    {
        $nextItem = RecurringCampaign::orderBy('scheduled_at', 'ASC')
            ->where('status', 'active')
            ->whereNotNull('scheduled_at')
            ->first();

        if (!$nextItem) {
            update_option('_fc_next_recurring_campaign', false, 'no');
            return;
        }

        update_option('_fc_next_recurring_campaign', strtotime($nextItem->scheduled_at), 'no');
    }

    public static function assesCondition($condition)
    {
        $conditionalDateValue = gmdate('Y-m-d H:i:s', current_time('timestamp') - 2100 - (int)$condition['compare_value'] * 86400);

        if ($condition['object_type'] === 'cpt') {
            $exist = fluentcrmDb()->table('posts')
                ->where('post_type', $condition['object_name'])
                ->where('post_status', 'publish')
                ->where('post_date', '>', $conditionalDateValue)
                ->first();

            if ($exist) {
                return true;
            }
        }

        return false;
    }
}
