/**
 * Google Maps – API-basierte Implementierung mit Routenplanung (DSGVO-konform)
 *
 * Wrapper:   .google-map          mit data-lat, data-lng, data-zoom,
 *                                     data-map-id, data-marker-title, data-style
 * Placeholder: .google-map__placeholder
 * Button:    [data-map-accept-comfort]  → Comfort-Cookie setzen + Karte laden
 * Button:    [data-map-open-settings]  → Cookie-Banner öffnen
 * Canvas:    .google-map__canvas
 * Directions: .google-map__directions  (wird dynamisch vom JS generiert)
 */

export default class GoogleMaps {

    constructor() {
        this.maps      = document.querySelectorAll( '.google-map' );
        this.apiKey    = window.stadtwirtTheme?.googleMapsApiKey || '';
        this.apiLoaded = false;
        this.loaded    = new Set();

        if ( this.maps.length === 0 ) return;

        this.init();
    }

    // ── Initialisierung ───────────────────────────────────────────────────────

    init() {
        this.maps.forEach( wrapper => this.initWrapper( wrapper ) );

        document.addEventListener( 'cookies:accepted', e => {
            if ( e.detail?.comfort ) this.loadAll();
        } );
        document.addEventListener( 'cookies:changed', e => {
            if ( e.detail?.comfort ) this.loadAll();
        } );
    }

    initWrapper( wrapper ) {
        if ( this.hasComfortConsent() ) {
            this.loadMap( wrapper );
            return;
        }

        const acceptBtn = wrapper.querySelector( '[data-map-accept-comfort]' );
        if ( acceptBtn ) {
            acceptBtn.addEventListener( 'click', () => {
                this.setComfortConsent();
                this.loadMap( wrapper );
            } );
        }

        const settingsBtn = wrapper.querySelector( '[data-map-open-settings]' );
        if ( settingsBtn ) {
            settingsBtn.addEventListener( 'click', () => {
                const toggle = document.getElementById( 'cookie-settings-btn' );
                if ( toggle ) {
                    toggle.click();
                } else {
                    document.dispatchEvent( new CustomEvent( 'cookies:openSettings' ) );
                }
            } );
        }
    }

    // ── Karte laden ───────────────────────────────────────────────────────────

    async loadMap( wrapper ) {
        const mapId = wrapper.dataset.mapId;
        if ( this.loaded.has( mapId ) ) return;
        this.loaded.add( mapId );

        const lat         = parseFloat( wrapper.dataset.lat );
        const lng         = parseFloat( wrapper.dataset.lng );
        const zoom        = parseInt( wrapper.dataset.zoom ) || 15;
        const markerTitle = wrapper.dataset.markerTitle || '';
        const style       = wrapper.dataset.style || 'default';
        const destination = `${lat},${lng}`;

        // Placeholder ausblenden
        const placeholder = wrapper.querySelector( '.google-map__placeholder' );
        if ( placeholder ) {
            placeholder.style.transition = 'opacity 0.3s ease';
            placeholder.style.opacity    = '0';
            setTimeout( () => placeholder.remove(), 350 );
        }

        if ( ! this.apiLoaded ) {
            await this.loadAPI();
        }

        const canvas = wrapper.querySelector( '.google-map__canvas' );
        if ( ! canvas ) return;

        // Karte initialisieren
        const map = new google.maps.Map( canvas, {
            center:            { lat, lng },
            zoom:              zoom,
            styles:            this.getStyles( style ),
            mapTypeControl:    false,
            streetViewControl: false,
            fullscreenControl: true,
            mapId:             'DEMO_MAP_ID',
        } );

        // Marker
        const { AdvancedMarkerElement } = await google.maps.importLibrary( 'marker' );
        const marker = new AdvancedMarkerElement( {
            position: { lat, lng },
            map:      map,
            title:    markerTitle,
        } );

        if ( markerTitle ) {
            const infoWindow = new google.maps.InfoWindow( {
                content: `<div style="padding:8px 12px;font-weight:600;">${markerTitle}</div>`,
            } );
            marker.addListener( 'click', () => infoWindow.open( map, marker ) );
        }

        // Directions Services
        const directionsService  = new google.maps.DirectionsService();
        const directionsRenderer = new google.maps.DirectionsRenderer( {
            map,
            suppressMarkers: false,
            polylineOptions: {
                strokeColor:   '#7f1612',
                strokeWeight:  5,
                strokeOpacity: 0.8,
            },
        } );

        // Directions UI einbauen
        this.renderDirectionsUI( wrapper, map, directionsService, directionsRenderer, destination, lat, lng );
    }

    loadAll() {
        this.maps.forEach( wrapper => this.loadMap( wrapper ) );
    }

    // ── Directions UI ─────────────────────────────────────────────────────────

