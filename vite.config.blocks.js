import { defineConfig } from 'vite';
import path from 'path';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const pluginDir  = path.resolve(__dirname, 'cms/wp-content/plugins/media-lab-agency-core');

/**
 * Vite-Konfiguration: Gutenberg Blocks (Plugin-Assets)
 *
 * Separater Build für media-lab-agency-core/assets/dist/.
 * Aufruf:  vite build --config vite.config.blocks.js
 * Oder via: npm run build  (ruft beide Configs nacheinander auf)
 *
 * @since 1.6.0
 */
export default defineConfig({
  root: path.resolve(pluginDir, 'assets/src'),
  base: '/wp-content/plugins/media-lab-agency-core/assets/dist/',

  build: {
    outDir:      path.resolve(pluginDir, 'assets/dist'),
    emptyOutDir: true,

    rollupOptions: {
      input: {
        blocks:              path.resolve(pluginDir, 'assets/src/js/blocks.js'),
        'block-accordion':   path.resolve(pluginDir, 'assets/src/js/block-accordion.js'),
        'block-logo-slider': path.resolve(pluginDir, 'assets/src/js/block-logo-slider.js'),
        'blocks-scss':       path.resolve(pluginDir, 'assets/src/scss/blocks.scss'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) return 'css/[name].css';
          return 'assets/[name][extname]';
        },
      },
      // wp-* nicht bundeln – WordPress stellt diese zur Laufzeit bereit
      external: [/^@wordpress\/.*/],
    },

    cssCodeSplit: false,
    minify: 'terser',
    terserOptions: {
      compress: { drop_console: true, drop_debugger: true },
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
});
