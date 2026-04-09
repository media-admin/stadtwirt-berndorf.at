<?php
/**
 * Template: Buchungsformular v1.4.0
 * Variablen: $atts, $locations, $preset_location_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$loc_id = $preset_location_id ?: 0;
$labels = [
    'location'    => mlb_label( 'mlb_label_location',    'Standort wählen',        $loc_id ),
    'date'        => mlb_label( 'mlb_label_date',        'Datum',                   $loc_id ),
    'time'        => mlb_label( 'mlb_label_time',        'Uhrzeit',                 $loc_id ),
    'service'     => mlb_label( 'mlb_label_service',     'Dienstleistung',          $loc_id ),
    'persons'     => mlb_label( 'mlb_label_persons',     'Personenanzahl',          $loc_id ),
    'name'        => mlb_label( 'mlb_label_name',        'Vor- und Nachname',       $loc_id ),
    'email'       => mlb_label( 'mlb_label_email',       'E-Mail-Adresse',          $loc_id ),
    'phone'       => mlb_label( 'mlb_label_phone',       'Telefon',                 $loc_id ),
    'notes'       => mlb_label( 'mlb_label_notes',       'Anmerkungen',             $loc_id ),
    'submit'      => mlb_label( 'mlb_label_submit',      'Buchung anfragen',        $loc_id ),
    'privacy'     => mlb_label( 'mlb_label_privacy',     'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.', $loc_id ),
    'privacy_note'=> mlb_label( 'mlb_label_privacy_note', '', $loc_id ),
];

$wrapper_class = 'mlb-booking-form' . ( ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '' );
$form_id       = 'mlb-form-' . wp_unique_id();
$privacy_url   = get_privacy_policy_url();
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>" id="<?php echo esc_attr( $form_id ); ?>">

    <?php if ( ! empty( $atts['title'] ) ) : ?>
        <h2 class="mlb-booking-form__title"><?php echo esc_html( $atts['title'] ); ?></h2>
    <?php endif; ?>

    <form class="mlb-form" novalidate>
        <?php wp_nonce_field( 'mlb_nonce', 'mlb_nonce_field' ); ?>

        <!-- Standort -->
        <div class="mlb-form__step mlb-form__step--location <?php echo $preset_location_id ? 'mlb-form__step--hidden' : ''; ?>">
            <div class="mlb-form__field">
                <label for="<?php echo esc_attr( $form_id ); ?>-location" class="mlb-form__label mlb-form__label--required"><?php echo $labels['location']; ?></label>
                <select id="<?php echo esc_attr( $form_id ); ?>-location" name="location_id" class="mlb-form__select mlb-location-select" <?php echo $preset_location_id ? 'data-preset="' . esc_attr( $preset_location_id ) . '"' : 'required'; ?>>
                    <?php if ( ! $preset_location_id ) : ?><option value="">Bitte wählen…</option><?php endif; ?>
                    <?php foreach ( $locations as $loc ) : ?>
                        <option value="<?php echo esc_attr( $loc->ID ); ?>"<?php selected( $preset_location_id, $loc->ID ); ?>><?php echo esc_html( $loc->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ( $preset_location_id ) : ?>
            <input type="hidden" name="location_id" value="<?php echo esc_attr( $preset_location_id ); ?>">
        <?php endif; ?>

        <!-- Datum + Uhrzeit -->
        <div class="mlb-form__step mlb-form__step--datetime">
            <div class="mlb-form__row">
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-date" class="mlb-form__label mlb-form__label--required"><?php echo $labels['date']; ?></label>
                    <input type="text" id="<?php echo esc_attr( $form_id ); ?>-date" name="date" class="mlb-form__input mlb-date-picker" placeholder="Datum wählen" autocomplete="off" readonly required>
                </div>
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-time" class="mlb-form__label mlb-form__label--required"><?php echo $labels['time']; ?></label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-time" name="time" class="mlb-form__select mlb-time-select" required disabled>
                        <option value="">Bitte zuerst Datum wählen</option>
                    </select>
                    <div class="mlb-slots-info"></div>
                </div>
            </div>
        </div>

        <!-- Dienstleistung + Personen -->
        <div class="mlb-form__step mlb-form__step--service">
            <div class="mlb-form__row">
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-service" class="mlb-form__label"><?php echo $labels['service']; ?></label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-service" name="service" class="mlb-form__select mlb-service-select">
                        <option value="">Bitte zuerst Standort wählen</option>
                    </select>
                </div>
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-persons" class="mlb-form__label mlb-form__label--required"><?php echo $labels['persons']; ?></label>
                    <input type="number" id="<?php echo esc_attr( $form_id ); ?>-persons" name="persons" class="mlb-form__input" value="1" min="1" max="99" required>
                </div>
            </div>
        </div>

        <!-- Kontaktdaten -->
        <div class="mlb-form__step mlb-form__step--contact">
            <div class="mlb-form__field">
                <label for="<?php echo esc_attr( $form_id ); ?>-name" class="mlb-form__label mlb-form__label--required"><?php echo $labels['name']; ?></label>
                <input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name" class="mlb-form__input" autocomplete="name" required>
            </div>
            <div class="mlb-form__row" style="margin-top:16px">
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-email" class="mlb-form__label mlb-form__label--required"><?php echo $labels['email']; ?></label>
                    <input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" class="mlb-form__input" autocomplete="email" required>
                </div>
                <div class="mlb-form__field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-phone" class="mlb-form__label"><?php echo $labels['phone']; ?></label>
                    <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" class="mlb-form__input" autocomplete="tel">
                </div>
            </div>
            <div class="mlb-form__field" style="margin-top:16px">
                <label for="<?php echo esc_attr( $form_id ); ?>-notes" class="mlb-form__label"><?php echo $labels['notes']; ?></label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>-notes" name="notes" class="mlb-form__textarea" rows="4" placeholder="Haben Sie besondere Wünsche oder Anmerkungen?"></textarea>
            </div>
        </div>

        <!-- DSGVO -->
        <div class="mlb-form__step mlb-form__step--privacy">
            <div class="mlb-form__field mlb-form__field--checkbox">
                <label class="mlb-form__checkbox-label">
                    <input type="checkbox" name="privacy_consent" value="1" class="mlb-form__checkbox mlb-privacy-checkbox" required aria-required="true" aria-describedby="<?php echo esc_attr( $form_id ); ?>-privacy-error">
                    <span class="mlb-form__checkbox-text">
                        <?php
                        $privacy_text = $labels['privacy'];
                        if ( $privacy_url && strpos( $privacy_text, 'Datenschutzerklärung' ) !== false ) {
                            $link = '<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener noreferrer">Datenschutzerklärung</a>';
                            echo str_replace( 'Datenschutzerklärung', $link, $privacy_text );
                        } elseif ( $privacy_url ) {
                            echo esc_html( $privacy_text ) . ' (<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener noreferrer">Datenschutzerklärung</a>)';
                        } else {
                            echo esc_html( $privacy_text );
                        }
                        ?>
                        <span class="mlb-form__required-mark" aria-hidden="true"> *</span>
                    </span>
                </label>
                <div class="mlb-form__field-error" id="<?php echo esc_attr( $form_id ); ?>-privacy-error" role="alert" hidden>
                    Bitte stimmen Sie der Datenschutzerklärung zu, um fortzufahren.
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="mlb-form__submit">
            <button type="submit" class="mlb-form__button">
                <span class="mlb-form__button-text"><?php echo $labels['submit']; ?></span>
                <span class="mlb-form__button-spinner" aria-hidden="true"></span>
            </button>
            <?php if ( $labels['privacy_note'] ) : ?>
                <p class="mlb-form__privacy-note"><?php echo esc_html( $labels['privacy_note'] ); ?></p>
            <?php endif; ?>
        </div>
    </form>

    <!-- Erfolgsmeldung -->
    <div class="mlb-form__success" hidden>
        <div class="mlb-form__success-icon" aria-hidden="true">✓</div>
        <h3 class="mlb-form__success-title">Buchung eingereicht!</h3>
        <p class="mlb-form__success-message"></p>
        <a href="#" class="mlb-form__ical-link" hidden>Termin in Kalender speichern (.ics)</a>
    </div>

    <!-- Fehlermeldung -->
    <div class="mlb-form__error-global" role="alert" hidden>
        <p class="mlb-form__error-message"></p>
    </div>

</div>
