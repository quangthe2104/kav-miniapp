import {
  authorize,
  downloadFile,
  getAccessToken,
  getPhoneNumber,
  getUserInfo,
  nativeStorage,
  openShareSheet,
} from 'zmp-sdk'
import type { Platform } from './types'

declare global {
  interface Window {
    APP_ID?: string
    BASE_PATH?: string
  }
}

function resolveBasename(): string {
  if (window.BASE_PATH) return window.BASE_PATH.replace(/\/$/, '')
  if (window.APP_ID) return `/zapps/${window.APP_ID}`
  const m = window.location.pathname.match(/^\/zapps\/[^/]+/)
  return m ? m[0] : ''
}

function blobToDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(reader.error ?? new Error('Không đọc được file.'))
    reader.readAsDataURL(blob)
  })
}

// LocalStorage / SessionStorage / Cookie are unavailable inside Zalo Mini App.
export const platform: Platform = {
  isZalo: true,
  routerBasename: resolveBasename(),
  storage: {
    get(key) {
      try {
        return nativeStorage.getItem(key) || null
      } catch {
        return null
      }
    },
    set(key, value) {
      nativeStorage.setItem(key, value)
    },
    remove(key) {
      nativeStorage.removeItem(key)
    },
  },
  async getAccessToken() {
    try {
      const token = await getAccessToken()
      return token?.trim() || null
    } catch {
      return null
    }
  },
  async requestContactInfo() {
    let granted: Record<string, boolean> = {}
    try {
      granted = await authorize({ scopes: ['scope.userInfo', 'scope.userPhonenumber'] })
    } catch {
      /* declined or unsupported — vote continues without contact info */
    }

    let displayName: string | null = null
    if (granted['scope.userInfo']) {
      try {
        const { userInfo } = await getUserInfo({})
        displayName = userInfo?.name?.trim() || null
      } catch {
        /* ignore */
      }
    }

    let phoneToken: string | null = null
    if (granted['scope.userPhonenumber']) {
      try {
        const { token } = await getPhoneNumber({})
        phoneToken = token?.trim() || null
      } catch {
        /* ignore */
      }
    }

    const accessToken = await platform.getAccessToken()
    return accessToken ? { accessToken, phoneToken, displayName } : null
  },
  async shareLink(link) {
    await openShareSheet({ type: 'link', data: { link, chatOnly: false } })
    return true
  },
  async saveFile(blob) {
    await downloadFile({ fileBase64Data: await blobToDataUrl(blob) })
    return true
  },
}
