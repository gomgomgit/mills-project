<script setup lang="ts">
/**
 * LoginForm — screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Wraps POST /api/login (via stores/auth.ts's login(), which adds the
 * `device_name` that selects this screen's Sanctum token-issuing branch on
 * the backend) with:
 *   - client-side mirrors of business_logic steps 1-2 (required fields,
 *     password format) so the "Format Password Tidak Valid" scenario shows
 *     an inline error without a round trip — mirrors
 *     App\Livewire\Auth\LoginForm's rules() (screen-001).
 *   - the "Tidak Ada Koneksi Saat Login Pertama" client-side-only guard
 *     (useConnectivityGuard) — blocks submit before any API call.
 *   - error display + password-clear-on-error per edge_case_handling.
 *
 * States: idle / submitting / error / success (uiux-spec "auth" screen
 * type). Business Area is a predefined dropdown, not free text, per
 * uiux-spec form pattern — see loadBusinessUnits()/known_issues for the
 * data-source gap (no business-units listing endpoint exists yet in this
 * screen's api_contracts).
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { toDisplayMessage } from '@/services/errorHandler'
import { useAuthStore } from '@/stores/auth'
import { useConnectivityGuard } from '@/composables/useConnectivityGuard'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'

interface BusinessUnitOption {
  id: string
  name: string
}

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { blocksFirstLogin, blockMessage } = useConnectivityGuard()

type FormStatus = 'idle' | 'submitting' | 'error' | 'success'

const status = ref<FormStatus>('idle')
const errorMessage = ref<string | null>(null)
const showPassword = ref(false)

const form = reactive({
  username: '',
  password: '',
  business_unit_id: '',
})

const fieldErrors = reactive<{ username: string | null; password: string | null; business_unit_id: string | null }>({
  username: null,
  password: null,
  business_unit_id: null,
})

const businessUnits = ref<BusinessUnitOption[]>([])
const businessUnitsLoadFailed = ref(false)

// SearchableSelect's fixed { value, label } option shape — this component
// owns no filtering logic of its own, just maps the already-loaded, full
// businessUnits list once per change.
const businessUnitOptions = computed<SearchableSelectOption[]>(() =>
  businessUnits.value.map((unit) => ({ value: unit.id, label: unit.name })),
)

/**
 * KNOWN GAP: no `GET /api/business-units` (or equivalent public listing)
 * endpoint exists in this screen's api_contracts / the current
 * implementation plan — screen-001's equivalent dropdown is populated
 * server-side inside the Livewire component's mount() (BusinessUnit::
 * orderBy('name')->get()), which has no mobile/API equivalent yet. This
 * fetch is written against the REST-conventional path so it starts working
 * the moment that endpoint is added; until then it fails gracefully (empty
 * dropdown + inline notice) rather than crashing the screen. See
 * known_issues in the implementation report.
 */
async function loadBusinessUnits() {
  try {
    const response = await apiClient.get('/api/business-units')
    const payload = response.data
    businessUnits.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
  } catch {
    businessUnitsLoadFailed.value = true
    businessUnits.value = []
  }
}

onMounted(() => {
  loadBusinessUnits()
})

/**
 * Mirrors AuthService::validatePasswordFormat() (min 6 chars, at least one
 * alphanumeric char, at least one symbol).
 */
function isPasswordFormatValid(password: string): boolean {
  const hasMinLength = password.length >= 6
  const hasAlphanumeric = /[A-Za-z0-9]/.test(password)
  const hasSymbol = /[^A-Za-z0-9]/.test(password)

  return hasMinLength && hasAlphanumeric && hasSymbol
}

function validate(): boolean {
  fieldErrors.username = form.username.trim() ? null : 'Username wajib diisi.'
  fieldErrors.business_unit_id = form.business_unit_id ? null : 'Business area wajib dipilih.'

  if (!form.password) {
    fieldErrors.password = 'Password wajib diisi.'
  } else if (!isPasswordFormatValid(form.password)) {
    fieldErrors.password = 'Password minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.'
  } else {
    fieldErrors.password = null
  }

  return !fieldErrors.username && !fieldErrors.password && !fieldErrors.business_unit_id
}

const isSubmitting = computed(() => status.value === 'submitting')

