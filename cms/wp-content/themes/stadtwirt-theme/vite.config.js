import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  build: {
    outDir: 'cms/wp-content/themes/stadtwirt-theme/assets/dist',
    emptyOutDir: true,
    sourcemap: false,

    rollupOptions: {
      input: {
        main:          'cms/wp-content/themes/stadtwirt-theme/assets/src/js/main.js',
        ajaxFilters:   'cms/wp-content/themes/stadtwirt-theme/assets/src/js/components/ajax-filters.js',
        ajaxSearch:    'cms/wp-content/themes/stadtwirt-theme/assets/src/js/components/ajax-search.js',
        loadMore:      'cms/wp-content/themes/stadtwirt-theme/assets/src/js/components/load-more.js',
        googleMaps:    'cms/wp-content/themes/stadtwirt-theme/assets/src/js/components/google-maps.js',
        notifications: 'cms/wp-content/themes/stadtwirt-theme/assets/src/js/components/notifications.js',
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'css/[name][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },

    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console:  true,
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info', 'console.debug'],
      },
    },

    cssCodeSplit: false,
    chunkSizeWarningLimit: 200,
  },

  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
      },
    },
  },
});
