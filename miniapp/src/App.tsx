import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { HomePage } from './pages/HomePage'
import { TeacherLoginPage } from './pages/TeacherLoginPage'
import { TeacherProfilesPage } from './pages/TeacherProfilesPage'
import { TeacherCreateProfilePage } from './pages/TeacherCreateProfilePage'
import { TeacherClassFormPage } from './pages/TeacherClassFormPage'
import {
  ParentTokenEntryPage,
  ParentVotePage,
} from './pages/ParentVotePage'

export default function App() {
  return (
    <BrowserRouter basename="/miniapp">
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/teacher/login" element={<TeacherLoginPage />} />
        <Route path="/teacher" element={<TeacherProfilesPage />} />
        <Route
          path="/teacher/profiles/create"
          element={<TeacherCreateProfilePage />}
        />
        <Route
          path="/teacher/profiles/:id"
          element={<Navigate to="/teacher" replace />}
        />
        <Route
          path="/teacher/class-forms/:id"
          element={<TeacherClassFormPage />}
        />
        <Route path="/parent" element={<ParentTokenEntryPage />} />
        <Route path="/vote/:token" element={<ParentVotePage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  )
}
