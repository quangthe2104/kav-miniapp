import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// `vite build --mode zalo` → bundle for zmp deploy (Zalo CDN serves it under /zapps/{APP_ID}).
export default defineConfig(({ mode }) => {
  const zalo = mode === 'zalo'

  return {
    plugins: [react()],
    base: zalo ? './' : '/miniapp/',
    resolve: {
      alias: {
        '@platform': fileURLToPath(
          new URL(`./src/platform/${zalo ? 'zalo' : 'web'}.ts`, import.meta.url),
        ),
      },
    },
    build: zalo
      ? {
          outDir: 'dist-zalo',
          emptyOutDir: true,
          modulePreload: { polyfill: false },
          rollupOptions: {
            output: {
              entryFileNames: 'assets/[name].module.js',
              chunkFileNames: 'assets/[name].module.js',
              assetFileNames: 'assets/[name][extname]',
            },
          },
        }
      : undefined,
    server: {
      port: 5173,
      host: true,
    },
  }
})
