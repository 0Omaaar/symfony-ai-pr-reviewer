<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

type MeResponse = {
  authenticated?: boolean;
  githubAppInstalled?: boolean;
  id?: number | string;
  email?: string;
  username?: string;
  login?: string;
  name?: string;
  [key: string]: unknown;
};

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const router = useRouter();
const route = useRoute();
const me = ref<MeResponse | null>(null);
const meStatus = ref("Not checked");
const meError = ref("");
const meRaw = ref("");

function loginWithGithub() {
  window.location.href = `${apiBaseUrl}/connect/github`;
}

function installGithubApp() {
  if (isInstallDisabled.value) return;
  window.location.href = `${apiBaseUrl}/connect/github/app/install`;
}

async function fetchCurrentUser() {
  meError.value = "";
  meStatus.value = "Loading /api/me...";

  try {
    const response = await fetch(`${apiBaseUrl}/api/me`, {
      credentials: "include",
    });

    if (!response.ok) {
      const text = await response.text();
      me.value = null;
      meRaw.value = text;
      meStatus.value = `Unauthenticated (${response.status})`;
      return;
    }

    const data = (await response.json()) as MeResponse;
    me.value = data;
    meRaw.value = JSON.stringify(data, null, 2);
    meStatus.value = "Authenticated";
  } catch (error) {
    me.value = null;
    meStatus.value = "Request failed";
    meError.value = error instanceof Error ? error.message : "Unknown error";
  }
}

async function logout() {
  meError.value = "";

  try {
    const response = await fetch(`${apiBaseUrl}/logout`, {
      method: "POST",
      credentials: "include",
      redirect: "manual",
    });

    const isRedirect = response.type === "opaqueredirect" || (response.status >= 300 && response.status < 400);
    if (!response.ok && !isRedirect) {
      throw new Error(`Logout failed with status ${response.status}`);
    }

    me.value = null;
    meRaw.value = "";
    meStatus.value = "Logged out";
    await router.push({ name: "login" });
  } catch (error) {
    meError.value = error instanceof Error ? error.message : "Logout failed";
    console.error("Logout failed:", error);
  }
}

onMounted(() => {
  void fetchCurrentUser();
});

function isDashboardRoute() {
  return route.name === "dashboard";
}

function isRepositoriesRoute() {
  return route.name === "repos" || route.name === "repo-details" || route.name === "pr-details";
}

const isInstallDisabled = computed(() => {
  return Boolean(me.value?.authenticated) && Boolean(me.value?.githubAppInstalled);
});
</script>

<template>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C10.9 2 10 2.9 10 4V5C10 5.55 9.55 6 9 6S8 5.55 8 5V4C8 1.79 9.79 0 12 0S16 1.79 16 4V5C16 5.55 15.55 6 15 6S14 5.55 14 5V4C14 2.9 13.1 2 12 2ZM12 22C10.9 22 10 21.1 10 20V19C10 18.45 9.55 18 9 18S8 18.45 8 19V20C8 22.21 9.79 24 12 24S16 22.21 16 20V19C16 18.45 15.55 18 15 18S14 18.45 14 19V20C14 21.1 13.1 22 12 22ZM2 12C2 10.9 2.9 10 4 10H5C5.55 10 6 9.55 6 9S5.55 8 5 8H4C1.79 8 0 9.79 0 12S1.79 16 4 16H5C5.55 16 6 15.55 6 15S5.55 14 5 14H4C2.9 14 2 13.1 2 12ZM22 12C22 10.9 21.1 10 20 10H19C18.45 10 18 9.55 18 9S18.45 8 19 8H20C22.21 8 24 9.79 24 12S22.21 16 20 16H19C18.45 16 18 15.55 18 15S18.45 14 19 14H20C21.1 14 22 13.1 22 12Z"/>
          </svg>
        </div>
        <div class="brand-copy">
          <h2 class="logo">AI PMR Reviewer</h2>
          <p class="brand-subtitle">Code review workspace</p>
        </div>
      </div>

      <nav class="nav">
        <p class="nav-title">Navigation</p>
        <RouterLink to="/" class="nav-link" :class="{ 'router-link-active': isDashboardRoute() }">
          <span class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
            </svg>
          </span>
          <span class="nav-label">Dashboard</span>
        </RouterLink>
        <RouterLink to="/repos" class="nav-link" :class="{ 'router-link-active': isRepositoriesRoute() }">
          <span class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
          </span>
          <span class="nav-label">Repositories</span>
        </RouterLink>
      </nav>

      <section class="auth-panel" aria-label="Authentication">
        <p class="auth-caption">{{ me ? "Connected with GitHub" : "Session unavailable" }}</p>
        <button
          v-if="me"
          class="install-app-button"
          :class="{ 'is-disabled': isInstallDisabled }"
          :disabled="isInstallDisabled"
          @click="installGithubApp"
        >
          <span class="install-icon" aria-hidden="true">⬢</span>
          <span>{{ isInstallDisabled ? "GitHub App Installed" : "Install GitHub App" }}</span>
        </button>
        <button class="logout-button" @click="logout">
          <span class="logout-icon" aria-hidden="true">↩</span>
          <span>Log out</span>
        </button>
      </section>
    </aside>

    <main class="content">
      <div class="content-shell">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<style scoped>