    renderDirectionsUI( wrapper, map, service, renderer, destination, lat, lng ) {
        const fallbackUrl = `https://www.google.com/maps/dir/?api=1&destination=${destination}`;

        // UI-Container nach der Karte einfügen
        const ui = document.createElement( 'div' );
        ui.className = 'google-map__directions';
        ui.innerHTML = `
            <div class="google-map__directions-inner">
                <div class="google-map__directions-input-wrap">
                    <input
                        type="text"
                        class="google-map__directions-input ff-el-form-control"
                        placeholder="Startadresse eingeben…"
                        aria-label="Startadresse für Routenberechnung"
                    />
                    <button type="button" class="google-map__directions-btn btn btn--primary">
                        Route berechnen
                    </button>
                </div>
                <div class="google-map__directions-error" role="alert" hidden></div>
                <a
                    href="${fallbackUrl}"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="google-map__directions-fallback"
                >
                    ↗ In Google Maps öffnen
                </a>
            </div>
        `;
        wrapper.insertAdjacentElement( 'afterend', ui );

        const input    = ui.querySelector( '.google-map__directions-input' );
        const btn      = ui.querySelector( '.google-map__directions-btn' );
        const errorBox = ui.querySelector( '.google-map__directions-error' );
        const fallback = ui.querySelector( '.google-map__directions-fallback' );

        // Places Autocomplete
        const autocomplete = new google.maps.places.Autocomplete( input, {
            types:  [ 'geocode' ],
            fields: [ 'formatted_address', 'geometry' ],
        } );

        // Route berechnen
        const calculate = () => {
            const origin = input.value.trim();
            if ( ! origin ) {
                this.showError( errorBox, 'Bitte eine Startadresse eingeben.' );
                return;
            }

            errorBox.hidden = true;
            btn.disabled    = true;
            btn.textContent = 'Berechne…';

            service.route( {
                origin:      origin,
                destination: { lat: parseFloat( destination.split(',')[0] ), lng: parseFloat( destination.split(',')[1] ) },
                travelMode:  google.maps.TravelMode.DRIVING,
            }, ( result, status ) => {
                btn.disabled    = false;
                btn.textContent = 'Route berechnen';

                if ( status === 'OK' ) {
                    renderer.setDirections( result );

                    // Fallback-Link mit Startadresse aktualisieren
                    const encoded = encodeURIComponent( origin );
                    fallback.href = `https://www.google.com/maps/dir/?api=1&origin=${encoded}&destination=${destination}`;

                    // Karte ans obere Ende scrollen
                    wrapper.scrollIntoView( { behavior: 'smooth', block: 'start' } );
                } else {
                    this.showError( errorBox, 'Route konnte nicht berechnet werden. Bitte Adresse prüfen.' );
                }
            } );
        };

        btn.addEventListener( 'click', calculate );
        input.addEventListener( 'keydown', e => {
            if ( e.key === 'Enter' ) calculate();
        } );
    }

    showError( box, msg ) {
        box.textContent = msg;
        box.hidden      = false;
    }

    // ── Google Maps API ───────────────────────────────────────────────────────

    loadAPI() {
        return new Promise( ( resolve, reject ) => {
            if ( typeof google !== 'undefined' && google.maps ) {
                this.apiLoaded = true;
                resolve();
                return;
            }

            window.initGoogleMaps = () => {
                this.apiLoaded = true;
                resolve();
            };

            const script   = document.createElement( 'script' );
            // libraries=places für Autocomplete
            script.src     = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=places&loading=async&callback=initGoogleMaps`;
            script.async   = true;
            script.defer   = true;
            script.onerror = () => reject( new Error( 'Google Maps API konnte nicht geladen werden.' ) );
            document.head.appendChild( script );
        } );
    }

    // ── Consent-Handling ──────────────────────────────────────────────────────

    hasComfortConsent() {
        try {
            const stored = JSON.parse(
                localStorage.getItem( 'medialab-cookie-consent' ) || 'null'
            );
            if ( stored?.categories?.comfort === true ) return true;
        } catch ( e ) { /* ignore */ }
        return localStorage.getItem( 'google_maps_consent' ) === 'true';
    }

    setComfortConsent() {
        try {
            const stored = JSON.parse(
                localStorage.getItem( 'medialab-cookie-consent' ) || '{}'
            );
            if ( ! stored.categories ) stored.categories = {};
            stored.categories.comfort = true;
            localStorage.setItem( 'medialab-cookie-consent', JSON.stringify( stored ) );
            document.dispatchEvent( new CustomEvent( 'cookies:changed', {
                detail: { comfort: true }
            } ) );
        } catch ( e ) { /* ignore */ }
    }

    // ── Map Styles ────────────────────────────────────────────────────────────

    getStyles( style ) {
        const styles = {
            default: [],
            silver: [
                { elementType: 'geometry',           stylers: [ { color: '#f5f5f5' } ] },
                { elementType: 'labels.icon',        stylers: [ { visibility: 'off' } ] },
                { elementType: 'labels.text.fill',   stylers: [ { color: '#616161' } ] },
                { elementType: 'labels.text.stroke', stylers: [ { color: '#f5f5f5' } ] },
            ],
            dark: [
                { elementType: 'geometry',           stylers: [ { color: '#212121' } ] },
                { elementType: 'labels.icon',        stylers: [ { visibility: 'off' } ] },
                { elementType: 'labels.text.fill',   stylers: [ { color: '#757575' } ] },
                { elementType: 'labels.text.stroke', stylers: [ { color: '#212121' } ] },
            ],
            retro: [
                { elementType: 'geometry',           stylers: [ { color: '#ebe3cd' } ] },
                { elementType: 'labels.text.fill',   stylers: [ { color: '#523735' } ] },
                { elementType: 'labels.text.stroke', stylers: [ { color: '#f5f1e6' } ] },
            ],
        };
        return styles[ style ] || styles.default;
    }
}

new GoogleMaps();
