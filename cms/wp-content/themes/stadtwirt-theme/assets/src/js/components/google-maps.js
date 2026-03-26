/**
 * DSGVO-konforme Google Maps Component
 */

export default class GoogleMaps {
  constructor() {
    this.maps = document.querySelectorAll('.google-map-wrapper');
    this.apiKey = window.stadtwirtTheme?.googleMapsApiKey || '';
    this.apiLoaded = false;
    this.loadedMaps = new Set();
    
    if (this.maps.length > 0) {
      this.init();
    }
  }
  
  init() {
    console.log(`✅ Found ${this.maps.length} map(s)`);

    this.maps.forEach(mapWrapper => {
      const button = mapWrapper.querySelector('[data-action="load-map"]');

      if (button) {
        button.addEventListener('click', () => {
          // Consent via CookieConsent setzen falls vorhanden
          if (window.CookieConsent && typeof window.CookieConsent._saveConsent === 'function') {
            const current = window.CookieConsent.consent || {};
            window.CookieConsent._saveConsent({ ...current, comfort: true });
          }
          this.loadMap(mapWrapper);
        });
      }

      // Sofort laden wenn Comfort-Consent bereits gesetzt
      if (this.hasComfortConsent()) {
        this.loadMap(mapWrapper);
      }
    });

    // Auf Cookie-Consent-Event lauschen
    document.addEventListener('cookies:changed', (e) => {
      if (e.detail?.comfort) {
        this.maps.forEach(mapWrapper => this.loadMap(mapWrapper));
      }
    });

    document.addEventListener('cookies:accepted', (e) => {
      if (e.detail?.comfort) {
        this.maps.forEach(mapWrapper => this.loadMap(mapWrapper));
      }
    });
  }

  hasComfortConsent() {
    try {
      const stored = JSON.parse(localStorage.getItem('medialab-cookie-consent') || 'null');
      return stored?.categories?.comfort === true;
    } catch (e) {
      return false;
    }
  }
  
  async loadMap(mapWrapper) {
    const mapId = mapWrapper.dataset.mapId;
    
    // Prevent double loading
    if (this.loadedMaps.has(mapId)) {
      return;
    }
    
    console.log('Loading map:', mapId);
    
    // Get data
    const lat = parseFloat(mapWrapper.dataset.lat);
    const lng = parseFloat(mapWrapper.dataset.lng);
    const zoom = parseInt(mapWrapper.dataset.zoom) || 15;
    const markerTitle = mapWrapper.dataset.markerTitle || '';
    const style = mapWrapper.dataset.style || 'default';
    
    // Hide overlay
    const overlay = mapWrapper.querySelector('.google-map-overlay');
    overlay.style.opacity = '0';
    setTimeout(() => {
      overlay.style.display = 'none';
    }, 300);
    
    // Load Google Maps API if not loaded
    if (!this.apiLoaded) {
      await this.loadGoogleMapsAPI();
    }
    
    // Initialize map
    const mapCanvas = mapWrapper.querySelector(`#${mapId}`);
    
    const map = new google.maps.Map(mapCanvas, {
      center: { lat, lng },
      zoom: zoom,
      styles: this.getMapStyles(style),
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
      mapId: "DEMO_MAP_ID",
    });
    
    // Add marker (AdvancedMarkerElement)
    const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
    const marker = new AdvancedMarkerElement({
      position: { lat, lng },
      map: map,
      title: markerTitle,
    });

    // Info window (optional)
    if (markerTitle) {
      const infoWindow = new google.maps.InfoWindow({
        content: `<div style="padding: 10px;"><strong>${markerTitle}</strong></div>`,
      });

      marker.addListener('click', () => {
        infoWindow.open(map, marker);
      });
    }
    
    this.loadedMaps.add(mapId);
    
    // Save consent in localStorage (optional)
    localStorage.setItem('google_maps_consent', 'true');
    
    console.log('✅ Map loaded:', mapId);
  }
  
  loadGoogleMapsAPI() {
    return new Promise((resolve, reject) => {
      if (typeof google !== 'undefined' && google.maps) {
        this.apiLoaded = true;
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&loading=async&callback=initGoogleMaps`;
      script.async = true;
      script.defer = true;

      window.initGoogleMaps = () => {
        this.apiLoaded = true;
        resolve();
      };

      script.onerror = () => reject(new Error('Failed to load Google Maps API'));
      document.head.appendChild(script);
    });
  }
  
  getMapStyles(style) {
    const styles = {
      default: [],
      
      silver: [
        { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
        { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#f5f5f5' }] },
      ],
      
      dark: [
        { elementType: 'geometry', stylers: [{ color: '#212121' }] },
        { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#212121' }] },
      ],
      
      retro: [
        { elementType: 'geometry', stylers: [{ color: '#ebe3cd' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#523735' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#f5f1e6' }] },
      ],
    };
    
    return styles[style] || styles.default;
  }
}

// Initialize
new GoogleMaps();