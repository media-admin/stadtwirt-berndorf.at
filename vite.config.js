import { defineConfig } from 'vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';

// Compression (Brotli + Gzip) – graceful fallback wenn nicht installiert
// Installation: npm install -D vite-plugin-compression2
let compression = null;
try {
    const mod = await import('vite-plugin-compression2');
    compression = mod.compression ?? mod.default;
} catch (e) { /* not installed */ }

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const themeDir   = path.resolve(__dirname, 'cms/wp-content/themes/stadtwirt-theme');

export default defineConfig(({ command }) => ({

  root: path.resolve(themeDir, 'assets'),

  // Im Dev-Modus absolute URL damit @vite/client & HMR erreichbar sind.
  // Im Build-Modus der echte Pfad für die enqueued Assets.
  base: command === 'serve'
    ? '/'
    : '/wp-content/themes/stadtwirt-theme/assets/dist/',

  plugins: [
    liveReload([
      path.resolve(__dirname, 'cms/wp-content/themes/stadtwirt-theme/**/*.php'),
    ]),
    ...(compression ? [compression({ algorithm: 'brotliCompress', exclude: [/\.(br|gz)$/] })] : []),
    ...(compression ? [compression({ algorithm: 'gzip',           exclude: [/\.(br|gz)$/] })] : []),
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
          if (assetInfo.name?.endsWith('.css'))                          return 'css/style.css';
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
    watch: {
      usePolling: true,
      interval:   300,
    },
    hmr: {
      host:       'localhost',
      port:       3000,
      protocol:   'ws',
      clientPort: 3000,
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

}));
