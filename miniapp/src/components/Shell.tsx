import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import type { ReactNode } from 'react'
import { getToken, setToken } from '../api/client'
import logoUrl from '../assets/logo-kav.svg'
import { Icon } from './Icon'

export function Shell({
  children,
  title,
  showNav = true,
}: {
  children: ReactNode
  title?: string
  showNav?: boolean
}) {
  const loggedIn = Boolean(getToken())
  const location = useLocation()
  const navigate = useNavigate()
  const teacherArea = location.pathname.startsWith('/teacher')
  const onCreateClass = location.pathname === '/teacher/profiles/create'

  return (
    <div className="app">
      <header className="top">
        <NavLink to="/" className="brand" aria-label="KavMiniApp trang chủ">
          <img
            src={logoUrl}
            alt="Khan Academy Vietnam"
            className="brand-logo"
          />
        </NavLink>
        {title ? <p className="top-title">{title}</p> : null}
        {showNav && loggedIn ? (
          <div className="top-actions">
            {teacherArea && !onCreateClass ? (
              <NavLink to="/teacher/profiles/create" className="linkish btn-with-icon">
                <Icon name="plus" />
                Tạo lớp mới
              </NavLink>
            ) : null}
            <button
              type="button"
              className="linkish btn-with-icon"
              onClick={() => {
                setToken(null)
                navigate('/', { replace: true })
              }}
            >
              <Icon name="logout" />
              Đăng xuất
            </button>
          </div>
        ) : null}
      </header>
      <main className="main">{children}</main>
      <footer className="foot">KavMiniApp · phiếu bình chọn phụ huynh</footer>
    </div>
  )
}
