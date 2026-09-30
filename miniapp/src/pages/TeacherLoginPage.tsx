import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Shell } from '../components/Shell'
import {
  ApiError,
  ZALO_SESSION_ERROR,
  isZaloApp,
  mockTeacherLogin,
  registerAsTeacher,
  tryZaloAccessToken,
} from '../api/client'

const isDevBuild = import.meta.env.DEV
const forceDevLogin =
  import.meta.env.VITE_ENABLE_DEV_LOGIN === 'true' || isDevBuild

export function TeacherLoginPage() {
  const navigate = useNavigate()
  const [zaloUserId, setZaloUserId] = useState('teacher-dev-1')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [showTestLogin, setShowTestLogin] = useState(forceDevLogin)

  async function loginWithZalo() {
    setError(null)
    setLoading(true)
    try {
      const accessToken = await tryZaloAccessToken()
      if (!accessToken) {
        if (isZaloApp) {
          setError(ZALO_SESSION_ERROR)
          if (forceDevLogin) setShowTestLogin(true)
        } else if (forceDevLogin || showTestLogin) {
          setShowTestLogin(true)
          setError(
            'Chưa lấy được phiên Zalo. Dùng đăng nhập thử nghiệm bên dưới (trình duyệt / local).',
          )
        } else {
          setError(
            'Vui lòng mở ứng dụng trong Zalo Mini App để đăng nhập.',
          )
          setShowTestLogin(true)
        }
        return
      }
      await registerAsTeacher(accessToken)
      navigate('/teacher')
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Không đăng nhập được. Thử lại sau.',
      )
    } finally {
      setLoading(false)
    }
  }

  const autoLoginTried = useRef(false)
  useEffect(() => {
    if (isZaloApp && !autoLoginTried.current) {
      autoLoginTried.current = true
      void loginWithZalo()
    }
  }, [])

  async function onMockSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await mockTeacherLogin(zaloUserId)
      navigate('/teacher')
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Không đăng nhập được. Kiểm tra kết nối máy chủ.',
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <Shell title="Giáo viên · đăng nhập">
      <section className="hero-block">
        <h1 className="h1-sm">Đăng nhập giáo viên</h1>
        <p className="lead">
          Đăng nhập bằng Zalo — lần đầu hệ thống tự tạo tài khoản giáo viên,
          sau đó bạn có thể tạo lớp ngay. Không cần Admin cấp quyền trước.
        </p>
      </section>

      <div className="panel">
        <button
          className="btn primary"
          type="button"
          disabled={loading}
          onClick={() => void loginWithZalo()}
        >
          {loading ? 'Đang đăng nhập…' : 'Đăng nhập Zalo'}
        </button>
      </div>

      {error ? <p className="err">{error}</p> : null}

      {showTestLogin ? (
        <details className="panel details-panel" open={forceDevLogin}>
          <summary>Đăng nhập thử nghiệm (trình duyệt)</summary>
          <p className="tiny muted">
            Dùng khi test ngoài Zalo Mini App. Nhập mã định danh thử nghiệm rồi
            tiếp tục.
          </p>
          <form onSubmit={onMockSubmit}>
            <label className="field">
              <span>Mã định danh thử nghiệm</span>
              <input
                value={zaloUserId}
                onChange={(e) => setZaloUserId(e.target.value)}
                autoComplete="username"
                required
                placeholder="teacher-dev-1"
              />
            </label>
            <button className="btn outline" type="submit" disabled={loading}>
              {loading ? 'Đang đăng nhập…' : 'Tiếp tục'}
            </button>
          </form>
        </details>
      ) : null}

      <p className="muted">
        <Link to="/">← Về trang chủ</Link>
      </p>
    </Shell>
  )
}
