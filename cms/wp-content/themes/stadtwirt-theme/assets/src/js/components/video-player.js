/**
 * Video Player with Custom Thumbnail
 */

export default class VideoPlayer {
  constructor() {
    this.players = document.querySelectorAll('.video-player');
    this.init();
  }
  
  init() {
    if (this.players.length === 0) return;
    
    this.players.forEach(player => {
      this.initPlayer(player);
    });
  }
  
  initPlayer(player) {
    const thumbnail = player.querySelector('.video-player__thumbnail');
    
    if (thumbnail) {
      const playButton = thumbnail.querySelector('.video-player__play-button');
      const videoUrl = thumbnail.dataset.videoUrl;
      
      // Click on thumbnail or play button
      thumbnail.addEventListener('click', () => {
        this.loadVideo(player, thumbnail, videoUrl);
      });
    }
  }
  
  loadVideo(player, thumbnail, videoUrl) {
    // Add loading state
    player.classList.add('is-loading');
    
    // Create iframe
    const iframe = document.createElement('iframe');
    iframe.className = 'video-player__iframe';
    iframe.src = videoUrl + '&autoplay=1';
    iframe.frameBorder = '0';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    
    // Wait for iframe to load
    iframe.onload = () => {
      player.classList.remove('is-loading');
    };
    
    // Replace thumbnail with iframe
    thumbnail.parentNode.replaceChild(iframe, thumbnail);
  }
}

// Initialize
new VideoPlayer();