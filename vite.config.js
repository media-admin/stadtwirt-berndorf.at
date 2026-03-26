import { defineConfig } from 'vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';

// Compression (Brotli + Gzip) – graceful fallback wenn nicht installiert
let compression = null;
try {
  const mod = await import('vite-plugin-compression2');
  compression = mod.compression ?? mod.default;
} catch (e) { /* not installed */ }

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const themeDir   = path.resolve(__dirname, 'cms/wp-content/themes/stadtwirt-theme');

const isDev = process.env.NODE_ENV !== 'production' && process.env.NODE_ENV !== 'staging';

export default defineConfig({
  root: path.resolve(themeDir, 'assets'),

  /**
   * FIX: Im Dev-Modus zeigt base auf den Vite Dev Server (localhost:3000).
   * WordPress liest diese URL aus einer JSON-Datei (s.u.) und verwendet sie
   * für alle Asset-URLs → Browser verbindet sich mit dem Vite-WebSocket
   * → HMR und Live Reload funktionieren.
   *
   * Im Build-Modus: normaler dist-Pfad für WordPress.
   */
  base: isDev
    ? 'http://localhost:3000/'
    : '/cms/wp-content/themes/stadtwirt-theme/assets/dist/',

  plugins: [
    // Triggert Full-Page-Reload bei PHP-Änderungen (relativ zum CWD = Projekt-Root)
    liveReload([
      'cms/wp-content/themes/stadtwirt-theme/**/*.php',
      'cms/wp-content/themes/stadtwirt-theme/**/*.twig', // falls Twig verwendet wird
    ]),
    ...(compression
      ? [
          compression({ algorithm: 'brotliCompress', exclude: [/\.(br|gz)$/] }),
          compression({ algorithm: 'gzip',           exclude: [/\.(br|gz)$/] }),
        ]
      : []),
  ],

  build: {
    outDir:      path.resolve(themeDir, 'assets/dist'),
    emptyOutDir: true,

    rollupOptions: {
      input: {
        main: path.resolve(themeDir, 'assets/src/js/main.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) return 'css/style.css';
          if (/\.(png|jpe?g|svg|gif|webp)$/.test(assetInfo.name ?? '')) return 'images/[name][extname]';
          return 'assets/[name][extname]';
        },
      },
    },

    manifest:              true,
    cssCodeSplit:          false,
    chunkSizeWarningLimit: 200,
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console:  true,
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info', 'console.debug'],
      },
    },
  },

  server: {
    host:       'localhost',
    port:       3000,
    strictPort: true,
    cors:       true,

    // HMR über expliziten Host – wichtig wenn WordPress auf einer anderen Domain läuft
    hmr: {
      host:     'localhost',
      port:     3000,
      protocol: 'ws',
    },
  },

  css: {
    preprocessorOptions: {
      scss: { api: 'modern-compiler' },
    },
    postcss: {
      plugins: [
        autoprefixer({
          overrideBrowserslist: ['last 2 versions', '> 1%', 'not dead'],
        }),
      ],
    },
  },

  resolve: {
    alias: {
      '@': path.resolve(themeDir, 'assets/src'),
    },
  },
});
