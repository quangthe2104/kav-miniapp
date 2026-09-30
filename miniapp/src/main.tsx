import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles.css'

// Zalo Mini App serves its own index.html with root element #app.
createRoot((document.getElementById('app') ?? document.getElementById('root'))!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
