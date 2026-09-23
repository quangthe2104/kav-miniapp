/**
 * Share vote link into Zalo (group / chat).
 * Inside Mini App: native share sheet. On web: zalo.me/share.
 */
export async function shareVoteToZalo(url: string): Promise<void> {
  const link = url.trim()
  if (!link) throw new Error('Chưa có link phiếu.')

  const w = window as Window & {
    zmp?: {
      openShareSheet?: (opts: {
        type: string
        data: Record<string, unknown>
        success?: () => void
        fail?: (err: unknown) => void
      }) => void
    }
  }

  if (typeof w.zmp?.openShareSheet === 'function') {
    await new Promise<void>((resolve, reject) => {
      w.zmp!.openShareSheet!({
        type: 'link',
        data: { link, chatOnly: false },
        success: () => resolve(),
        fail: (err) =>
          reject(err instanceof Error ? err : new Error('Không chia sẻ được trên Zalo.')),
      })
    })
    return
  }

  const share = `https://zalo.me/share?u=${encodeURIComponent(link)}`
  // Prefer <a target=_blank>: window.open(..., 'noopener') often returns null even when a tab opened,
  // which previously also navigated the current page via location.href.
  const a = document.createElement('a')
  a.href = share
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  a.remove()
}
