import { execSync } from 'node:child_process'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

// Process env wins over miniapp/.env in Vite: empty base URL => same-origin API on the deployed domain.
const env = {
  ...process.env,
  VITE_API_BASE_URL: process.env.VITE_API_BASE_URL ?? '',
  VITE_ENABLE_DEV_LOGIN: process.env.VITE_ENABLE_DEV_LOGIN ?? 'false',
}

console.log(
  `build:cpanel VITE_API_BASE_URL="${env.VITE_API_BASE_URL}" VITE_ENABLE_DEV_LOGIN=${env.VITE_ENABLE_DEV_LOGIN}`,
)
execSync('npm run build:wamp', { stdio: 'inherit', env, cwd: root })
