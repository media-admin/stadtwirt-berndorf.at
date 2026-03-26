<?php

namespace FluentCampaign\App\Modules\AbandonCart;

use FluentCampaign\App\Services\Funnel\Conditions\FunnelConditionHelper;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\App\Services\Libs\ConditionAssessor;
use FluentCrm\Framework\Support\Arr;

class AbandonCartRunner
{
    public function runAbandonCart(AbandonCartModel $abandonCart)
    {
        if ($abandonCart->status != 'draft') {
            return false;
        }

        if (AbCartHelper::isWooWithinCoolOffPeriod($abandonCart)) {
            $abandonCart->status = 'skipped';
            $abandonCart->note = 'Under Cool Off Period';
            $abandonCart->save();
            return $abandonCart;
        }

        $automationData = $this->getEligibleAutomation($abandonCart);

        if (!$automationData) {
            $abandonCart->status = 'skipped';
            $abandonCart->note = 'No automation found for this cart';
            $abandonCart->save();
            return $abandonCart;
        }

        if (is_wp_error($automationData)) {
            $abandonCart->status = 'skipped';
            $abandonCart->note = $automationData->get_error_message();
            $abandonCart->save();
            return $abandonCart;
        }

        $automation = $automationData['automation'];
        $contact = $automationData['contact'];

        if (!$contact) {
            $contact = $this->createContactFromCart($abandonCart);
        }

        // Check if exit
        $existingFunnelSub = FunnelSubscriber::where('funnel_id', $automation->id)
            ->where('subscriber_id', $contact->id)
            ->first();

        if ($existingFunnelSub) {
            FunnelMetric::where('funnel_id', $existingFunnelSub->funnel_id)
                ->where('subscriber_id', $contact->id)
                ->delete();

            $existingFunnelSub->delete();
        }

        $settings = AbCartHelper::getSettings();
        if ($attachLists = Arr::get($settings, 'lists_on_cart_abandoned', [])) {
            $contact->attachLists($attachLists);
        }

        if ($attachTags = Arr::get($settings, 'tags_on_cart_abandoned', [])) {
            $contact->attachTags($attachTags);
        }

        $abandonCart->status = 'processing';
        $abandonCart->automation_id = $automation->id;
        $abandonCart->contact_id = $contact->id;
        $abandonCart->abandoned_at = current_time('mysql');
        $abandonCart->save();

        (new FunnelProcessor())->startFunnelSequence($automation, [], [
            'source_trigger_name' => 'fc_ab_cart_simulation_' . $abandonCart->provider,
            'source_ref_id'       => $abandonCart->id
        ], $contact);

        return $abandonCart;
    }

    public function getEligibleAutomation(AbandonCartModel $abandonCart)
    {
        $automations = AbCartHelper::getSortedAutomations($abandonCart->provider);

        if (!$automations) {
            return new \WP_Error('no_automation', 'No automation found for this cart');
        }

        $existingContact = fluentCrmApi('contacts')->getContact($abandonCart->email);

        $processableStatuses = ['subscribed', 'transactional'];

        if ($existingContact && !in_array($existingContact->status, $processableStatuses)) {
            return new \WP_Error('contact_unsubscribed', 'Contact status is not allowed to process this cart');
        }

        $items = Arr::get($abandonCart->cart, 'cart_contents', []);

        $productIds = [];
        $categoryIds = [];

        foreach ($items as $item) {
            $productIds[] = $item['product_id'];
        }

        if ($productIds) {
            $cats = fluentCrmDb()->table('term_relationships')
                ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
                ->whereIn('term_relationships.object_id', $productIds)
                ->where('term_taxonomy.taxonomy', 'product_cat')
                ->select('term_taxonomy.term_id')
                ->get();

            foreach ($cats as $cat) {
                $categoryIds[] = $cat->term_id;
            }
        }

        $cartData = [
            'cart_total'            => $abandonCart->total,
            'cart_items_count'      => count($items),
            'cart_items'            => $productIds,
            'cart_items_categories' => $categoryIds,
        ];

        $contact = $existingContact;

        foreach ($automations as $automation) {
            $conditions = (array)$automation->conditions;
            if (Arr::get($conditions, 'require_subscribed') === 'yes' && (!$existingContact || $existingContact->status != 'subscribed')) {
                continue;
            }

            $existingFunnelSub = null;
            $checkActive = Arr::get($conditions, 'active_once') === 'yes';
            if ($checkActive && $existingContact) {
                // check if the contact is already has an automation for this one
                $existingFunnelSub = FunnelSubscriber::where('funnel_id', $automation->id)
                    ->where('subscriber_id', $existingContact->id)
                    ->first();

                if ($existingFunnelSub && $existingFunnelSub->status == 'active') {
                    continue;
                }
            }

            $cartConditions = array_filter(Arr::get($conditions, 'cart_conditions', []));

            if (!$cartConditions) {
                return [
                    'automation' => $automation,
                    'contact'    => $contact
                ];
            }

            if (!$contact && $this->hasContactConditions($cartConditions)) {
                $contact = $this->createContactFromCart($abandonCart);
            }

            if ($this->assessConditionGroups($cartConditions, $contact, $cartData)) {
                return [
                    'automation' => $automation,
                    'contact'    => $contact
                ];
            }
        }

        return new \WP_Error('no_automation', 'No automation found for this cart based on condition match');
    }

