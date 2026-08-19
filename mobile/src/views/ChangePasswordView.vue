<script setup lang="ts">
/**
 * ChangePasswordView — screen-004--ganti-password-mobile route-level view
 * (mounted at /settings/password, meta.public = false, per
 * router/index.ts — requires an authenticated session, enforced by the
 * router's global auth guard). Full-page settings layout (uiux-spec "form"
 * screen type, mobile): simple header + ChangePasswordForm, matching
 * LoginView's split pattern (all interactive logic lives in
 * components/ChangePasswordForm.vue so it can be unit/component-tested in
 * isolation, matching test_scenarios' component name "ChangePasswordForm").
 */
import ChangePasswordForm from '@/components/ChangePasswordForm.vue'
</script>

<template>
  <main class="change-password-view">
    <header class="change-password-header">
      <h1 class="screen-title">Ganti Password</h1>
    </header>

    <section class="change-password-body">
      <ChangePasswordForm />
    </section>
  </main>
</template>

<style scoped>
.change-password-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
  padding: 24px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.change-password-header {
  width: 100%;
  max-width: 360px;
}

.screen-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}

.change-password-body {
  width: 100%;
  max-width: 360px;
}
</style>
