import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Layouts
import AuthLayout from '@/components/layout/AuthLayout.vue'
import DashboardLayout from '@/components/layout/DashboardLayout.vue'

// Auth Views
import LoginView from '@/views/auth/LoginView.vue'
import VerifyView from '@/views/auth/VerifyView.vue'

// Dashboard Views
import DashboardView from '@/views/dashboard/DashboardView.vue'

// Member Views
import MemberView from '@/views/member/MemberView.vue'
import MemberSessionDetailView from '@/views/member/MemberSessionDetailView.vue'

// Persons Views
import PersonsListView from '@/views/persons/PersonsListView.vue'
import PersonDetailView from '@/views/persons/PersonDetailView.vue'
import PersonFormView from '@/views/persons/PersonFormView.vue'

// Sessions Views
import SessionsListView from '@/views/sessions/SessionsListView.vue'
import SessionDetailView from '@/views/sessions/SessionDetailView.vue'
import SessionFormView from '@/views/sessions/SessionFormView.vue'

// Proposals Views
import ProposalsListView from '@/views/proposals/ProposalsListView.vue'

// Users Views
import UsersListView from '@/views/users/UsersListView.vue'
import UserDetailView from '@/views/users/UserDetailView.vue'
import UserFormView from '@/views/users/UserFormView.vue'

// Settings Views
import SettingsView from '@/views/settings/SettingsView.vue'

// Agenda View
import AgendaView from '@/views/agenda/AgendaView.vue'

// Booking Views (public)
import BookingLayout from '@/components/layout/BookingLayout.vue'
import BookingWizardView from '@/views/booking/BookingWizardView.vue'
import BookingConfirmView from '@/views/booking/BookingConfirmView.vue'
import BookingCancelView from '@/views/booking/BookingCancelView.vue'
import BookingEmbedView from '@/views/booking/BookingEmbedView.vue'

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
