<?php

namespace FluentCrm\App\Hooks\Handlers;

class FluentBlockPatternHandler
{
    public function shouldUnregisterAllPatterns($shouldUnregister, $context = '', $data = [])
    {
        return true;
    }

    public function addCustomPatternCategories($categories)
    {
        $categories[] = [
            'name'        => 'fcrm-email',
            'label'       => __('FluentCRM Email', 'fluent-crm'),
            'description' => __('Reusable email sections for FluentCRM editor.', 'fluent-crm')
        ];

        return $categories;
    }

    public function addCustomPatterns($patterns)
    {
        $patterns[] = [
            'name'       => 'fcrm/intro-cta',
            'title'      => __('Intro + CTA', 'fluent-crm'),
            'categories' => ['fcrm-email'],
            'keywords'   => ['intro', 'cta'],
            'content'    => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group has-theme-palette-color-8-background-color has-background" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:heading {"level":3} --><h3>' . esc_html__('Welcome to our newsletter', 'fluent-crm') . '</h3><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__('Share your main message here in one short paragraph.', 'fluent-crm') . '</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__('Get Started', 'fluent-crm') . '</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->'
        ];

        $patterns[] = [
            'name'       => 'fcrm/two-button-row',
            'title'      => __('Two Button Row', 'fluent-crm'),
            'categories' => ['fcrm-email'],
            'keywords'   => ['buttons', 'actions'],
            'content'    => '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . esc_html__('Choose an action:', 'fluent-crm') . '</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__('Primary Action', 'fluent-crm') . '</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__('Secondary Action', 'fluent-crm') . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
        ];

        $patterns[] = [
            'name'       => 'fcrm/feature-list',
            'title'      => __('Feature List', 'fluent-crm'),
            'categories' => ['fcrm-email'],
            'keywords'   => ['list', 'features'],
            'content'    => '<!-- wp:heading {"level":4} --><h4>' . esc_html__('Why people choose us', 'fluent-crm') . '</h4><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>' . esc_html__('Fast setup', 'fluent-crm') . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__('Simple workflow', 'fluent-crm') . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__('Better conversion', 'fluent-crm') . '</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
        ];

        $patterns[] = [
            'name'       => 'fcrm/event-reminder',
            'title'      => __('Event Reminder', 'fluent-crm'),
            'categories' => ['fcrm-email'],
            'keywords'   => ['event', 'reminder'],
            'content'    => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group" style="padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><!-- wp:heading {"level":4} --><h4>' . esc_html__('Reminder: Upcoming Event', 'fluent-crm') . '</h4><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__('Date: Monday, 10:00 AM', 'fluent-crm') . '<br>' . esc_html__('Location: Online', 'fluent-crm') . '</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__('Add to Calendar', 'fluent-crm') . '</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->'
        ];

        $patterns[] = [
            'name'       => 'fcrm/simple-footer-note',
            'title'      => __('Simple Footer Note', 'fluent-crm'),
            'categories' => ['fcrm-email'],
            'keywords'   => ['footer', 'note'],
            'content'    => '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator --><!-- wp:paragraph {"align":"center","fontSize":"small"} --><p class="has-text-align-center has-small-font-size">' . esc_html__('Need help? Reply to this email and our team will assist you.', 'fluent-crm') . '</p><!-- /wp:paragraph -->'
        ];

        return $patterns;
    }
}

