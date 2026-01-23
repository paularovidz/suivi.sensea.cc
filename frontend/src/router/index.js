import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Layouts - imported statically (small, always needed)
import AuthLayout from '@/components/layout/AuthLayout.vue'
import DashboardLayout from '@/components/layout/DashboardLayout.vue'
import BookingLayout from '@/components/layout/BookingLayout.vue'

// ============================================
// LAZY LOADED VIEWS - Enables CSS code splitting
// ============================================

// Auth Views (small bundle)
const LoginView = () => import('@/views/auth/LoginView.vue')
const VerifyView = () => import('@/views/auth/VerifyView.vue')

// Member Views (member bundle)
const MemberView = () => import('@/views/member/MemberView.vue')
const MemberPersonDetailView = () => import('@/views/member/MemberPersonDetailView.vue')
const MemberSessionDetailView = () => import('@/views/member/MemberSessionDetailView.vue')

// Dashboard Views (dashboard bundle)
const DashboardView = () => import('@/views/dashboard/DashboardView.vue')

// Persons Views (dashboard bundle)
const PersonsListView = () => import('@/views/persons/PersonsListView.vue')
const PersonDetailView = () => import('@/views/persons/PersonDetailView.vue')
const PersonFormView = () => import('@/views/persons/PersonFormView.vue')

// Sessions Views (dashboard bundle)
const SessionsListView = () => import('@/views/sessions/SessionsListView.vue')
const SessionDetailView = () => import('@/views/sessions/SessionDetailView.vue')
const SessionFormView = () => import('@/views/sessions/SessionFormView.vue')

// Proposals Views (dashboard bundle)
const ProposalsListView = () => import('@/views/proposals/ProposalsListView.vue')

// Users Views (dashboard bundle)
const UsersListView = () => import('@/views/users/UsersListView.vue')
const UserDetailView = () => import('@/views/users/UserDetailView.vue')
const UserFormView = () => import('@/views/users/UserFormView.vue')

// Settings Views (dashboard bundle)
const SettingsView = () => import('@/views/settings/SettingsView.vue')

// Promo Codes Views (dashboard bundle)
const PromoCodesListView = () => import('@/views/promo-codes/PromoCodesListView.vue')
const PromoCodeFormView = () => import('@/views/promo-codes/PromoCodeFormView.vue')
const PromoCodeDetailView = () => import('@/views/promo-codes/PromoCodeDetailView.vue')

// Agenda View (dashboard bundle)
const AgendaView = () => import('@/views/agenda/AgendaView.vue')

// Booking Views (booking bundle - public)
const BookingWizardView = () => import('@/views/booking/BookingWizardView.vue')
const BookingConfirmView = () => import('@/views/booking/BookingConfirmView.vue')
const BookingCancelView = () => import('@/views/booking/BookingCancelView.vue')
const BookingEmbedView = () => import('@/views/booking/BookingEmbedView.vue')

const routes = [
  // Auth routes
  {
    path: '/',
    component: AuthLayout,
    children: [
      {
        path: '',
        redirect: '/login'
      },
      {
        path: 'login',
        name: 'login',
        component: LoginView,
        meta: { guest: true }
      },
      {
        path: 'auth/verify/:token',
        name: 'verify',
        component: VerifyView,
        meta: { guest: true }
      }
    ]
  },

  // Member routes (non-admin users) - standalone views
  {
    path: '/app/member',
    name: 'member',
    component: MemberView,
    meta: { requiresAuth: true }
  },
  {
    path: '/app/member/persons/:id',
    name: 'member-person-detail',
    component: MemberPersonDetailView,
    meta: { requiresAuth: true }
  },
  {
    path: '/app/member/sessions/:id',
    name: 'member-session-detail',
    component: MemberSessionDetailView,
    meta: { requiresAuth: true }
  },

  // Dashboard routes (protected - admin only for most)
  {
    path: '/app',
    component: DashboardLayout,
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      {
        path: '',
        redirect: '/app/dashboard'
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: DashboardView
      },

      // Persons
      {
        path: 'persons',
        name: 'persons',
        component: PersonsListView
      },
      {
        path: 'persons/new',
        name: 'person-create',
        component: PersonFormView
      },
      {
        path: 'persons/:id',
        name: 'person-detail',
        component: PersonDetailView
      },
      {
        path: 'persons/:id/edit',
        name: 'person-edit',
        component: PersonFormView
      },

      // Sessions
      {
        path: 'sessions',
        name: 'sessions',
        component: SessionsListView
      },
      {
        path: 'sessions/new',
        name: 'session-create',
        component: SessionFormView
      },
      {
        path: 'sessions/new/:personId',
        name: 'session-create-for-person',
        component: SessionFormView
      },
      {
        path: 'sessions/:id',
        name: 'session-detail',
        component: SessionDetailView
      },
      {
        path: 'sessions/:id/edit',
        name: 'session-edit',
        component: SessionFormView
      },

      // Sensory Proposals
      {
        path: 'proposals',
        name: 'proposals',
        component: ProposalsListView
      },

      // Users
      {
        path: 'users',
        name: 'users',
        component: UsersListView
      },
      {
        path: 'users/new',
        name: 'user-create',
        component: UserFormView
      },
      {
        path: 'users/:id',
        name: 'user-detail',
        component: UserDetailView
      },
      {
        path: 'users/:id/edit',
        name: 'user-edit',
        component: UserFormView
      },

      // Settings
      {
        path: 'settings',
        name: 'settings',
        component: SettingsView
      },

      // Agenda (bookings)
      {
        path: 'agenda',
        name: 'agenda',
        component: AgendaView
      },

      // Promo Codes
      {
        path: 'promo-codes',
        name: 'promo-codes',
        component: PromoCodesListView
      },
      {
        path: 'promo-codes/new',
        name: 'promo-code-create',
        component: PromoCodeFormView
      },
      {
        path: 'promo-codes/:id',
        name: 'promo-code-detail',
        component: PromoCodeDetailView
      },
      {
        path: 'promo-codes/:id/edit',
        name: 'promo-code-edit',
        component: PromoCodeFormView
      }
    ]
  },

  // ============================================
  // PUBLIC BOOKING ROUTES (No authentication)
  // ============================================
  {
    path: '/booking',
    component: BookingLayout,
    meta: { public: true },
    children: [
      {
        path: '',
        name: 'booking',
        component: BookingWizardView
      },
      {
        path: 'confirm/:token',
        name: 'booking-confirm',
        component: BookingConfirmView
      },
      {
        path: 'cancel/:token',
        name: 'booking-cancel',
        component: BookingCancelView
      }
    ]
  },

  // Embed view (standalone, no layout)
  {
    path: '/booking/embed',
    name: 'booking-embed',
    component: BookingEmbedView,
    meta: { public: true, embed: true }
  },

  // Catch all - redirect to login
  {
    path: '/:pathMatch(.*)*',
    redirect: '/login'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
  // Public routes don't need auth check
  if (to.meta.public) {
    return next()
  }

  const authStore = useAuthStore()

  // Initialize auth from storage if not done
  if (!authStore.isAuthenticated) {
    authStore.initializeFromStorage()
  }

  // Check if route requires auth
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Check if route requires admin - redirect non-admin to member view
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return next({ name: 'member' })
  }

  // Check if route is for guests only
  if (to.meta.guest && authStore.isAuthenticated) {
    // Redirect to appropriate view based on role
    if (authStore.isAdmin) {
      return next({ name: 'dashboard' })
    } else {
      return next({ name: 'member' })
    }
  }

  next()
})

export default router
