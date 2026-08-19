<script setup lang="ts">
/**
 * ChangePasswordForm — screen-004--ganti-password-mobile /
 * usecase-004--ganti-password-mobile.
 *
 * Wraps PATCH /api/me/password (same endpoint as screen-003's web
 * ChangePasswordForm, see App\Http\Controllers\Api\AuthController::
 * changePassword() / App\Services\AuthService::changePassword() — the
 * route's guard/role list was merged to also accept a Sanctum-authenticated
 * operator/supervisor mobile requester, see routes/api.php's merge-decision
 * comment) with:
 *   - client-side mirrors of business_logic step 2 (new_password format)
 *     and step 3 (confirmation match), so inline errors show without a
 *     round trip — mirrors App\Livewire\Settings\ChangePasswordForm::rules().
 *   - the "Tidak ada koneksi internet" client-side-only guard
 *     (useConnectivityGuard's generic `blocksAction`, generalized from
 *     screen-002's login-only `blocksFirstLogin` — see that composable's
 *     header comment) — blocks submit before any API call, unconditionally
 *     (no "already logged in before" exception, unlike login).
 *   - error display + field-clear-on-error per edge_case_handling, mirroring
 *     App\Livewire\Settings\ChangePasswordForm::save()'s per-exception
 *     field resets. The API only distinguishes OLD_PASSWORD_INCORRECT /
 *     PASSWORD_CONFIRMATION_MISMATCH from each other by message text (no
 *     machine-readable error code in shared_decisions.error_format — see
 *     ApiExceptionHandler), so this form matches on the two exceptions'
 *     known default messages to decide which field(s) to clear; the
 *     display banner itself always just shows the server message.
 *
 * States: idle / submitting / error / success (uiux-spec "form" screen
 * type pattern, mobile — matches LoginForm's status model for consistency).
 */
import { computed, reactive, ref } from 'vue'
import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { toDisplayMessage } from '@/services/errorHandler'
import { useConnectivityGuard } from '@/composables/useConnectivityGuard'

const OFFLINE_ACTION_MESSAGE = 'Tidak ada koneksi internet. Ganti password memerlukan koneksi internet.'

const { blocksAction, offlineActionMessage } = useConnectivityGuard({
  offlineActionMessage: OFFLINE_ACTION_MESSAGE,
})

type FormStatus = 'idle' | 'submitting' | 'error' | 'success'

