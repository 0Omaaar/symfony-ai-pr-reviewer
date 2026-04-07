<template>
  <div class="admin-login-page">
    <div class="login-card">
      <div class="logo">
        <span class="logo-icon">⚙</span>
        <div>
          <p class="logo-title">autoPMR</p>
          <p class="logo-sub">Admin Panel</p>
        </div>
      </div>

      <h1 class="heading">Sign in to Admin</h1>
      <p class="sub">Internal access only. Not for regular users.</p>

      <form class="form" @submit.prevent="handleLogin">
        <div class="field">
          <label class="label" for="email">Email</label>
          <input
            id="email"
            v-model="email"
            class="input"
            type="email"
            placeholder="admin@admin.com"
            autocomplete="username"
            required
          />
        </div>

        <div class="field">
          <label class="label" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            class="input"
            type="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button class="submit-btn" type="submit" :disabled="loading">
          {{ loading ? "Signing in…" : "Sign in" }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { adminLogin, setAdminToken } from "@/api/admin";

const router = useRouter();
const email = ref("");
const password = ref("");
const error = ref("");
const loading = ref(false);

async function handleLogin() {
  error.value = "";
  loading.value = true;
  try {
    const { token } = await adminLogin(email.value, password.value);
    setAdminToken(token);
    await router.push({ name: "admin-dashboard" });
  } catch {
    error.value = "Invalid credentials. Please try again.";
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.admin-login-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: #0f0f14;
  font-family: "Manrope", sans-serif;
}

.login-card {
  width: min(420px, calc(100% - 32px));
  padding: 40px;
  background: #1a1a2e;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.6);
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 28px;
}

.logo-icon {
  font-size: 1.6rem;
  width: 44px;
  height: 44px;
  background: #2d2d4e;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  color: #e2e8f0;
}

.logo-sub {
  margin: 2px 0 0;
  font-size: 0.7rem;
  font-weight: 600;
  color: #6b7a99;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.heading {
  margin: 0 0 6px;
  font-size: 1.6rem;
  font-weight: 800;
  color: #f0f4ff;
  letter-spacing: -0.02em;
}

.sub {
  margin: 0 0 28px;
  font-size: 0.85rem;
  color: #6b7a99;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #94a3c4;
  letter-spacing: 0.02em;
}

.input {
  height: 44px;
  padding: 0 14px;
  background: #0f0f1a;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  color: #e2e8f0;
  font-size: 0.9rem;
  font-family: inherit;
  outline: none;
  transition: border-color 0.15s;
}

.input:focus {
  border-color: rgba(99, 102, 241, 0.6);
}

.input::placeholder {
  color: #3d4a6b;
}

.error {
  margin: 0;
  font-size: 0.82rem;
  color: #f87171;
  font-weight: 600;
}

.submit-btn {
  height: 46px;
  background: linear-gradient(135deg, #4f46e5, #6366f1);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s;
  margin-top: 4px;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  opacity: 0.92;
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: wait;
}
</style>
