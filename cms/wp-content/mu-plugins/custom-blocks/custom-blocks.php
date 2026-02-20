<?php
/**
 * Plugin Name: Custom Blocks
 * Description: Shortcodes für Custom Components
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}



// ============================================
// ADMIN BUTTONS
// ============================================

add_action('media_buttons', 'add_shortcode_buttons');
function add_shortcode_buttons() {
    echo '<button type="button" class="button" id="insert-accordion">
        <span class="dashicons dashicons-list-view" style="margin-top: 3px;"></span> Accordion
    </button>';
    
    echo '<button type="button" class="button" id="insert-hero-slider" style="margin-left: 5px;">
        <span class="dashicons dashicons-images-alt2" style="margin-top: 3px;"></span> Hero Slider
    </button>';
    
    echo '<button type="button" class="button" id="insert-modal" style="margin-left: 5px;">
        <span class="dashicons dashicons-admin-page" style="margin-top: 3px;"></span> Modal
    </button>';

    echo '<button type="button" class="button" id="insert-testimonials" style="margin-left: 5px;">
        <span class="dashicons dashicons-star-filled" style="margin-top: 3px;"></span> Testimonials
    </button>';

    echo '<button type="button" class="button" id="insert-tabs" style="margin-left: 5px;">
        <span class="dashicons dashicons-index-card" style="margin-top: 3px;"></span> Tabs
    </button>';

    echo '<button type="button" class="button" id="insert-notification" style="margin-left: 5px;">
        <span class="dashicons dashicons-bell" style="margin-top: 3px;"></span> Notification
    </button>';

    echo '<button type="button" class="button" id="insert-notification" style="margin-left: 5px;">
        <span class="dashicons dashicons-megaphone" style="margin-top: 3px;"></span> Notification
    </button>';

    echo '<button type="button" class="button" id="insert-stats" style="margin-left: 5px;">
        <span class="dashicons dashicons-chart-bar" style="margin-top: 3px;"></span> Stats
    </button>'; 

    echo '<button type="button" class="button" id="insert-timeline" style="margin-left: 5px;">
        <span class="dashicons dashicons-backup" style="margin-top: 3px;"></span> Timeline
    </button>';

    echo '<button type="button" class="button" id="insert-image-comparison" style="margin-left: 5px;">
        <span class="dashicons dashicons-image-flip-horizontal" style="margin-top: 3px;"></span> Image Comparison
    </button>';

    echo '<button type="button" class="button" id="insert-logo-carousel" style="margin-left: 5px;">
        <span class="dashicons dashicons-images-alt" style="margin-top: 3px;"></span> Logo Carousel
    </button>';

    echo '<button type="button" class="button" id="insert-team-cards" style="margin-left: 5px;">
        <span class="dashicons dashicons-groups" style="margin-top: 3px;"></span> Team Cards
    </button>';

    echo '<button type="button" class="button" id="insert-video-player" style="margin-left: 5px;">
        <span class="dashicons dashicons-video-alt3" style="margin-top: 3px;"></span> Video Player
    </button>';

    echo '<button type="button" class="button" id="insert-faq" style="margin-left: 5px;">
        <span class="dashicons dashicons-editor-help" style="margin-top: 3px;"></span> FAQ
    </button>';

    echo '<button type="button" class="button" id="insert-cpt-query" style="margin-left: 5px;">
        <span class="dashicons dashicons-database" style="margin-top: 3px;"></span> CPT Query
    </button>';

    echo '<button type="button" class="button" id="insert-spoiler" style="margin-left: 5px;">
        <span class="dashicons dashicons-hidden" style="margin-top: 3px;"></span> Spoiler
    </button>';

    echo '<button type="button" class="button" id="insert-pricing" style="margin-left: 5px;">
        <span class="dashicons dashicons-tag" style="margin-top: 3px;"></span> Pricing
    </button>';

}

add_action('admin_footer', 'shortcode_buttons_js');
function shortcode_buttons_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Accordion
        $('#insert-accordion').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[accordion]
                [accordion_item title="Frage 1"]
                    Antwort 1
                [/accordion_item]
                [accordion_item title="Frage 2"]
                    Antwort 2
                [/accordion_item]
                [/accordion]`;
            insertShortcode(shortcode);
        });
        
        // Hero Slider
        $('#insert-hero-slider').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[hero_slider autoplay="true" loop="true"]\n' +
                '[hero_slide image="https://picsum.photos/1920/800?random=1" image_mobile="https://picsum.photos/800/1200?random=1" title="Willkommen" subtitle="Ihr Partner für digitale Lösungen" button_text="Mehr erfahren" button_link="#" text_align="left" text_color="white"]\n' +
                'Entdecken Sie unsere innovativen Dienstleistungen.\n' +
                '[/hero_slide]\n' +
                '[hero_slide image="https://picsum.photos/1920/800?random=2" image_mobile="https://picsum.photos/800/1200?random=2" title="Unsere Services" subtitle="Professionell & Zuverlässig" button_text="Services ansehen" button_link="#" text_align="center"]\n' +
                'Von Webdesign bis App-Entwicklung.\n' +
                '[/hero_slide]\n' +
                '[/hero_slider]';
            insertShortcode(shortcode);
        });
        
        // Modal
        $('#insert-modal').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[modal_trigger target="beispiel-modal"]Modal öffnen[/modal_trigger]
                [modal id="beispiel-modal" title="Modal Titel"]
                Ihr Modal-Inhalt hier...
                [/modal]`;
            insertShortcode(shortcode);
        });

        // Testimonials
        $('#insert-testimonials').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[testimonials columns="3" style="card"]
                [testimonial name="Max Mustermann" role="CEO" company="Firma GmbH" image="https://i.pravatar.cc/150?img=1" rating="5"]
                    Hervorragende Arbeit! Das Team hat unsere Erwartungen übertroffen.
                [/testimonial]
                [testimonial name="Anna Schmidt" role="Marketing Manager" company="StartUp AG" image="https://i.pravatar.cc/150?img=5" rating="5"]
                    Professionell, schnell und kreativ. Absolut empfehlenswert!
                [/testimonial]
                [testimonial name="Peter Müller" role="Geschäftsführer" image="https://i.pravatar.cc/150?img=12" rating="5"]
                    Beste Entscheidung für unser Projekt. Vielen Dank!
                [/testimonial]
                [/testimonials]`;
            insertShortcode(shortcode);
        });

        // Tabs
        $('#insert-tabs').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[tabs style="default"]
                [tab title="Tab 1" active="true"]
                    Inhalt des ersten Tabs.
                [/tab]
                [tab title="Tab 2"]
                    Inhalt des zweiten Tabs.
                [/tab]
                [tab title="Tab 3"]
                    Inhalt des dritten Tabs.
                [/tab]
                [/tabs]`;
            insertShortcode(shortcode);
        });

        // Notification
        $('#insert-notification').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[notification type="info" title="Information" dismissible="true"]
                Dies ist eine Info-Benachrichtigung. Sie können hier wichtige Informationen anzeigen.
                [/notification]`;
            insertShortcode(shortcode);
        });

        // Notifications
        $('#insert-notification').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[notification type="info" dismissible="true"]
                <strong>Hinweis:</strong> Dies ist eine Info-Benachrichtigung.
                [/notification]`;
            insertShortcode(shortcode);
        });

        // Stats
        $('#insert-stats').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[stats columns="4" style="default"]
                [stat number="1000" suffix="+" label="Kunden"]
                    Zufriedene Kunden weltweit
                [/stat]
                [stat number="250" suffix="+" label="Projekte"]
                    Erfolgreich abgeschlossen
                [/stat]
                [stat number="15" label="Jahre"]
                    Erfahrung im Markt
                [/stat]
                [stat number="98" suffix="%" label="Zufriedenheit"]
                    Kundenzufriedenheit
                [/stat]
                [/stats]`;
            insertShortcode(shortcode);
        });

        // Timeline
        $('#insert-timeline').on('click', function(e) {
            e.preventDefault();
            var shortcode = `[timeline style="alternate"]
                [timeline_item date="2020" title="Gründung" icon="dashicons-star-filled" color="primary"]
                    Unser Unternehmen wurde mit der Vision gegründet, innovative Lösungen zu schaffen.
                [/timeline_item]
                [timeline_item date="2021" title="Erstes Produkt" icon="dashicons-products" color="success"]
                    Launch unseres ersten erfolgreichen Produkts mit über 1000 Kunden.
                [/timeline_item]
                [timeline_item date="2022" title="Expansion" icon="dashicons-admin-site-alt3" color="info"]
                    Eröffnung von Niederlassungen in 3 weiteren Ländern.
                [/timeline_item]
                [timeline_item date="2023" title="Auszeichnung" icon="dashicons-awards" color="warning"]
                    Gewinner des Innovation Awards für beste Technologie.
                [/timeline_item]
                [/timeline]`;
            insertShortcode(shortcode);
        });

        // Image Comparison
        $('#insert-image-comparison').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[image_comparison before="https://picsum.photos/1200/675?random=1" after="https://picsum.photos/1200/675?random=2" before_label="Vorher" after_label="Nachher" position="50"]';
            insertShortcode(shortcode);
        });

        // Logo Carousel
        $('#insert-logo-carousel').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[logo_carousel autoplay="true" speed="3000" grayscale="true"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/667eea/ffffff?text=Logo+1" alt="Partner 1" link="https://example.com"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/764ba2/ffffff?text=Logo+2" alt="Partner 2" link="https://example.com"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/f093fb/ffffff?text=Logo+3" alt="Partner 3" link="https://example.com"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/4facfe/ffffff?text=Logo+4" alt="Partner 4" link="https://example.com"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/00f2fe/ffffff?text=Logo+5" alt="Partner 5" link="https://example.com"]\n' +
                '[logo_item image="https://via.placeholder.com/200x80/43e97b/ffffff?text=Logo+6" alt="Partner 6" link="https://example.com"]\n' +
                '[/logo_carousel]';
            insertShortcode(shortcode);
        });

        // Team Cards
        $('#insert-team-cards').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[team_cards columns="3" style="default"]\n' +
                '[team_member name="Max Mustermann" role="CEO & Gründer" image="https://i.pravatar.cc/400?img=12" email="max@example.com" linkedin="https://linkedin.com"]\n' +
                'Mit über 15 Jahren Erfahrung in der Tech-Branche führt Max unser Unternehmen in eine innovative Zukunft.\n' +
                '[/team_member]\n' +
                '[team_member name="Anna Schmidt" role="CTO" image="https://i.pravatar.cc/400?img=5" email="anna@example.com" linkedin="https://linkedin.com"]\n' +
                'Anna ist verantwortlich für unsere technische Strategie und leitet unser Entwicklerteam.\n' +
                '[/team_member]\n' +
                '[team_member name="Peter Müller" role="Head of Design" image="https://i.pravatar.cc/400?img=15" email="peter@example.com" twitter="https://twitter.com"]\n' +
                'Peter bringt kreative Visionen zum Leben und sorgt für außergewöhnliche User Experience.\n' +
                '[/team_member]\n' +
                '[/team_cards]';
            insertShortcode(shortcode);
        });

        // Video Player
        $('#insert-video-player').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[video_player url="https://www.youtube.com/watch?v=dQw4w9WgXcQ" type="youtube" title="Video Titel" poster="https://picsum.photos/1280/720?random=1"]\n' +
                'Optional: Beschreibungstext zum Video.\n' +
                '[/video_player]';
            insertShortcode(shortcode);
        });

        // FAQ Accordion
        $('#insert-faq').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[faq_accordion style="default" schema="true"]\n' +
                '[faq_item question="Wie kann ich bestellen?" open="true"]\n' +
                'Sie können ganz einfach über unseren Online-Shop bestellen. Wählen Sie Ihre Produkte aus und folgen Sie dem Checkout-Prozess.\n' +
                '[/faq_item]\n' +
                '[faq_item question="Welche Zahlungsmethoden akzeptieren Sie?"]\n' +
                'Wir akzeptieren Kreditkarten, PayPal, Sofortüberweisung und Rechnung.\n' +
                '[/faq_item]\n' +
                '[faq_item question="Wie lange dauert der Versand?"]\n' +
                'Standard-Versand dauert 3-5 Werktage. Express-Versand ist innerhalb von 1-2 Werktagen möglich.\n' +
                '[/faq_item]\n' +
                '[faq_item question="Kann ich meine Bestellung zurückgeben?"]\n' +
                'Ja, Sie haben ein 30-tägiges Rückgaberecht ab Erhalt der Ware.\n' +
                '[/faq_item]\n' +
                '[/faq_accordion]';
            insertShortcode(shortcode);
        });

        // CPT Query
        $('#insert-cpt-query').on('click', function(e) {
            e.preventDefault();
            var shortcode = '<!-- Team -->\n' +
                '[team_query number="3" columns="3" style="default"]\n\n' +
                '<!-- Projects -->\n' +
                '[projects_query number="6" columns="3"]\n\n' +
                '<!-- Testimonials -->\n' +
                '[testimonials_query number="3" columns="3" style="card"]\n\n' +
                '<!-- Services -->\n' +
                '[services_query number="-1" columns="3"]';
            insertShortcode(shortcode);
        });

        // Spoiler / Read-More
        $('#insert-spoiler').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[spoiler open_text="Mehr anzeigen" close_text="Weniger anzeigen"]\n' +
                'Dieser Inhalt ist standardmäßig versteckt und wird erst angezeigt, wenn der Benutzer auf den Button klickt.\n\n' +
                'Sie können hier beliebig viel Text, Bilder, Listen und andere Inhalte einfügen.\n' +
                '[/spoiler]';
            insertShortcode(shortcode);
        });

        // Pricing Tables
        $('#insert-pricing').on('click', function(e) {
            e.preventDefault();
            var shortcode = '[pricing_tables columns="3"]\n' +
                '[pricing_table title="Starter" price="29" period="pro Monat" button_text="Jetzt starten" button_link="#"]\n' +
                '[pricing_feature icon="check"]5 Projekte[/pricing_feature]\n' +
                '[pricing_feature icon="check"]10 GB Speicher[/pricing_feature]\n' +
                '[pricing_feature icon="check"]E-Mail Support[/pricing_feature]\n' +
                '[pricing_feature icon="cross"]Telefon Support[/pricing_feature]\n' +
                '[/pricing_table]\n\n' +
                '[pricing_table title="Professional" price="79" period="pro Monat" featured="true" badge="Beliebt" button_text="Jetzt starten" button_link="#"]\n' +
                '[pricing_feature icon="check"]Unbegrenzte Projekte[/pricing_feature]\n' +
                '[pricing_feature icon="check"]100 GB Speicher[/pricing_feature]\n' +
                '[pricing_feature icon="check"]E-Mail Support[/pricing_feature]\n' +
                '[pricing_feature icon="check"]Telefon Support[/pricing_feature]\n' +
                '[pricing_feature icon="check"]Priorität Support[/pricing_feature]\n' +
                '[/pricing_table]\n\n' +
                '[pricing_table title="Enterprise" price="199" period="pro Monat" button_text="Kontakt" button_link="/kontakt"]\n' +
                '[pricing_feature icon="check"]Alles aus Professional[/pricing_feature]\n' +
                '[pricing_feature icon="check"]Unbegrenzter Speicher[/pricing_feature]\n' +
                '[pricing_feature icon="check"]24/7 Support[/pricing_feature]\n' +
                '[pricing_feature icon="check"]Dedizierter Account Manager[/pricing_feature]\n' +
                '[pricing_feature icon="check"]Custom Integrationen[/pricing_feature]\n' +
                '[/pricing_table]\n' +
                '[/pricing_tables]';
            insertShortcode(shortcode);
        });


        
        function insertShortcode(shortcode) {
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);
            } else {
                var editor = $('#content');
                editor.val(editor.val() + shortcode);
            }
        }
    });
    </script>
    <?php
}