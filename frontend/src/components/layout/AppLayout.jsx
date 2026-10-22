import { useRef, useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar.jsx'
import Header from './Header.jsx'
import BackToTop from './BackToTop.jsx'
import AdminFooter from './AdminFooter.jsx'
import DocumentTitle from '../../hooks/usePageTitle.js'
import { OverlayProvider } from '../../context/OverlayProvider.jsx'

export default function AppLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const scrollRef = useRef(null)

  return (
    <OverlayProvider>
      <div className="flex h-screen overflow-hidden bg-canvas">
        <DocumentTitle />
        <Sidebar
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          collapsed={collapsed}
        />
        <div className="flex h-screen min-w-0 flex-1 flex-col">
          <Header
            onMenu={() => setSidebarOpen(true)}
            onToggleSidebar={() => setCollapsed((current) => !current)}
          />
          <div ref={scrollRef} className="min-h-0 flex-1 overflow-y-auto">
            <div className="flex min-h-full flex-col">
              <main className="flex-1 p-4 sm:p-6">
                <Outlet />
              </main>
              <AdminFooter />
            </div>
          </div>
        </div>
        <BackToTop scrollRef={scrollRef} />
      </div>
    </OverlayProvider>
  )
}
