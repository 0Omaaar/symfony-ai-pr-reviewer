<template>
  <section class="login-page">
    <div class="background-glow glow-a" aria-hidden="true"></div>
    <div class="background-glow glow-b" aria-hidden="true"></div>

    <article class="login-card">
      <p class="eyebrow">Secure Access</p>
      <h1 class="title">Sign in to AutoPMR</h1>
      <p class="subtitle">
        Continue with GitHub to manage repositories, review pull requests, and keep your PMR workflow in one place.
      </p>

      <button class="github-button" type="button" :disabled="isRedirecting" @click="login">
        <svg class="github-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path
            fill="currentColor"
            d="M12 .5a12 12 0 0 0-3.79 23.39c.6.1.82-.26.82-.58v-2.04c-3.34.73-4.04-1.62-4.04-1.62-.55-1.4-1.34-1.77-1.34-1.77-1.1-.76.08-.75.08-.75 1.2.08 1.84 1.25 1.84 1.25 1.08 1.85 2.83 1.32 3.52 1.01.1-.79.42-1.32.77-1.62-2.67-.3-5.47-1.34-5.47-5.94 0-1.31.47-2.39 1.24-3.24-.12-.3-.54-1.53.12-3.18 0 0 1.02-.33 3.33 1.24A11.5 11.5 0 0 1 12 6.32a11.5 11.5 0 0 1 3.03.41c2.3-1.56 3.32-1.24 3.32-1.24.66 1.65.24 2.88.12 3.18.77.85 1.24 1.93 1.24 3.24 0 4.61-2.8 5.63-5.48 5.94.43.38.82 1.12.82 2.26v3.35c0 .32.21.69.82.58A12 12 0 0 0 12 .5Z"
          />
        </svg>
        <span>{{ isRedirecting ? "Redirecting..." : "Continue with GitHub" }}</span>
      </button>

      <p v-if="error" class="error-message">
        {{ error }}
      </p>

      <p class="footnote">
        By signing in, you authorize repository access according to your GitHub permissions.
      </p>
    </article>
  </section>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const isRedirecting = ref(false);
const error = route.query.error ? "Login failed. Please try again." : "";

function login() {
  isRedirecting.value = true;
  window.location.href = `${apiBaseUrl}/connect/github`;
}
</script>

<style scoped>
.login-page {
  position: relative;
  min-height: 100vh;
  display: grid;
  place-items: center;
  overflow: hidden;
  background:
    radial-gradient(circle at 10% 10%, #d0f0ff 0%, transparent 50%),
    radial-gradient(circle at 90% 90%, #fde8d0 0%, transparent 50%),
    linear-gradient(145deg, #f4f8ff 0%, #eef3ff 50%, #f0fffe 100%);
  font-family: var(--font-sans);
}

.background-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(50px);
  opacity: 0.45;
  animation: drift 14s ease-in-out infinite;
  pointer-events: none;
}

.glow-a { width: 320px; height: 320px; left: -80px; top: -60px; background: #67e8f9; }
.glow-b { width: 360px; height: 360px; right: -90px; bottom: -60px; background: #fdba74; animation-delay: -5s; }

.login-card {
  position: relative;
  z-index: 1;
  width: min(480px, calc(100% - 36px));
  padding: 40px;
  border-radius: 24px;
  background: rgba(255,255,255,0.92);
  border: 1px solid rgba(255,255,255,0.85);
  box-shadow:
    0 2px 8px rgba(12,26,46,0.04),
    0 24px 56px -20px rgba(12,26,46,0.22);
  backdrop-filter: blur(12px);
  animation: rise 0.4s ease-out both;
}

.eyebrow {
  margin: 0 0 10px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--accent);
}

.title {
  margin: 0;
  color: var(--ink-strong);
  font-size: clamp(1.8rem, 4vw, 2.4rem);
  line-height: 1.1;
  letter-spacing: -0.03em;
  font-weight: 800;
}

.subtitle {
  margin: 12px 0 28px;
  color: var(--ink-soft);
  font-size: 0.95rem;
  line-height: 1.65;
}

.github-button {
  width: 100%;
  height: 52px;
  border: 1px solid transparent;
  border-radius: 14px;
  padding: 0 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #0c1e3b 0%, #1c3d6e 100%);
  cursor: pointer;
  font-family: var(--font-sans);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
  box-shadow: 0 8px 24px -10px rgba(12,30,59,0.65);
}

.github-button:hover:enabled {
  transform: translateY(-1px);
  box-shadow: 0 12px 32px -12px rgba(12,30,59,0.75);
}

.github-button:focus-visible {
  outline: 3px solid var(--accent-mid);
  outline-offset: 2px;
}

.github-button:disabled { cursor: wait; opacity: 0.8; }

.github-icon { width: 20px; height: 20px; flex-shrink: 0; }

.error-message {
  margin: 14px 0 0;
  color: #b91c1c;
  font-weight: 600;
  font-size: 0.88rem;
}

.footnote {
  margin: 18px 0 0;
  font-size: 0.84rem;
  color: var(--ink-faint);
  line-height: 1.5;
}

@keyframes rise {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes drift {
  0%, 100% { transform: translate(0, 0); }
  50%       { transform: translate(16px, -12px); }
}

@media (max-width: 600px) {
  .login-card { padding: 28px 22px; border-radius: 20px; }
  .subtitle { margin-bottom: 22px; font-size: 0.9rem; }
}
</style>
