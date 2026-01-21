<template>
  <div class="mb-8">
    <!-- Steps indicator -->
    <div class="flex items-center justify-between">
      <template v-for="(step, index) in steps" :key="index">
        <!-- Step circle -->
        <div class="flex flex-col items-center">
          <div
            :class="[
              'w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all duration-300',
              index + 1 < currentStep
                ? 'bg-indigo-600 text-white'
                : index + 1 === currentStep
                  ? 'bg-indigo-600 text-white ring-4 ring-indigo-500/30'
                  : 'bg-gray-700 text-gray-400'
            ]"
          >
            <template v-if="index + 1 < currentStep">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </template>
            <template v-else>
              {{ index + 1 }}
            </template>
          </div>
          <span
            :class="[
              'mt-2 text-xs font-medium hidden sm:block',
              index + 1 <= currentStep ? 'text-indigo-400' : 'text-gray-500'
            ]"
          >
            {{ step }}
          </span>
        </div>

        <!-- Connector line (not after last step) -->
        <div
          v-if="index < steps.length - 1"
          :class="[
            'flex-1 h-1 mx-2 rounded transition-all duration-300',
            index + 1 < currentStep ? 'bg-indigo-600' : 'bg-gray-700'
          ]"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
defineProps({
  currentStep: {
    type: Number,
    required: true
  },
  steps: {
    type: Array,
    default: () => ['Type', 'Personne', 'Date', 'Contact', 'Confirmation']
  }
})
</script>
