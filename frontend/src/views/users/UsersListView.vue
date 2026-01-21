<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { usersApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const router = useRouter()

const loading = ref(true)
const users = ref([])
const pagination = ref({ page: 1, limit: 20, total: 0, pages: 0 })
const confirmDialog = ref(null)
const userToDelete = ref(null)

onMounted(async () => {
  await loadUsers()
})

async function loadUsers(page = 1) {
  loading.value = true
  try {
    const response = await usersApi.getAll({ page, limit: 20 })
    users.value = response.data.data.users
    pagination.value = response.data.data.pagination
  } catch (e) {
    console.error('Error loading users:', e)
  } finally {
    loading.value = false
  }
}

function confirmDelete(user) {
  userToDelete.value = user
  confirmDialog.value?.open()
}

async function handleDelete() {
  if (!userToDelete.value) return
  try {
    await usersApi.delete(userToDelete.value.id)
    // Reload users to see updated status
    await loadUsers(pagination.value.page)
  } catch (e) {
    console.error('Error deactivating user:', e)
    alert(e.response?.data?.message || 'Erreur lors de la désactivation')
  } finally {
    userToDelete.value = null
  }
}

function getRoleBadgeClass(role) {
  return role === 'admin' ? 'badge-primary' : 'badge-gray'
}

function goToUser(userId) {
  router.push(`/app/users/${userId}`)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Utilisateurs</h1>
        <p class="text-gray-600 mt-1">Gestion des comptes professionnels</p>
      </div>
      <RouterLink to="/app/users/new" class="btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nouvel utilisateur
      </RouterLink>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <div class="card overflow-hidden">
        <table class="table">
          <thead>
            <tr>
              <th>Utilisateur</th>
              <th>Email</th>
              <th>Rôle</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in users"
              :key="user.id"
              @click="goToUser(user.id)"
              class="cursor-pointer hover:bg-gray-50"
            >
              <td>
                <div class="flex items-center">
                  <div class="w-8 h-8 rounded-full gradient-sensea flex items-center justify-center text-white font-medium text-sm mr-3">
                    {{ user.first_name[0] }}{{ user.last_name[0] }}
                  </div>
                  <div>
                    <div class="font-medium text-gray-900">{{ user.first_name }} {{ user.last_name }}</div>
                    <div class="text-sm text-gray-500">@{{ user.login }}</div>
                  </div>
                </div>
              </td>
              <td>{{ user.email }}</td>
              <td>
                <span :class="getRoleBadgeClass(user.role)">
                  {{ user.role === 'admin' ? 'Admin' : 'Membre' }}
                </span>
              </td>
              <td>
                <span :class="user.is_active ? 'badge-success' : 'badge-danger'">
                  {{ user.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="text-right" @click.stop>
                <button @click="confirmDelete(user)" class="btn-danger btn-sm">
                  Désactiver
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.pages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
          <div class="text-sm text-gray-500">
            {{ pagination.total }} utilisateur(s)
          </div>
          <div class="flex space-x-2">
            <button
              v-for="page in pagination.pages"
              :key="page"
              @click="loadUsers(page)"
              :class="[
                'px-3 py-1 text-sm rounded',
                page === pagination.page
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <ConfirmDialog
      ref="confirmDialog"
      title="Désactiver cet utilisateur ?"
      :message="`Êtes-vous sûr de vouloir désactiver le compte de ${userToDelete?.first_name} ${userToDelete?.last_name} ?`"
      confirm-text="Désactiver"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
