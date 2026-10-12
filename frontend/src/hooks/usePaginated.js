import { keepPreviousData, useQuery } from '@tanstack/react-query'

export function usePaginated(queryKey, fetcher, { page = 1, params = {}, enabled = true } = {}) {
  return useQuery({
    queryKey: [...queryKey, { page, ...params }],
    queryFn: () => fetcher({ ...params, page }),
    placeholderData: keepPreviousData,
    enabled,
  })
}

const MAX_PAGES = 100

async function fetchAllPages(fetcher, params) {
  const first = await fetcher({ ...params, page: 1 })
  const rows = [...(first.data ?? [])]
  const lastPage = Math.min(first.last_page ?? 1, MAX_PAGES)

  if (lastPage > 1) {
    const pages = await Promise.all(
      Array.from({ length: lastPage - 1 }, (_, i) =>
        fetcher({ ...params, page: i + 2 }),
      ),
    )
    for (const page of pages) {
      rows.push(...(page.data ?? []))
    }
  }

  return rows
}

export function useAll(queryKey, fetcher, { params = {}, enabled = true } = {}) {
  return useQuery({
    queryKey: [...queryKey, params],
    queryFn: () => fetchAllPages(fetcher, params),
    enabled,
  })
}