import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, usersApi } from '@/services/api'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const accessToken = ref(null)
  const refreshToken = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Impersonation state
  const isImpersonating = ref(false)
  const impersonator = ref(null)

  const isAuthenticated = computed(() => !!accessToken.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const fullName = computed(() => {
    if (!user.value) return ''
    return `${user.value.first_name} ${user.value.last_name}`
  })

  function initializeFromStorage() {
    const storedToken = localStorage.getItem('access_token')
    const storedRefresh = localStorage.getItem('refresh_token')
    const storedUser = localStorage.getItem('user')
    const storedImpersonating = localStorage.getItem('impersonating')
    const storedImpersonator = localStorage.getItem('impersonator')

    if (storedToken && storedUser) {
      accessToken.value = storedToken
      refreshToken.value = storedRefresh
      try {
        user.value = JSON.parse(storedUser)
        if (storedImpersonating === 'true' && storedImpersonator) {
          isImpersonating.value = true
          impersonator.value = JSON.parse(storedImpersonator)
        }
      } catch (e) {
        clearAuth()
      }
    }
  }

  function setAuth(data, impersonationData = null) {
    accessToken.value = data.access_token
    refreshToken.value = data.refresh_token
    user.value = data.user

    localStorage.setItem('access_token', data.access_token)
    localStorage.setItem('refresh_token', data.refresh_token)
    localStorage.setItem('user', JSON.stringify(data.user))

    // Handle impersonation state
    if (impersonationData) {
      isImpersonating.value = true
      impersonator.value = impersonationData.impersonator
      localStorage.setItem('impersonating', 'true')
      localStorage.setItem('impersonator', JSON.stringify(impersonationData.impersonator))
    } else {
      isImpersonating.value = false
      impersonator.value = null
      localStorage.removeItem('impersonating')
      localStorage.removeItem('impersonator')
    }
  }

  function clearAuth() {
    accessToken.value = null
    refreshToken.value = null
    user.value = null
    isImpersonating.value = false
    impersonator.value = null

    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('user')
    localStorage.removeItem('impersonating')
    localStorage.removeItem('impersonator')
  }

  async function requestMagicLink(email) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.requestMagicLink(email)
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Une erreur est survenue'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function verifyMagicLink(token) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.verifyMagicLink(token)
      setAuth(response.data.data)
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Lien invalide ou expiré'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      if (refreshToken.value) {
        await authApi.logout(refreshToken.value)
      }
    } catch (e) {
      // Ignore errors during logout
    } finally {
      clearAuth()
      router.push('/login')
    }
  }

  async function fetchCurrentUser() {
    try {
      const response = await usersApi.getMe()
      user.value = response.data.data
      localStorage.setItem('user', JSON.stringify(user.value))
      return user.value
    } catch (e) {
      if (e.response?.status === 401) {
        clearAuth()
        router.push('/login')
      }
      throw e
    }
  }

  async function impersonate(userId) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.impersonate(userId)
      const data = response.data.data

      // Set auth with impersonation data
      setAuth(data, {
        impersonating: true,
        impersonator: data.impersonator
      })

      // Redirect to member view when impersonating a non-admin
      if (data.user.role !== 'admin') {
        router.push('/app/member')
      } else {
        router.push('/app/dashboard')
      }

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur lors de l\'impersonation'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function stopImpersonate() {
    loading.value = true
    error.value = null

    // Save the impersonated user's ID before stopping
    const impersonatedUserId = user.value?.id

    try {
      const response = await authApi.stopImpersonate()
      const data = response.data.data

      // Set auth without impersonation
      setAuth(data, null)

      // Redirect to the user detail page of the impersonated user
      if (impersonatedUserId) {
        router.push(`/app/users/${impersonatedUserId}`)
      } else {
        router.push('/app/dashboard')
      }

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur lors du retour au compte admin'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    accessToken,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    fullName,
    isImpersonating,
    impersonator,
    initializeFromStorage,
    requestMagicLink,
    verifyMagicLink,
    logout,
    fetchCurrentUser,
    clearAuth,
    impersonate,
    stopImpersonate
  }
})
