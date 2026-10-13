import { useCallback, useEffect, useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import * as authApi from '../api/auth.js'
import { AUTH_EVENTS } from '../api/client.js'
import { clearStoredAuth, tokenStorage } from '../utils/storage.js'
import { disconnectEcho } from '../lib/echo.js'
import { AuthContext } from './AuthContext.js'

export function AuthProvider({ children }) {
  const queryClient = useQueryClient()
  const [user, setUser] = useState(null)
  const [isLoading, setIsLoading] = useState(() => Boolean(tokenStorage.get()))

  const clearSession = useCallback(() => {
    clearStoredAuth()
    disconnectEcho()
    queryClient.clear()
    setUser(null)
  }, [queryClient])

  useEffect(() => {
    const token = tokenStorage.get()
    if (!token) {
      setIsLoading(false)
      return
    }

    let cancelled = false

    authApi
      .me()
      .then((currentUser) => {
        if (!cancelled) setUser(currentUser)
      })
      .catch(() => {
        if (!cancelled) clearSession()
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [clearSession])

  useEffect(() => {
    const onUnauthorized = () => clearSession()
    window.addEventListener(AUTH_EVENTS.unauthorized, onUnauthorized)
    return () => window.removeEventListener(AUTH_EVENTS.unauthorized, onUnauthorized)
  }, [clearSession])

  const applySession = useCallback((result) => {
    if (result?.token) {
      tokenStorage.set(result.token)
    }
    if (result?.user) {
      setUser(result.user)
    }
    return result
  }, [])

  const login = useCallback(
    async (credentials) => applySession(await authApi.login(credentials)),
    [applySession],
  )

  const verifyOtp = useCallback(
    async (payload) => applySession(await authApi.verifyOtp(payload)),
    [applySession],
  )

  const verifyTwoFactor = useCallback(
    async (payload) => applySession(await authApi.verifyTwoFactor(payload)),
    [applySession],
  )

  const refreshUser = useCallback(async () => {
    const current = await authApi.me()
    setUser(current)
    return current
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Ignore network/permission errors; always clear the local session.
    }
    clearSession()
  }, [clearSession])

  const can = useCallback(
    (permission) => Boolean(user?.permissions?.includes(permission)),
    [user],
  )

  const hasRole = useCallback(
    (role) => Boolean(user?.roles?.includes(role)),
    [user],
  )

  const value = useMemo(
    () => ({
      user,
      isLoading,
      isAuthenticated: Boolean(user),
      login,
      verifyOtp,
      verifyTwoFactor,
      refreshUser,
      logout,
      endSession: clearSession,
      can,
      hasRole,
    }),
    [user, isLoading, login, verifyOtp, verifyTwoFactor, refreshUser, logout, clearSession, can, hasRole],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}