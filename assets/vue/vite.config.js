import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    lib: {
      entry: 'assets/vue/main.js',
      formats: ['iife'],
      name: 'TmgmtDashboard',
      fileName: () => 'dashboard.iife.js',
    },
    outDir: 'assets/dist',
    cssCodeSplit: false,
    rollupOptions: {
      external: ['leaflet', 'sweetalert2'],
      output: {
        globals: {
          leaflet: 'L',
          sweetalert2: 'Swal',
        },
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') return 'dashboard.css'
          return assetInfo.name
        },
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['assets/vue/tests/**/*.test.js'],
  },
})