const status = ref<FormStatus>('idle')
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const form = reactive({
  old_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const fieldErrors = reactive<{
  old_password: string | null
  new_password: string | null
  new_password_confirmation: string | null
}>({
  old_password: null,
  new_password: null,
  new_password_confirmation: null,
})

/**
 * Mirrors AuthService::validatePasswordFormat() (min 6 chars, at least one
 * alphanumeric char, at least one symbol) — same rule LoginForm's
 * isPasswordFormatValid() mirrors for the login password field.
 */
function isPasswordFormatValid(password: string): boolean {
  const hasMinLength = password.length >= 6
  const hasAlphanumeric = /[A-Za-z0-9]/.test(password)
  const hasSymbol = /[^A-Za-z0-9]/.test(password)

  return hasMinLength && hasAlphanumeric && hasSymbol
}

function validate(): boolean {
  fieldErrors.old_password = form.old_password ? null : 'Password lama wajib diisi.'

  if (!form.new_password) {
    fieldErrors.new_password = 'Password baru wajib diisi.'
  } else if (!isPasswordFormatValid(form.new_password)) {
    fieldErrors.new_password = 'Password baru minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.'
  } else {
    fieldErrors.new_password = null
  }

  if (!form.new_password_confirmation) {
    fieldErrors.new_password_confirmation = 'Konfirmasi password wajib diisi.'
  } else if (form.new_password_confirmation !== form.new_password) {
    fieldErrors.new_password_confirmation = 'Konfirmasi password tidak cocok dengan password baru.'
  } else {
    fieldErrors.new_password_confirmation = null
  }

  return !fieldErrors.old_password && !fieldErrors.new_password && !fieldErrors.new_password_confirmation
}

const isSubmitting = computed(() => status.value === 'submitting')

// Known default messages of the two non-validation 422 exceptions
// (App\Exceptions\OldPasswordIncorrectException /
// App\Exceptions\PasswordConfirmationMismatchException) — used only to
// decide which field(s) to clear client-side (see header comment); the
// displayed banner text always comes straight from the server response.
const OLD_PASSWORD_INCORRECT_MESSAGE = 'Password lama salah.'
const PASSWORD_CONFIRMATION_MISMATCH_MESSAGE = 'Konfirmasi password tidak cocok dengan password baru.'

async function onSubmit() {
  errorMessage.value = null
  successMessage.value = null

  // "Tidak ada koneksi internet" — client-side only, no API call.
  if (blocksAction.value) {
    status.value = 'error'
    errorMessage.value = offlineActionMessage

    return
  }

  if (!validate()) {
    return
  }

  status.value = 'submitting'

  try {
    const response = await apiClient.patch('/api/me/password', {
      old_password: form.old_password,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation,
    })

    status.value = 'success'
    successMessage.value = response.data?.message ?? 'Password berhasil diubah.'
    form.old_password = ''
    form.new_password = ''
    form.new_password_confirmation = ''
  } catch (err) {
    status.value = 'error'
    const normalized = err as NormalizedApiError
    const message = toDisplayMessage(normalized)
    errorMessage.value = message

    // edge_case_handling: clear the field(s) relevant to the rejected
    // attempt (mirrors App\Livewire\Settings\ChangePasswordForm::save()).
    if (message === OLD_PASSWORD_INCORRECT_MESSAGE) {
      form.old_password = ''
    } else if (message === PASSWORD_CONFIRMATION_MISMATCH_MESSAGE) {
      form.new_password = ''
      form.new_password_confirmation = ''
    } else if (normalized.errors) {
      // VALIDATION_ERROR — server-side re-validation caught something the
      // client-side rules() missed (defense in depth); clear the new
      // password fields the same way the Livewire form does.
      form.new_password = ''
      form.new_password_confirmation = ''
    }
  }
}
</script>

<template>
  <form class="change-password-form" novalidate @submit.prevent="onSubmit">
    <div v-if="status === 'success' && successMessage" class="banner banner-success" role="status">
      {{ successMessage }}
    </div>

    <div v-if="status === 'error' && errorMessage" class="banner banner-error" role="alert">
      {{ errorMessage }}
    </div>

    <div class="field">
      <label for="old_password">
        Password Lama <span class="required">*</span>
      </label>
      <input
        id="old_password"
        v-model="form.old_password"
        type="password"
        autocomplete="current-password"
        :disabled="isSubmitting"
        @blur="validate"
      />
      <p v-if="fieldErrors.old_password" class="field-error">{{ fieldErrors.old_password }}</p>
    </div>

    <div class="field">
      <label for="new_password">
        Password Baru <span class="required">*</span>
      </label>
      <input
        id="new_password"
        v-model="form.new_password"
        type="password"
        autocomplete="new-password"
        :disabled="isSubmitting"
        @blur="validate"
      />
      <p v-if="fieldErrors.new_password" class="field-error">{{ fieldErrors.new_password }}</p>
    </div>

    <div class="field">
      <label for="new_password_confirmation">
        Konfirmasi Password Baru <span class="required">*</span>
      </label>
      <input
        id="new_password_confirmation"
        v-model="form.new_password_confirmation"
        type="password"
        autocomplete="new-password"
        :disabled="isSubmitting"
        @blur="validate"
      />
      <p v-if="fieldErrors.new_password_confirmation" class="field-error">
        {{ fieldErrors.new_password_confirmation }}
      </p>
    </div>

    <button type="submit" class="submit-button" :disabled="isSubmitting">
      {{ isSubmitting ? 'Memproses…' : 'Simpan' }}
    </button>
  </form>
</template>

<style scoped>
.change-password-form {
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

.banner-success {
  background-color: #d6f6e5;
  color: #1d7a4e;
}
</style>