    protected function createContactFromCart(AbandonCartModel $abandonCart)
    {
        $customData = Arr::get($abandonCart->cart, 'customer_data', []);
        $cartSettings = AbCartHelper::getSettings();
        $contactData = array_filter([
            'email'          => $abandonCart->email,
            'first_name'     => Arr::get($customData, 'billingAddress.first_name'),
            'last_name'      => Arr::get($customData, 'billingAddress.last_name'),
            'user_id'        => $abandonCart->user_id,
            'full_name'      => $abandonCart->full_name,
            'status'         => Arr::get($cartSettings, 'new_contact_status', 'transactional'),
            'address_line_1' => Arr::get($customData, 'billingAddress.address_1'),
            'address_line_2' => Arr::get($customData, 'billingAddress.address_2'),
            'city'           => Arr::get($customData, 'billingAddress..city'),
            'state'          => Arr::get($customData, 'billingAddress.state'),
            'postal_code'    => Arr::get($customData, 'billingAddress.postcode'),
            'country'        => Arr::get($customData, 'billingAddress.country'),
            'phone'          => Arr::get($customData, 'billingAddress.phone'),
            'tags'           => Arr::get($cartSettings, 'tags_on_cart_abandoned', []),
            'lists'          => Arr::get($cartSettings, 'lists_on_cart_abandoned', [])
        ]);


        return fluentCrmApi('contacts')->createOrUpdate($contactData);
    }

    protected function hasContactConditions($conditions)
    {
        foreach ($conditions as $conditionGroup) {
            foreach ($conditionGroup as $filterItem) {
                if (count($filterItem['source']) != 2 || empty($filterItem['source'][0]) || empty($filterItem['source'][1]) || empty($filterItem['operator'])) {
                    continue;
                }

                $provider = $filterItem['source'][0];

                if ($provider != 'ab_cart_woo') {
                    return true;
                }
            }
        }

        return false;
    }

    protected function assessConditionGroups($conditionGroups, $subscriber, $cartData = [])
    {
        foreach ($conditionGroups as $conditions) {
            $result = $this->assesConditions($conditions, $subscriber, $cartData);
            if ($result) {
                return true;
            }
        }

        return false;
    }

    protected function assesConditions($conditions, $subscriber, $cartData = [])
    {
        $formattedGroups = FunnelConditionHelper::formatConditionGroups($conditions);

        foreach ($formattedGroups as $groupName => $group) {
            if ($groupName == 'subscriber') {

                if (!$subscriber) {
                    return false;
                }

                $subscriberData = $subscriber->toArray();
                if (!ConditionAssessor::matchAllConditions($group, $subscriberData)) {
                    return false;
                }
            } else if ($groupName == 'custom_fields') {
                if (!$subscriber) {
                    return false;
                }
                $customData = $subscriber->custom_fields();
                if (!ConditionAssessor::matchAllConditions($group, $customData)) {
                    return false;
                }
            } else if ($groupName == 'segment') {
                if (!$subscriber) {
                    return false;
                }
                if (!FunnelConditionHelper::assessSegmentConditions($group, $subscriber)) {
                    return false;
                }
            } else if ($groupName == 'activities') {
                if (!$subscriber) {
                    return false;
                }
                if (!FunnelConditionHelper::assessActivities($group, $subscriber)) {
                    return false;
                }
            } else if ($groupName == 'event_tracking') {
                if (!$subscriber) {
                    return false;
                }
                if (!FunnelConditionHelper::assessEventTrackingConditions($group, $subscriber)) {
                    return false;
                }
            } else if ($groupName == 'other') {
                if (!$subscriber) {
                    return false;
                }
                foreach ($group as $condition) {
                    $prop = $condition['data_key'];
                    if (!apply_filters('fluentcrm_automation_custom_condition_assert_' . $prop, true, $condition, $subscriber, null, null)) {
                        return false;
                    }
                }
            } else if ($groupName == 'ab_cart_woo') {
                if (!ConditionAssessor::matchAllConditions($group, $cartData)) {
                    return false;
                }
            } else {
                if (!$subscriber) {
                    return false;
                }

                $result = apply_filters("fluentcrm_automation_conditions_assess_$groupName", true, $group, $subscriber, null, null);
                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }
}
