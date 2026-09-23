import type { ReactNode } from 'react'
import { shareVoteToZalo } from '../api/shareZalo'

function ShareIcon() {
  return (
    <svg className="btn-icon" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="18" cy="5" r="3" fill="none" stroke="currentColor" strokeWidth="2" />
      <circle cx="6" cy="12" r="3" fill="none" stroke="currentColor" strokeWidth="2" />
      <circle cx="18" cy="19" r="3" fill="none" stroke="currentColor" strokeWidth="2" />
      <path
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"
      />
    </svg>
  )
}

export function ShareZaloButton({
  url,
  getUrl,
  disabled,
  className = 'btn outline btn-with-icon',
  children = 'Share Zalo',
  onError,
}: {
  url?: string | null
  getUrl?: () => Promise<string>
  disabled?: boolean
  className?: string
  children?: ReactNode
  onError?: (message: string) => void
}) {
  return (
    <button
      type="button"
      className={className}
      disabled={disabled}
      onClick={() => {
        void (async () => {
          try {
            const link = url || (await getUrl?.())
            if (!link) {
              onError?.('Chưa có link phiếu.')
              return
            }
            await shareVoteToZalo(link)
          } catch (err) {
            onError?.(err instanceof Error ? err.message : 'Không chia sẻ được.')
          }
        })()
      }}
    >
      <ShareIcon />
      <span>{children}</span>
    </button>
  )
}
