/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string
  /** Show browser mock login even in production builds (local WAMP). */
  readonly VITE_ENABLE_DEV_LOGIN?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
