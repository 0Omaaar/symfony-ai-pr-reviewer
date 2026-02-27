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
@import url("https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@600;700&display=swap");

.login-page {
  --ink: #0f172a;
  --ink-muted: #334155;
  --ink-soft: #64748b;
  --panel: rgba(255, 255, 255, 0.9);
  --line: rgba(148, 163, 184, 0.4);
  --accent: #0f766e;
  --accent-strong: #0b5f58;
  --accent-soft: #ccfbf1;
  position: relative;
  min-height: calc(100vh - 120px);
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: 22px;
  background:
    radial-gradient(circle at 15% 15%, #cffafe 0%, rgba(207, 250, 254, 0.1) 45%, transparent 60%),
    radial-gradient(circle at 90% 85%, #ffedd5 0%, rgba(255, 237, 213, 0.06) 48%, transparent 62%),
    linear-gradient(145deg, #f8fafc 0%, #eef2ff 42%, #ecfeff 100%);
  border: 1px solid var(--line);
  font-family: "Manrope", "Segoe UI", sans-serif;
}

.background-glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(6px);
  opacity: 0.55;
  animation: drift 12s ease-in-out infinite;
}

.glow-a {
  width: 280px;
  height: 280px;
  left: -80px;
  top: -60px;
  background: #67e8f9;
}

.glow-b {
  width: 300px;
  height: 300px;
  right: -90px;
  bottom: -70px;
  background: #fdba74;
  animation-delay: -4s;
}

.login-card {
  position: relative;
  z-index: 1;
  width: min(520px, calc(100% - 36px));
  padding: 36px;
  border-radius: 26px;
  background: var(--panel);
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow:
    0 24px 60px -20px rgba(15, 23, 42, 0.3),
    inset 0 1px 0 rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(8px);
  animation: rise 0.45s ease-out both;
}

.eyebrow {
  margin: 0 0 12px;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--accent);
}

.title {
  margin: 0;
  color: var(--ink);
  font-family: "Space Grotesk", "Segoe UI", sans-serif;
  font-size: clamp(2rem, 4vw, 2.6rem);
  line-height: 1.05;
  letter-spacing: -0.03em;
}

.subtitle {
  margin: 14px 0 28px;
  color: var(--ink-muted);
  font-size: 1rem;
  line-height: 1.6;
}

.github-button {
  width: 100%;
  height: 54px;
  border: 1px solid transparent;
  border-radius: 14px;
  padding: 0 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-size: 0.98rem;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
  box-shadow: 0 14px 28px -14px rgba(15, 118, 110, 0.9);
}

.github-button:hover:enabled {
  transform: translateY(-1px);
  box-shadow: 0 18px 32px -16px rgba(15, 118, 110, 0.95);
}

.github-button:focus-visible {
  outline: 3px solid var(--accent-soft);
  outline-offset: 2px;
}

.github-button:disabled {
  cursor: wait;
  filter: saturate(0.75) brightness(0.96);
}

.github-icon {
  width: 20px;
  height: 20px;
}

.error-message {
  margin: 14px 0 0;
  color: #b91c1c;
  font-weight: 600;
}

.footnote {
  margin: 18px 0 0;
  font-size: 0.88rem;
  color: var(--ink-soft);
  line-height: 1.45;
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes drift {
  0%,
  100% {
    transform: translate(0, 0);
  }
  50% {
    transform: translate(14px, -10px);
  }
}

@media (max-width: 720px) {
  .login-page {
    min-height: calc(100vh - 92px);
    border-radius: 16px;
  }

  .login-card {
    padding: 26px 20px;
    border-radius: 20px;
  }

  .subtitle {
    margin-bottom: 22px;
    font-size: 0.95rem;
  }
}
</style>
