<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";

type MeResponse = {
  id?: number | string;
  email?: string;
  username?: string;
  login?: string;
  name?: string;
  [key: string]: unknown;
};

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const router = useRouter();
const me = ref<MeResponse | null>(null);
const meStatus = ref("Not checked");
const meError = ref("");
const meRaw = ref("");

function loginWithGithub() {
  window.location.href = `${apiBaseUrl}/connect/github`;
}

function installGithubApp() {
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
        <h2 class="logo">AI PMR Reviewer</h2>
      </div>

      <nav class="nav">
        <RouterLink to="/" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
          </svg>
          <span class="nav-label">Dashboard</span>
        </RouterLink>
        <RouterLink to="/repos" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
          </svg>
          <span class="nav-label">Repositories</span>
        </RouterLink>
      </nav>

      <section class="auth-panel" aria-label="Authentication">
        <button v-if="me" class="install-app-button" @click="installGithubApp">
          <span class="install-icon" aria-hidden="true">⬢</span>
          <span>Install GitHub App</span>
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
  --bg: #f0f4f8;
  --surface: #ffffff;
  --surface-soft: #f8fbff;
  --ink-strong: #0f172a;
  --ink-body: #334155;
  --ink-soft: #64748b;
  --line: #dbe5f0;
  --line-strong: #c5d4e6;
  --accent: #0ea5e9;
  --accent-soft: #e0f2fe;
  --shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.25);
  --shadow-hover: 0 25px 60px -12px rgba(15, 23, 42, 0.35);
  display: grid;
  grid-template-columns: 270px minmax(0, 1fr);
  min-height: 100vh;
  background:
    radial-gradient(ellipse at top left, #e0f2fe 0%, transparent 50%),
    radial-gradient(ellipse at bottom right, #f0f9ff 0%, transparent 50%),
    linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  color: var(--ink-body);
  font-family: "Inter", "Segoe UI", "Helvetica Neue", sans-serif;
}

.sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 16px 12px;
  border-right: 1px solid var(--line);
  background:
    linear-gradient(180deg, #ffffff 0%, #f1f5f9 50%, #e2e8f0 100%);
  box-shadow: var(--shadow);
}

.auth-panel {
  margin-top: 200%;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid var(--line);
  background: rgba(255, 255, 255, 0.8);
}

.install-app-button {
  border: 1px solid #bae6fd;
  border-radius: 12px;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #ecfeff 0%, #e0f2fe 100%);
  color: #075985;
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

.install-icon {
  font-size: 0.95rem;
  line-height: 1;
}

.logout-button {
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: 10px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
  color: #9f1239;
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
  padding: 12px 12px 10px;
  border-radius: 16px;
  background: var(--surface);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.brand-mark {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: var(--accent);
  background:
    linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  border: 2px solid var(--accent-soft);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2);
}

.logo {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--ink-strong);
  background: linear-gradient(135deg, var(--ink-strong) 0%, var(--accent) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 12px;
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
  color: var(--ink-body);
  font-weight: 600;
  font-size: 0.95rem;
  transition:
    all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.nav-link::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s;
}

.nav-link:hover::before {
  left: 100%;
}

.nav-link:hover {
  background: var(--accent-soft);
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-2px);
  box-shadow: var(--shadow-hover);
}

.nav-link.router-link-active {
  color: var(--accent);
  background: linear-gradient(135deg, var(--accent-soft) 0%, #bae6fd 100%);
  border-color: var(--accent);
  box-shadow: inset 0 0 0 1px rgba(14, 165, 233, 0.2), var(--shadow);
}

.nav-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.content {
  padding: 32px;
  min-width: 0;
}

.content-shell {
  min-height: calc(100vh - 64px);
  border-radius: 24px;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid var(--line);
  box-shadow: var(--shadow);
  padding: 24px;
  position: relative;
}

.content-shell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent) 0%, #06b6d4 50%, #0ea5e9 100%);
  border-radius: 24px 24px 0 0;
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
    border-bottom: 1px solid var(--line);
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
    padding: 20px;
  }

  .content-shell {
    min-height: calc(100vh - 120px);
    border-radius: 20px;
    padding: 16px;
  }
}
</style>