async function onSubmit() {
  errorMessage.value = null

  // "Tidak Ada Koneksi Saat Login Pertama" — client-side only, no API call.
  if (blocksFirstLogin.value) {
    status.value = 'error'
    errorMessage.value = blockMessage

    return
  }

  if (!validate()) {
    return
  }

  status.value = 'submitting'

  try {
    await authStore.login({
      username: form.username,
      password: form.password,
      business_unit_id: form.business_unit_id,
    })

    status.value = 'success'

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/home'
    router.push(redirect)
  } catch (err) {
    status.value = 'error'
    // edge_case_handling: kredensial salah -> field password dikosongkan.
    // Applied for every error path here (matches screen-001's LoginForm.php
    // behavior of clearing the password on any rejected attempt).
    form.password = ''
    errorMessage.value = toDisplayMessage(err as NormalizedApiError)
  }
}
</script>

<template>
  <form class="login-form" novalidate @submit.prevent="onSubmit">
    <div v-if="authStore.sessionExpiredOffline" class="banner banner-info">
      Sesi lokal Anda telah kedaluwarsa. Silakan login kembali secara online.
    </div>

    <div v-if="status === 'error' && errorMessage" class="banner banner-error" role="alert">
      {{ errorMessage }}
    </div>

    <div class="field">
      <label for="username">
        Username / NIK <span class="required">*</span>
      </label>
      <input
        id="username"
        v-model="form.username"
        type="text"
        autocomplete="username"
        :disabled="isSubmitting"
        @blur="validate"
      />
      <p v-if="fieldErrors.username" class="field-error">{{ fieldErrors.username }}</p>
    </div>

    <div class="field">
      <label for="password">
        Password <span class="required">*</span>
      </label>
      <div class="password-input-group">
        <input
          id="password"
          v-model="form.password"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          :disabled="isSubmitting"
          @blur="validate"
        />
        <button
          type="button"
          class="toggle-password-button"
          :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
          :aria-pressed="showPassword"
          tabindex="-1"
          @click="showPassword = !showPassword"
        >
          <svg v-if="showPassword" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
            <line x1="1" y1="1" x2="23" y2="23" />
          </svg>
          <svg v-else viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
        </button>
      </div>
      <p v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
    </div>

    <div class="field">
      <label for="business_unit_id">
        Business Area <span class="required">*</span>
      </label>
      <SearchableSelect
        id="business_unit_id"
        v-model="form.business_unit_id"
        :options="businessUnitOptions"
        placeholder="Pilih Business Area"
        :disabled="isSubmitting"
        @select="validate"
      />
      <p v-if="fieldErrors.business_unit_id" class="field-error">{{ fieldErrors.business_unit_id }}</p>
      <p v-if="businessUnitsLoadFailed" class="field-error">
        Daftar Business Area tidak dapat dimuat. Coba lagi saat online.
      </p>
    </div>

    <button type="submit" class="submit-button" :disabled="isSubmitting">
      {{ isSubmitting ? 'Memproses…' : 'Login' }}
    </button>

    <a href="#" class="forgot-password-link" aria-disabled="true" @click.prevent>
      Lupa Password
    </a>
  </form>
</template>

<style scoped>
.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  font-family: 'Inter', sans-serif;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.required {
  color: #dc2626;
}

input {
  box-sizing: border-box;
  min-height: 44px;
  padding: 0 12px;
  background-color: #edebeb;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 16px;
  font-family: inherit;
  color: #1f2937;
}

input:focus {
  outline: 2px solid #249360;
  outline-offset: 1px;
}

input:disabled {
  opacity: 0.6;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
  margin: 0;
}

.password-input-group {
  position: relative;
}

.password-input-group input {
  width: 100%;
  padding-right: 44px;
}

.toggle-password-button {
  position: absolute;
  top: 50%;
  right: 4px;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  color: #249360;
  background: transparent;
  border: none;
  cursor: pointer;
}

.submit-button {
  min-height: 44px;
  border: none;
  border-radius: 8px;
  background-color: #249360;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.submit-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.forgot-password-link {
  align-self: center;
  min-height: 44px;
  display: flex;
  align-items: center;
  color: #6b7280;
  font-size: 14px;
  text-decoration: none;
  cursor: not-allowed;
}

.banner {
  min-height: 44px;
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 14px;
}

.banner-error {
  background-color: #fee2e2;
  color: #dc2626;
}

.banner-info {
  background-color: #d6f6e5;
  color: #1d7a4e;
}
</style>
