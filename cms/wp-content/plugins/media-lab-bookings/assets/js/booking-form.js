/**
 * Media Lab Bookings – Frontend JS v1.4.0
 */
( function ( $, cfg ) {
    'use strict';
    if ( ! cfg || ! cfg.ajaxUrl ) return;

    function MLBForm( $wrap ) {
        var self = this;
        self.$wrap         = $wrap;
        self.$form         = $wrap.find( '.mlb-form' );
        self.$success      = $wrap.find( '.mlb-form__success' );
        self.$errGlobal    = $wrap.find( '.mlb-form__error-global' );
        self.$locationSel  = $wrap.find( '.mlb-location-select' );
        self.$datePicker   = $wrap.find( '.mlb-date-picker' );
        self.$timeSel      = $wrap.find( '.mlb-time-select' );
        self.$serviceSel   = $wrap.find( '.mlb-service-select' );
        self.$slotsInfo    = $wrap.find( '.mlb-slots-info' );
        self.$submitBtn    = $wrap.find( '.mlb-form__button' );
        self.flatpickrInstance = null;
        self.openWeekdays      = [];
        self.currentLocationId = 0;

        var preset = self.$locationSel.data( 'preset' );
        if ( preset ) {
            self.currentLocationId = parseInt( preset, 10 );
            self.loadLocationData( self.currentLocationId );
        }
        self.bindEvents();
    }

    MLBForm.prototype.bindEvents = function () {
        var self = this;
        self.$locationSel.on( 'change', function () {
            var locId = parseInt( $( this ).val(), 10 );
            if ( ! locId ) return;
            self.currentLocationId = locId;
            self.resetDate();
            self.loadLocationData( locId );
        } );
        self.$form.on( 'submit', function ( e ) {
            e.preventDefault();
            self.submitBooking();
        } );
    };

    MLBForm.prototype.loadLocationData = function ( locationId ) {
        var self = this;
        $.post( cfg.ajaxUrl, {
            action: 'mlb_get_location_data', nonce: cfg.nonce, location_id: locationId
        }, function ( res ) {
            if ( ! res.success ) return;
            self.openWeekdays = res.data.open_weekdays || [];
            self.initDatePicker();
            self.populateServices( res.data.services || [] );
        } );
    };

    MLBForm.prototype.initDatePicker = function () {
        var self = this;
        if ( self.flatpickrInstance ) self.flatpickrInstance.destroy();
        self.flatpickrInstance = flatpickr( self.$datePicker[0], {
            locale     : 'de',
            dateFormat : 'Y-m-d',
            altInput   : true,
            altFormat  : 'j. F Y',
            minDate    : 'today',
            disable    : [ function ( date ) { return self.openWeekdays.indexOf( date.getDay() ) === -1; } ],
            onReady    : function ( s, d, fp ) { if ( fp.altInput ) fp.altInput.classList.add( 'mlb-form__input' ); },
            onChange   : function ( selectedDates, dateStr ) {
                dateStr ? self.loadSlots( dateStr ) : self.resetSlots();
            },
        } );
    };

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
            self.$serviceSel.append( $( '<option>' ).val( s.name ).text( label ) );
        } );
    };

    MLBForm.prototype.loadSlots = function ( date ) {
        var self = this;
        if ( ! self.currentLocationId ) { self.showSlotsInfo( cfg.i18n.selectLocation ); return; }
        self.$timeSel.prop( 'disabled', true ).html( '<option value="">Lädt…</option>' );
        self.$slotsInfo.text( '' );
        $.post( cfg.ajaxUrl, {
            action: 'mlb_get_slots', nonce: cfg.nonce, location_id: self.currentLocationId, date: date
        }, function ( res ) {
            if ( ! res.success ) {
                self.showSlotsInfo( res.data && res.data.message ? res.data.message : cfg.i18n.errorGeneral );
                self.$timeSel.html( '<option value="">—</option>' );
                return;
            }
            var slots = res.data.slots || [];
            if ( ! slots.length ) {
                self.$timeSel.html( '<option value="">—</option>' );
                self.showSlotsInfo( res.data.message || cfg.i18n.noSlots );
                return;
            }
            self.$timeSel.empty().append( '<option value="">Uhrzeit wählen…</option>' );
            var availableCount = 0;
            $.each( slots, function ( i, slot ) {
                var $opt = $( '<option>' )
                    .val( slot.time )
                    .text( slot.label + ( ! slot.available ? ' – ' + cfg.i18n.booked : '' ) )
                    .prop( 'disabled', ! slot.available );
                if ( slot.available ) availableCount++;
                self.$timeSel.append( $opt );
            } );
            self.$timeSel.prop( 'disabled', false );
            self.showSlotsInfo( availableCount + ' von ' + slots.length + ' Slots verfügbar' );
        } );
    };

    MLBForm.prototype.resetSlots = function () {
        this.$timeSel.html( '<option value="">Bitte zuerst Datum wählen</option>' ).prop( 'disabled', true );
        this.$slotsInfo.text( '' );
    };

    MLBForm.prototype.resetDate = function () {
        if ( this.flatpickrInstance ) this.flatpickrInstance.clear();
        this.resetSlots();
    };

    MLBForm.prototype.showSlotsInfo = function ( msg ) { this.$slotsInfo.text( msg ); };

    MLBForm.prototype.submitBooking = function () {
        var self  = this;
        var $form = self.$form;

        if ( $form[0].checkValidity && ! $form[0].checkValidity() ) { $form[0].reportValidity(); return; }
        if ( ! self.currentLocationId ) { self.showError( cfg.i18n.selectLocation ); return; }
        if ( ! $form.find( '[name="date"]' ).val() ) { self.showError( cfg.i18n.selectDate ); return; }

        // DSGVO-Checkbox
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

        var data = $form.serializeArray().reduce( function ( acc, item ) { acc[ item.name ] = item.value; return acc; }, {} );
        data.action      = 'mlb_submit_booking';
        data.nonce       = cfg.nonce;
        data.location_id = self.currentLocationId;

        $.post( cfg.ajaxUrl, data, function ( res ) {
            self.setLoading( false );
            if ( res.success ) {
                self.showSuccess( res.data.message, res.data.ical_url || '' );
            } else {
                self.showError( ( res.data && res.data.message ) ? res.data.message : cfg.i18n.errorGeneral );
            }
        } ).fail( function () {
            self.setLoading( false );
            self.showError( cfg.i18n.errorGeneral );
        } );
    };

    MLBForm.prototype.setLoading = function ( isLoading ) {
        this.$submitBtn.prop( 'disabled', isLoading ).toggleClass( 'mlb-form__button--loading', isLoading );
    };

    MLBForm.prototype.showSuccess = function ( message, icalUrl ) {
        this.$form.prop( 'hidden', true );
        this.$success.find( '.mlb-form__success-message' ).text( message );
        if ( icalUrl ) {
            this.$success.find( '.mlb-form__ical-link' ).attr( 'href', icalUrl ).prop( 'hidden', false );
        }
        this.$success.prop( 'hidden', false );
        this.$wrap[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
    };

    MLBForm.prototype.showError = function ( message ) {
        this.$errGlobal.find( '.mlb-form__error-message' ).text( message );
        this.$errGlobal.prop( 'hidden', false );
        this.$errGlobal[0].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
    };

    MLBForm.prototype.hideError = function () { this.$errGlobal.prop( 'hidden', true ); };

    // Initialisierung NACH allen Prototype-Definitionen
    $( '.mlb-booking-form' ).each( function () { new MLBForm( $( this ) ); } );

} )( jQuery, window.mlbConfig || null );
