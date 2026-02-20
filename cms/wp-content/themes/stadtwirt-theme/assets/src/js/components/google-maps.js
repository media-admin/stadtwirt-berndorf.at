/**
 * DSGVO-konforme Google Maps Component
 */

export default class GoogleMaps {
  constructor() {
    this.maps = document.querySelectorAll('.google-map-wrapper');
    this.apiKey = window.customTheme?.googleMapsApiKey || '';
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
          this.loadMap(mapWrapper);
        });
      }
    });
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
    });
    
    // Add marker
    const marker = new google.maps.Marker({
      position: { lat, lng },
      map: map,
      title: markerTitle,
      animation: google.maps.Animation.DROP,
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
      
      console.log('Loading Google Maps API...');
      
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&callback=initGoogleMaps`;
      script.async = true;
      script.defer = true;
      
      window.initGoogleMaps = () => {
        this.apiLoaded = true;
        console.log('✅ Google Maps API loaded');
        resolve();
      };
      
      script.onerror = () => {
        console.error('❌ Failed to load Google Maps API');
        reject(new Error('Failed to load Google Maps API'));
      };
      
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