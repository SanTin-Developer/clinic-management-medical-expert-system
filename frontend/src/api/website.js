import { api } from './client.js'

/**
 * Public (unauthenticated) endpoints backing the informational website.
 */
export const publicWebsiteApi = {
  bootstrap: () => api.get('/website/bootstrap'),
  home: () => api.get('/website/home'),
  listPages: () => api.get('/website/pages'),
  getPage: (slug) => api.get(`/website/pages/${slug}`),
  listServices: () => api.get('/website/services'),
  listTeam: () => api.get('/website/team'),
  listGallery: (params) => api.get('/website/gallery', { params }),
  listArticles: (params) => api.get('/website/articles', { params }),
  getArticle: (slug) => api.get(`/website/articles/${slug}`),
}