.layout {
  --surface: #ffffff;
  --surface-soft: #f3f6fb;
  --ink-strong: #111827;
  --ink-body: #344256;
  --ink-soft: #67778f;
  --line: #d3deed;
  --line-strong: #b9c8dd;
  --accent: #0d7ea4;
  --accent-soft: #d9edf6;
  --brand-deep: #0f1f3d;
  --shadow: 0 24px 48px -24px rgba(15, 31, 61, 0.45);
  --shadow-hover: 0 24px 54px -18px rgba(17, 24, 39, 0.35);
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  min-height: 100vh;
  background:
    radial-gradient(800px 380px at -14% -10%, #dbeafe 0%, transparent 65%),
    radial-gradient(620px 300px at 110% 0%, #cffafe 0%, transparent 60%),
    linear-gradient(145deg, #f7fafc 0%, #edf3fb 100%);
  color: var(--ink-body);
  font-family: "Manrope", "Nunito Sans", "Avenir Next", "Segoe UI", sans-serif;
}

.sidebar {
  position: sticky;
  top: 24px;
  height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  gap: 26px;
  padding: 18px 14px;
  border-right: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 24px;
  background:
    linear-gradient(195deg, #13294e 0%, #163468 45%, #0f2a57 100%);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.sidebar::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    radial-gradient(420px 180px at -20% -15%, rgba(133, 203, 255, 0.24), transparent 70%),
    radial-gradient(300px 180px at 120% 20%, rgba(103, 177, 255, 0.18), transparent 72%);
  pointer-events: none;
}

.auth-panel {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(4px);
}

.auth-caption {
  margin: 0 0 2px;
  color: #dce8fb;
  font-size: 0.79rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.install-app-button {
  border: 1px solid #89d8f5;
  border-radius: 12px;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #ecfeff 0%, #e0f2fe 100%);
  color: #0d4667;
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.install-app-button:hover {
  transform: translateY(-1px);
  border-color: #7dd3fc;
  box-shadow: 0 10px 22px -12px rgba(3, 105, 161, 0.45);
}

.install-app-button:active {
  transform: translateY(0);
}

.install-app-button:focus-visible {
  outline: 3px solid #bae6fd;
  outline-offset: 2px;
}

.install-app-button.is-disabled,
.install-app-button:disabled {
  cursor: not-allowed;
  opacity: 0.72;
  border-color: #c7d9e5;
  background: linear-gradient(135deg, #eef5f9 0%, #e6eef4 100%);
  color: #567089;
  box-shadow: none;
  transform: none;
}

.install-icon {
  font-size: 0.95rem;
  line-height: 1;
}

.logout-button {
  border: 1px solid #f9c5d1;
  border-radius: 12px;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
  color: #8b1f3f;
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.logout-button:hover {
  transform: translateY(-1px);
  border-color: #fda4af;
  box-shadow: 0 10px 22px -12px rgba(159, 18, 57, 0.5);
}

.logout-button:active {
  transform: translateY(0);
}

.logout-button:focus-visible {
  outline: 3px solid #fecdd3;
  outline-offset: 2px;
}

.logout-icon {
  font-size: 0.9rem;
  line-height: 1;
}

.auth-title {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.auth-status {
  margin: 0;
  color: var(--ink-strong);
  font-weight: 600;
  font-size: 0.85rem;
}

.auth-user {
  margin: 0;
  color: var(--ink-body);
  font-size: 0.85rem;
}

.auth-button {
  border: 1px solid var(--line-strong);
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.auth-button.login {
  background: var(--ink-strong);
  color: #fff;
  border-color: var(--ink-strong);
}

.auth-button.login:hover {
  background: #0b1220;
}

.auth-button.refresh {
  background: #fff;
  color: var(--ink-body);
}

.auth-button.refresh:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.auth-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.8rem;
}

.auth-raw {
  font-size: 0.8rem;
}

.auth-raw summary {
  cursor: pointer;
  color: var(--ink-soft);
}

.auth-raw pre {
  margin: 6px 0 0;
  padding: 8px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #0f172a;
  overflow: auto;
  max-height: 180px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
}

.brand-copy {
  min-width: 0;
}

.brand-mark {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: #77d8ff;
  background:
    linear-gradient(140deg, #0b5f8a 0%, #1f86c0 100%);
  border: 2px solid rgba(173, 224, 249, 0.7);
  box-shadow: 0 8px 22px -10px rgba(42, 157, 220, 0.9);
}

.logo {
  margin: 0;
  font-size: 1.16rem;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: #f8fbff;
}

.brand-subtitle {
  margin: 4px 0 0;
  color: #b7cae9;
  font-size: 0.78rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.nav-title {
  margin: 0 6px 4px;
  color: #b5c7e6;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 52px;
  padding: 0 16px;
  border-radius: 16px;
  border: 1px solid transparent;
  text-decoration: none;
  color: #d4e3fb;
  font-weight: 700;
  font-size: 0.95rem;
  transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
  position: relative;
  overflow: hidden;
  isolation: isolate;
}

.nav-link::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
  transition: left 0.5s;
}

.nav-link:hover::before {
  left: 100%;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.11);
  border-color: rgba(171, 203, 245, 0.55);
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 14px 30px -16px rgba(8, 16, 35, 0.8);
}

.nav-link.router-link-active {
  color: #061427;
  background: linear-gradient(135deg, #d7eeff 0%, #bce0f6 100%);
  border-color: rgba(219, 239, 255, 0.95);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35), 0 16px 30px -22px rgba(10, 20, 39, 0.95);
}

.nav-link.router-link-active::after {
  content: "";
  position: absolute;
  left: 8px;
  top: 50%;
  width: 5px;
  height: 22px;
  border-radius: 999px;
  transform: translateY(-50%);
  background: linear-gradient(180deg, #0c5d8a 0%, #0b7fb4 100%);
}

.nav-icon-wrap {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.nav-link:hover .nav-icon-wrap {
  background: rgba(255, 255, 255, 0.18);
}

.nav-link.router-link-active .nav-icon-wrap {
  background: rgba(255, 255, 255, 0.66);
  border-color: rgba(13, 126, 164, 0.18);
}

.nav-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.content {
  padding: 24px 28px 28px;
  min-width: 0;
}

.content-shell {
  height: calc(100vh - 48px);
  border-radius: 26px;
  background: linear-gradient(152deg, #ffffff 0%, #f8fbff 48%, #f1f7ff 100%);
  border: 1px solid #ccdbef;
  box-shadow: var(--shadow);
  padding: 22px;
  position: relative;
  overflow: auto;
}

.content-shell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #56b1d9 0%, #0f8ec3 50%, #12618a 100%);
  border-radius: 26px 26px 0 0;
}

@media (max-width: 1024px) {
  .layout {
    grid-template-columns: 280px minmax(0, 1fr);
  }
}

@media (max-width: 768px) {
  .layout {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr;
  }

  .sidebar {
    position: static;
    height: auto;
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 0;
    padding: 20px 16px 16px;
    gap: 20px;
    box-shadow: none;
  }

  .auth-panel {
    margin-top: 0;
  }

  .brand {
    padding: 8px 8px 6px;
  }

  .nav {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
  }

  .nav-link {
    min-height: 48px;
    padding: 0 12px;
    font-size: 0.9rem;
  }

  .nav-icon {
    width: 18px;
    height: 18px;
  }

  .content {
    padding: 18px;
  }

  .content-shell {
    height: auto;
    min-height: calc(100vh - 36px);
    border-radius: 20px;
    padding: 16px;
    overflow: visible;
  }
}
</style>
