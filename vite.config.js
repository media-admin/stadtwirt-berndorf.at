import { defineConfig } from 'vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
  root: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets'),
  base: '/wp-content/themes/custom-theme/assets/dist/',

  plugins: [
    liveReload([
      'cms/wp-content/themes/custom-theme/**/*.php',
    ]),
  ],
  
  build: {
    outDir: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/dist'),
    emptyOutDir: true,
    
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/src/js/main.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            // GEÄNDERT: Immer style.css generieren
            return 'css/style.css';
          }
          if (/\.(png|jpe?g|svg|gif|webp)$/.test(assetInfo.name)) {
            return 'images/[name][extname]';
          }
          return 'assets/[name][extname]';
        }
      }
    },
    
    manifest: true,
    minify: 'terser',
  },
  
  server: {
    host: 'localhost',
    port: 3000,
    strictPort: true,
    cors: true,
    hmr: {
      host: 'localhost',
      port: 3000,
    },
  },

  css: {
    postcss: {
      plugins: [
        autoprefixer({
          overrideBrowserslist: [
            'last 2 versions',
            '> 1%',
            'IE 11',
            'not dead'
          ]
        })
      ]
    }
  },
  
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'cms/wp-content/themes/custom-theme/assets/src'),
    }
  }
});