import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])
  let nextId = 1

  /**
   * Ajoute un toast
   * @param {Object} options - Options du toast
   * @param {string} options.message - Message à afficher
   * @param {string} options.type - Type: 'success', 'error', 'warning', 'info'
   * @param {number} options.duration - Durée en ms (0 = persistant)
   * @param {string} options.title - Titre optionnel
   */
  function addToast({ message, type = 'info', duration = 5000, title = null }) {
    const id = nextId++

    const toast = {
      id,
      message,
      type,
      title,
      createdAt: Date.now()
    }

    toasts.value.push(toast)

    // Auto-remove après la durée spécifiée
    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }

    return id
  }

  function removeToast(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  function clearAll() {
    toasts.value = []
  }

  // Helpers pour les types courants
  function success(message, title = null) {
    return addToast({ message, type: 'success', title, duration: 4000 })
  }

  function error(message, title = null) {
    return addToast({ message, type: 'error', title, duration: 6000 })
  }

  function warning(message, title = null) {
    return addToast({ message, type: 'warning', title, duration: 5000 })
  }

  function info(message, title = null) {
    return addToast({ message, type: 'info', title, duration: 5000 })
  }

  /**
   * Traduit une erreur API en message utilisateur
   * @param {Error|Object} err - Erreur de l'API
   * @returns {string} Message traduit
   */
  function parseApiError(err) {
    const response = err?.response?.data

    // Erreurs de validation avec détails
    if (response?.errors && typeof response.errors === 'object') {
      const errors = response.errors
      const messages = []

      for (const [field, fieldErrors] of Object.entries(errors)) {
        const fieldMessages = Array.isArray(fieldErrors) ? fieldErrors : [fieldErrors]
        messages.push(...fieldMessages.map(msg => translateFieldError(field, msg)))
      }

      return messages.join('\n')
    }

    // Message d'erreur simple
    if (response?.message) {
      return translateErrorMessage(response.message)
    }

    // Erreur réseau
    if (err?.code === 'ERR_NETWORK') {
      return 'Impossible de contacter le serveur. Vérifiez votre connexion internet.'
    }

    // Erreur générique
    return err?.message || 'Une erreur inattendue est survenue'
  }

  /**
   * Traduit un message d'erreur de champ
   */
  function translateFieldError(field, message) {
    const fieldNames = {
      client_email: 'Email',
      client_phone: 'Téléphone',
      client_first_name: 'Prénom',
      client_last_name: 'Nom',
      person_first_name: 'Prénom du bénéficiaire',
      person_last_name: 'Nom du bénéficiaire',
      session_date: 'Date de la séance',
      siret: 'SIRET',
      company_name: 'Nom de l\'entreprise',
      gdpr_consent: 'Consentement RGPD',
      email: 'Email',
      phone: 'Téléphone',
      first_name: 'Prénom',
      last_name: 'Nom'
    }

    const fieldName = fieldNames[field] || field

    // Patterns de messages d'erreur courants
    const patterns = [
      { pattern: /invalid.*phone/i, message: `${fieldName} : format invalide. Utilisez le format 06 12 34 56 78` },
      { pattern: /invalid.*email/i, message: `${fieldName} : adresse email invalide` },
      { pattern: /required/i, message: `${fieldName} est requis` },
      { pattern: /too short/i, message: `${fieldName} est trop court` },
      { pattern: /too long/i, message: `${fieldName} est trop long` },
      { pattern: /must be.*digits/i, message: `${fieldName} : doit contenir 14 chiffres` },
      { pattern: /already.*taken/i, message: `${fieldName} est déjà utilisé` },
      { pattern: /invalid.*format/i, message: `${fieldName} : format invalide` },
      { pattern: /must be a valid/i, message: `${fieldName} : format invalide` }
    ]

    for (const { pattern, message: msg } of patterns) {
      if (pattern.test(message)) {
        return msg
      }
    }

    return `${fieldName} : ${message}`
  }

  /**
   * Traduit un message d'erreur global
   */
  function translateErrorMessage(message) {
    const translations = {
      'Validation failed': 'Certaines informations sont incorrectes',
      'Slot no longer available': 'Ce créneau n\'est plus disponible. Veuillez en choisir un autre.',
      'Too many bookings': 'Vous avez atteint le nombre maximum de réservations autorisées',
      'Email already exists': 'Cette adresse email est déjà utilisée',
      'Invalid captcha': 'Vérification de sécurité échouée. Veuillez réessayer.',
      'Booking not found': 'Réservation non trouvée',
      'Invalid token': 'Lien invalide ou expiré',
      'Booking already confirmed': 'Cette réservation a déjà été confirmée',
      'Booking already cancelled': 'Cette réservation a déjà été annulée',
      'Cannot cancel past booking': 'Impossible d\'annuler une réservation passée',
      'Unauthorized': 'Vous devez être connecté pour effectuer cette action',
      'Forbidden': 'Vous n\'avez pas les droits pour effectuer cette action',
      'Not found': 'Ressource non trouvée',
      'Server error': 'Erreur serveur. Veuillez réessayer plus tard.'
    }

    // Recherche exacte
    if (translations[message]) {
      return translations[message]
    }

    // Recherche partielle
    for (const [key, value] of Object.entries(translations)) {
      if (message.toLowerCase().includes(key.toLowerCase())) {
        return value
      }
    }

    return message
  }

  /**
   * Affiche une erreur API sous forme de toast
   */
  function apiError(err, fallbackMessage = 'Une erreur est survenue') {
    const message = parseApiError(err) || fallbackMessage
    return error(message)
  }

  return {
    toasts,
    addToast,
    removeToast,
    clearAll,
    success,
    error,
    warning,
    info,
    parseApiError,
    apiError
  }
})
