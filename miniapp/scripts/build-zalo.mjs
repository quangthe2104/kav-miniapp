import { execSync } from 'node:child_process'
import { copyFileSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const outDir = join(root, 'dist-zalo')

// Mini App runs on Zalo's domain (h5.zdn.vn) → API must be an absolute HTTPS origin with CORS.
const env = {
  ...process.env,
  VITE_API_BASE_URL: process.env.VITE_API_BASE_URL || 'https://miniapp.kav.edu.vn',
  VITE_ENABLE_DEV_LOGIN: process.env.VITE_ENABLE_DEV_LOGIN ?? 'false',
}

if (!env.VITE_API_BASE_URL.startsWith('https://')) {
  console.error(`build:zalo VITE_API_BASE_URL phải là https:// (đang là "${env.VITE_API_BASE_URL}")`)
  process.exit(1)
}

console.log(
  `build:zalo VITE_API_BASE_URL="${env.VITE_API_BASE_URL}" VITE_ENABLE_DEV_LOGIN=${env.VITE_ENABLE_DEV_LOGIN}`,
)
execSync('npx tsc -b', { stdio: 'inherit', env, cwd: root })
execSync('npx vite build --mode zalo', { stdio: 'inherit', env, cwd: root })

// Zalo uses its own index.html, so the entry JS/CSS must be listed in app-config.json.
const html = readFileSync(join(outDir, 'index.html'), 'utf8')
const strip = (p) => p.replace(/^\.?\//, '')
const listSyncJS = [...html.matchAll(/<script[^>]+src="([^"]+)"/g)].map((m) => strip(m[1]))
const listCSS = [...html.matchAll(/<link[^>]+rel="stylesheet"[^>]*href="([^"]+)"/g)]
  .map((m) => m[1])
  .filter((href) => !/^https?:/.test(href))
  .map(strip)

if (listSyncJS.length === 0) {
  console.error('build:zalo không tìm thấy entry JS trong dist-zalo/index.html')
  process.exit(1)
}

const appConfig = {
  app: {
    title: 'KAV Form lớp',
    headerTitle: 'KAV Form lớp',
    headerColor: '#0a2a66',
    textColor: 'white',
    statusBar: 'normal',
    actionBarHidden: false,
    leftButton: 'back',
  },
  debug: false,
  listCSS,
  listSyncJS,
  listAsyncJS: [],
}

const configPath = join(root, 'app-config.json')
writeFileSync(configPath, `${JSON.stringify(appConfig, null, 2)}\n`)
copyFileSync(configPath, join(outDir, 'app-config.json'))
console.log(`build:zalo OK → dist-zalo/ (JS: ${listSyncJS.join(', ')} · CSS: ${listCSS.join(', ')})`)
