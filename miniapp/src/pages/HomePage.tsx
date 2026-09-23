import { Link } from 'react-router-dom'
import { Shell } from '../components/Shell'
import { getToken } from '../api/client'

export function HomePage() {
  const teacherLoggedIn = Boolean(getToken())

  return (
    <Shell title="Chọn vai trò">
      <section className="hero-block">
        <h1>Phiếu bình chọn lớp học</h1>
        <p className="lead">
          Giáo viên tạo lớp và gửi link. Phụ huynh mở link để gửi ý kiến.
        </p>
      </section>

      <div className="role-stack">
        <Link
          className="role-btn role-teacher"
          to={teacherLoggedIn ? '/teacher' : '/teacher/login'}
        >
          <span className="role-kicker">Giáo viên</span>
          <span className="role-label">Tôi là giáo viên</span>
          <span className="role-hint">
            {teacherLoggedIn
              ? 'Vào danh sách lớp của bạn'
              : 'Đăng nhập Zalo · tạo lớp · lấy link gửi PH'}
          </span>
        </Link>
        <Link className="role-btn role-parent" to="/parent">
          <span className="role-kicker">Phụ huynh</span>
          <span className="role-label">Tôi là phụ huynh</span>
          <span className="role-hint">Dán link cô gửi trong group Zalo</span>
        </Link>
      </div>
    </Shell>
  )
}
