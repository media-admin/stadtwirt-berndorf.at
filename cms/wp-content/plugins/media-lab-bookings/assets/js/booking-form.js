/**
 * Media Lab Bookings – Frontend JS
 *
 * Ablauf:
 *  1. Standort wählen  → Öffnungstage + Dienstleistungen laden
 *  2. Datum wählen     → Flatpickr, geschlossene Wochentage deaktiviert
 *  3. Datum bestätigt  → Zeitslots laden
 *  4. Absenden         → AJAX, Erfolgsmeldung oder Fehleranzeige
 */

( function ( $, cfg ) {
    'use strict';

    // Kein Config-Objekt → abbrechen
    if ( ! cfg || ! cfg.ajaxUrl ) return;

    // ── Jede Formular-Instanz initialisieren ──────────────────────────────────
    $( '.mlb-booking-form' ).each( function () {
        new MLBForm( $( this ) );
    } );

    // ── Klasse pro Formular-Instanz ───────────────────────────────────────────
    function MLBForm( $wrap ) {

        var self = this;

        self.$wrap     = $wrap;
        self.$form     = $wrap.find( '.mlb-form' );
        self.$success  = $wrap.find( '.mlb-form__success' );
        self.$errGlobal = $wrap.find( '.mlb-form__error-global' );

        self.$locationSel = $wrap.find( '.mlb-location-select' );
        self.$datePicker  = $wrap.find( '.mlb-date-picker' );
        self.$timeSel     = $wrap.find( '.mlb-time-select' );
        self.$serviceSel  = $wrap.find( '.mlb-service-select' );
        self.$slotsInfo   = $wrap.find( '.mlb-slots-info' );
        self.$submitBtn   = $wrap.find( '.mlb-form__button' );

        self.flatpickrInstance = null;
        self.openWeekdays      = [];  // JS-Wochentage (0=So, 1=Mo, …)
        self.currentLocationId = 0;

        // Preset-Standort (via Shortcode-Attribut)?
        var preset = self.$locationSel.data( 'preset' );
        if ( preset ) {
            self.currentLocationId = parseInt( preset, 10 );
            self.loadLocationData( self.currentLocationId );
        }

        self.bindEvents();
    }

    // ── Events ────────────────────────────────────────────────────────────────

    MLBForm.prototype.bindEvents = function () {
        var self = this;

        // Standort wechseln
        self.$locationSel.on( 'change', function () {
            var locId = parseInt( $( this ).val(), 10 );
            if ( ! locId ) return;
            self.currentLocationId = locId;
            self.resetDate();
            self.loadLocationData( locId );
        } );

        // Formular absenden
        self.$form.on( 'submit', function ( e ) {
            e.preventDefault();
            self.submitBooking();
        } );
    };

    // ── Standortdaten laden (Wochentage + Services) ───────────────────────────

    MLBForm.prototype.loadLocationData = function ( locationId ) {
        var self = this;

        $.post( cfg.ajaxUrl, {
            action      : 'mlb_get_location_data',
            nonce       : cfg.nonce,
            location_id : locationId,
        }, function ( res ) {
            if ( ! res.success ) return;

            self.openWeekdays = res.data.open_weekdays || [];
            self.initDatePicker();
            self.populateServices( res.data.services || [] );
        } );
    };

    // ── Flatpickr initialisieren / neu initialisieren ─────────────────────────

    MLBForm.prototype.initDatePicker = function () {
        var self       = this;
        var today      = new Date();
        today.setHours( 0, 0, 0, 0 );

        // Bestehende Instanz zerstören
        if ( self.flatpickrInstance ) {
            self.flatpickrInstance.destroy();
        }

        self.flatpickrInstance = flatpickr( self.$datePicker[0], {
            locale        : 'de',
            dateFormat    : 'Y-m-d',
            altInput      : true,
            altFormat     : 'j. F Y',
            minDate       : 'today',
            disable       : [
                function ( date ) {
                    // Wochentage deaktivieren, die NICHT in openWeekdays sind
                    return self.openWeekdays.indexOf( date.getDay() ) === -1;
                }
            ],
            onReady: function( selectedDates, dateStr, fp ) {
                // Alt-Input erbt Klassen
                if ( fp.altInput ) {
                    fp.altInput.classList.add( 'mlb-form__input' );
                }
            },
            onChange: function ( selectedDates, dateStr ) {
                if ( dateStr ) {
                    self.loadSlots( dateStr );
                } else {
                    self.resetSlots();
                }
            },
        } );
    };

    // ── Dienstleistungen befüllen ─────────────────────────────────────────────

    MLBForm.prototype.populateServices = function ( services ) {
        var self = this;
        self.$serviceSel.empty();

        if ( ! services.length ) {
            self.$serviceSel.append( '<option value="">Keine Dienstleistungen verfügbar</option>' );
            return;
        }

        self.$serviceSel.append( '<option value="">Bitte wählen…</option>' );
        $.each( services, function ( i, s ) {
            var label = s.name + ( s.duration ? ' (' + s.duration + ' Min.)' : '' );
            self.$serviceSel.append(
                $( '<option>' ).val( s.name ).text( label )
            );
        } );
    };

    // ── Zeitslots laden ───────────────────────────────────────────────────────

    MLBForm.prototype.loadSlots = function ( date ) {
        var self = this;

        if ( ! self.currentLocationId ) {
            self.showSlotsInfo( cfg.i18n.selectLocation );
            return;
        }

        self.$timeSel.prop( 'disabled', true );
        self.$timeSel.html( '<option value="">Lädt…</option>' );
        self.$slotsInfo.text( '' );

        $.post( cfg.ajaxUrl, {
            action      : 'mlb_get_slots',
            nonce       : cfg.nonce,
            location_id : self.currentLocationId,
            date        : date,
        }, function ( res ) {
            if ( ! res.success ) {
                self.showSlotsInfo( res.data && res.data.message ? res.data.message : cfg.i18n.errorGeneral );
                self.$timeSel.html( '<option value="">—</option>' );
                return;
            }

            var slots = res.data.slots || [];

            if ( ! slots.length ) {
                var msg = res.data.message || cfg.i18n.noSlots;
                self.$timeSel.html( '<option value="">—</option>' );
                self.showSlotsInfo( msg );
                return;
            }

            self.$timeSel.empty();
            self.$timeSel.append( '<option value="">Uhrzeit wählen…</option>' );

            var availableCount = 0;
            $.each( slots, function ( i, slot ) {
                var $opt = $( '<option>' )
                    .val( slot.time )
                    .text( slot.label + ( ! slot.available ? ' – ' + cfg.i18n.booked : '' ) )
                    .prop( 'disabled', ! slot.available );

                if ( ! slot.available ) {
                    $opt.addClass( 'mlb-slot--booked' );
                } else {
                    availableCount++;
                }

                self.$timeSel.append( $opt );
            } );

            self.$timeSel.prop( 'disabled', false );
            self.showSlotsInfo( availableCount + ' von ' + slots.length + ' Slots verfügbar' );
        } );
    };

    // ── Slots zurücksetzen ────────────────────────────────────────────────────

    MLBForm.prototype.resetSlots = function () {
        this.$timeSel
            .html( '<option value="">Bitte zuerst Datum wählen</option>' )
            .prop( 'disabled', true );
        this.$slotsInfo.text( '' );
    };

    // ── Datum zurücksetzen ────────────────────────────────────────────────────

    MLBForm.prototype.resetDate = function () {
        if ( this.flatpickrInstance ) {
            this.flatpickrInstance.clear();
        }
        this.resetSlots();
    };

    // ── Info-Text unter Slot-Select ───────────────────────────────────────────

    MLBForm.prototype.showSlotsInfo = function ( msg ) {
        this.$slotsInfo.text( msg );
    };

    // ── Buchung absenden ──────────────────────────────────────────────────────

    MLBForm.prototype.submitBooking = function () {
        var self    = this;
        var $form   = self.$form;
        var $btn    = self.$submitBtn;

        // HTML5-Validierung
        if ( $form[0].checkValidity && ! $form[0].checkValidity() ) {
            $form[0].reportValidity();
            return;
        }

        // Standort + Datum + Zeit prüfen
        if ( ! self.currentLocationId ) {
            self.showError( cfg.i18n.selectLocation );
            return;
        }
        if ( ! $form.find( '[name="date"]' ).val() ) {
            self.showError( cfg.i18n.selectDate );
            return;
        }

        // DSGVO-Checkbox explizit prüfen + Inline-Fehler anzeigen
        var $privacyCheckbox = $form.find( '.mlb-privacy-checkbox' );
        var $privacyError    = $form.find( '[id$="-privacy-error"]' );
        if ( $privacyCheckbox.length && ! $privacyCheckbox.is( ':checked' ) ) {
            $privacyError.prop( 'hidden', false );
            $privacyCheckbox[0].focus();
            return;
        }
        $privacyError.prop( 'hidden', true );

        self.setLoading( true );
        self.hideError();

        var data = $form.serializeArray().reduce( function ( acc, item ) {
            acc[ item.name ] = item.value;
            return acc;
        }, {} );

        data.action      = 'mlb_submit_booking';
        data.nonce       = cfg.nonce;
        data.location_id = self.currentLocationId;

        $.post( cfg.ajaxUrl, data, function ( res ) {
            self.setLoading( false );

            if ( res.success ) {
                self.showSuccess( res.data.message );
            } else {
                var msg = ( res.data && res.data.message ) ? res.data.message : cfg.i18n.errorGeneral;
                self.showError( msg );
            }
        } ).fail( function () {
            self.setLoading( false );
            self.showError( cfg.i18n.errorGeneral );
        } );
    };

    // ── UI-Helfer ─────────────────────────────────────────────────────────────

    MLBForm.prototype.setLoading = function ( isLoading ) {
        this.$submitBtn
            .prop( 'disabled', isLoading )
            .toggleClass( 'mlb-form__button--loading', isLoading );
    };

    MLBForm.prototype.showSuccess = function ( message ) {
        this.$form.prop( 'hidden', true );
        this.$success.find( '.mlb-form__success-message' ).text( message );
        this.$success.prop( 'hidden', false );
        this.$wrap[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
    };

    MLBForm.prototype.showError = function ( message ) {
        this.$errGlobal
            .find( '.mlb-form__error-message' )
            .text( message );
        this.$errGlobal.prop( 'hidden', false );
        this.$errGlobal[0].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
    };

    MLBForm.prototype.hideError = function () {
        this.$errGlobal.prop( 'hidden', true );
    };

} )( jQuery, window.mlbConfig || null );
