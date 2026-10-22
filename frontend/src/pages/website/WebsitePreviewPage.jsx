import { useState } from 'react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import SitePreview from '../../components/website/SitePreview.jsx'
import { PREVIEW_PAGES, PUBLIC_SITE_URL } from '../../constants/sitePreview.js'

/**
 * Full-width preview of the public website inside the admin shell.
 *
 * Gives admins a large, dedicated canvas to review the site after content
 * changes, with the same page/device controls as the inline dashboard panel.
 */
export default function WebsitePreviewPage() {
  const [path, setPath] = useState('/')

  const activePage = PREVIEW_PAGES.find((page) => page.path === path)

  return (
    <>
      <PageHeader
        title="Website Preview"
        description={`Live preview of ${PUBLIC_SITE_URL}. Content updates appear after the page reloads.`}
      />

      <Card
        title={activePage ? `Previewing: ${activePage.label}` : 'Previewing website'}
        subtitle="Switch pages and device sizes to check how the public site looks."
        bodyClassName="p-4"
      >
        <SitePreview path={path} onPathChange={setPath} height="calc(100vh - 320px)" />
      </Card>
    </>
  )
}
