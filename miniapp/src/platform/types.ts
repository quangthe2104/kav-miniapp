export type ZaloContactInfo = {
  accessToken: string
  phoneToken: string | null
  displayName: string | null
}

/**
 * Runtime differences between the web build (/miniapp on Laravel) and the Zalo Mini App build.
 * `@platform` resolves to web.ts or zalo.ts by Vite mode (see vite.config.ts).
 */
export type Platform = {
  isZalo: boolean
  routerBasename: string
  storage: {
    get(key: string): string | null
    set(key: string, value: string): void
    remove(key: string): void
  }
  /** Zalo user access token, or null when unavailable. */
  getAccessToken(): Promise<string | null>
  /**
   * Asks the user (one Zalo popup) to share name + phone. Declining is not an error;
   * phoneToken is one-time (~2 min) and must be exchanged by the backend.
   */
  requestContactInfo(): Promise<ZaloContactInfo | null>
  /** Returns false when the caller should fall back to the web share flow. */
  shareLink(link: string): Promise<boolean>
  /** Returns false when the caller should fall back to an <a download> link. */
  saveFile(blob: Blob, filename: string): Promise<boolean>
}
