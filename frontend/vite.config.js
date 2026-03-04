import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, '../assets/dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.jsx'),
      output: {
        entryFileNames: 'aip-widget.js',
        assetFileNames: 'aip-widget.[ext]',
      },
    },
  },
  server: {
    port: 3100,
    cors: true,
  },
});
