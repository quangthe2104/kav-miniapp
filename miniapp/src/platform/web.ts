import type { Platform } from './types'

const memory = new Map<string, string>()

export const platform: Platform = {
  isZalo: false,
  routerBasename: '/miniapp',
  storage: {
    get(key) {
      try {
        return localStorage.getItem(key)
      } catch {
        return memory.get(key) ?? null
      }
    },
    set(key, value) {
      try {
        localStorage.setItem(key, value)
      } catch {
        memory.set(key, value)
      }
    },
    remove(key) {
      try {
        localStorage.removeItem(key)
      } catch {
        memory.delete(key)
      }
    },
  },
  async getAccessToken() {
    return null
  },
  async requestContactInfo() {
    return null
  },
  async shareLink() {
    return false
  },
  async saveFile() {
    return false
  },
}
