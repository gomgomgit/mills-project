<script setup lang="ts">
/**
 * LoginView — screen-002--login-mobile route-level view (mounted at
 * /login, meta.public = true, per router/index.ts). Full-page auth layout
 * (uiux-spec "auth" screen type, mobile): logo/splash header + LoginForm.
 * All interactive logic lives in components/LoginForm.vue so it can be
 * unit/component-tested in isolation (matches test_scenarios' component
 * name "LoginForm").
 */
import LoginForm from '@/components/LoginForm.vue'
</script>

<template>
  <main class="login-view">
    <header class="login-header">
      <h1 class="app-name">Mills Smart Log</h1>
    </header>

    <section class="login-body">
      <LoginForm />
    </section>
  </main>
</template>

<style scoped>
.login-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 32px;
  padding: 24px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.login-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.app-name {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #1f2937;
}

.login-body {
  width: 100%;
  max-width: 360px;
}
</style>
