import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import Modal from '../../components/ui/Modal.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { PageBanner, Section } from '../../components/public/PublicUI.jsx'

export default function GalleryPage() {
  const [category, setCategory] = useState('')
  const [active, setActive] = useState(null)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'gallery', category],
    queryFn: () => publicWebsiteApi.listGallery(category ? { category } : undefined),
  })

  const items = data?.data ?? []
  const categories = data?.categories ?? []

  return (
    <>
      <PageBanner
        title="Facilities & Gallery"
        description="Take a look at our clinic, our spaces and the people behind your care."
      />
      <Section className="py-12">
        {categories.length > 0 ? (
          <div className="mb-8 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => setCategory('')}
              className={cn(
                'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                category === ''
                  ? 'border-azure-500 bg-azure-500 text-white'
                  : 'border-line bg-surface text-ink-muted hover:text-ink',
              )}
            >
              All
            </button>
            {categories.map((cat) => (
              <button
                key={cat}
                type="button"
                onClick={() => setCategory(cat)}
                className={cn(
                  'rounded-full border px-4 py-1.5 text-sm font-medium capitalize transition-colors',
                  category === cat
                    ? 'border-azure-500 bg-azure-500 text-white'
                    : 'border-line bg-surface text-ink-muted hover:text-ink',
                )}
              >
                {cat}
              </button>
            ))}
          </div>
        ) : null}

        {isLoading ? (
          <div className="grid place-items-center py-16">
            <Spinner label="Loading gallery…" />
          </div>
        ) : isError ? (
          <ErrorState onRetry={refetch} />
        ) : items.length === 0 ? (
          <EmptyState title="No photos yet" message="Gallery images will appear here soon." />
        ) : (
          <div className="columns-2 gap-4 sm:columns-3 lg:columns-4 [&>*]:mb-4">
            {items.map((item) =>
              item.image?.url ? (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setActive(item)}
                  className="group block w-full overflow-hidden rounded-2xl border border-line"
                >
                  <img
                    src={item.image.url}
                    alt={item.title ?? item.caption ?? 'Gallery image'}
                    className="w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                </button>
              ) : null,
            )}
          </div>
        )}
      </Section>

      <Modal
        open={Boolean(active)}
        onClose={() => setActive(null)}
        title={active?.title ?? undefined}
        size="xl"
      >
        {active?.image?.url ? (
          <figure>
            <img src={active.image.url} alt={active.title ?? 'Gallery image'} className="w-full rounded-xl" />
            {active.caption ? (
              <figcaption className="mt-3 text-sm text-ink-muted">{active.caption}</figcaption>
            ) : null}
          </figure>
        ) : null}
      </Modal>
    </>
  )
}
