import { useEffect, useRef, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import Sidebar from './Sidebar.jsx'
import Header from './Header.jsx'
import BackToTop from './BackToTop.jsx'
import AdminFooter from './AdminFooter.jsx'
import QueryLoadingBar from './QueryLoadingBar.jsx'
import MobileTabBar from './MobileTabBar.jsx'
import DocumentTitle from '../../hooks/usePageTitle.js'
import { OverlayProvider } from '../../context/OverlayProvider.jsx'
import { useMediaQuery } from '../../hooks/useMediaQuery.js'
import { cn } from '../../utils/cn.js'

export default function AppLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [userCollapsed, setUserCollapsed] = useState(null)
  const scrollRef = useRef(null)

  // The layout scrolls inside a <div> rather than the window, so the browser's
  // default scroll restoration never applies. Reset it on navigation so a new
  // page opens at its top instead of keeping the previous page's scroll offset
  // (most visible on phones, where you were just reading the bottom).
  const { pathname } = useLocation()
  useEffect(() => {
    scrollRef.current?.scrollTo({ top: 0, left: 0, behavior: 'instant' })
  }, [pathname])

  // Tablets (768–1023px) keep the icon rail so navigation never eats a third of
  // the viewport; the toggle expands it on demand. Phones use the drawer.
  // Until the user picks a state, the rail follows the viewport width.
  const isDesktop = useMediaQuery('(min-width: 1024px)')
  const collapsed = userCollapsed ?? !isDesktop

  return (
    <OverlayProvider>
      <div className="flex h-[100dvh] overflow-hidden bg-canvas">
        <DocumentTitle />
        <QueryLoadingBar />
        {/* No `key` here: remounting on every open/close reset the search box
            and group list, but it also destroyed the slide animation — a
            remounted element starts at its final position. Sidebar resets
            itself in an effect instead. */}
        <Sidebar
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          collapsed={collapsed}
        />
        <div className="flex h-[100dvh] min-w-0 flex-1 flex-col">
          <Header onToggleSidebar={() => setUserCollapsed(!collapsed)} />
          <div
            ref={scrollRef}
            // Marks the real page scroller so dialogs can lock it (body is
            // already overflow:hidden in globals.css, so locking <body> alone
            // would not stop the page behind an overlay from scrolling).
            data-scroll-lock
            className={cn(
              'min-h-0 flex-1 overflow-y-auto overscroll-contain',
              // Keep the page behind the drawer from scrolling on phones.
              sidebarOpen && 'max-md:overflow-hidden',
            )}
          >
            <div className="flex min-h-full flex-col">
              {/* Keying on the pathname remounts the pane on navigation, which
                  replays the entrance animation for the incoming page. */}
              <main key={pathname} className="animate-page-enter flex-1 px-4 py-4 sm:p-6">
                <Outlet />
              </main>
              <AdminFooter />
            </div>
          </div>
          <MobileTabBar onMore={() => setSidebarOpen(true)} />
        </div>
        <BackToTop scrollRef={scrollRef} />
      </div>
    </OverlayProvider>
  )
}
