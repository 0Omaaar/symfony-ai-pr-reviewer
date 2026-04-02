<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { clearCachedAuth, setCachedAuth } from "@/api/auth";

type MeResponse = {
  authenticated?: boolean;
  githubAppInstalled?: boolean;
  installations?: Array<{
    installation_id?: number;
    account_login?: string | null;
    account_type?: string | null;
  }>;
  user?: {
    username?: string;
    email?: string;
    emailNotificationsEnabled?: boolean;
    [key: string]: unknown;
  };
  [key: string]: unknown;
};

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const router = useRouter();
const route = useRoute();
const me = ref<MeResponse | null>(null);
const isLoggingOut = ref(false);
const meError = ref("");

const username = computed(() => (me.value?.user?.username as string | undefined) ?? "");
const avatarLetter = computed(() => username.value ? username.value[0].toUpperCase() : "?");

function loginWithGithub() {
  window.location.href = `${apiBaseUrl}/connect/github`;
}

function installGithubApp() {
  if (isInstallDisabled.value) return;
  window.location.href = `${apiBaseUrl}/connect/github/app/install`;
}

async function fetchCurrentUser() {
  try {
    const response = await fetch(`${apiBaseUrl}/api/me`, { credentials: "include" });
    if (!response.ok) { me.value = null; setCachedAuth(false); return; }
    const data = (await response.json()) as MeResponse;
    me.value = data;
    setCachedAuth(Boolean(data.authenticated));
  } catch {
    me.value = null;
    setCachedAuth(false);
  }
}

async function logout() {
  if (isLogoutDisabled.value) return;
  isLoggingOut.value = true;
  meError.value = "";
  me.value = null;
  clearCachedAuth();
  await router.push({ name: "login", query: { logged_out: "1" } });
  try {
    await fetch(`${apiBaseUrl}/api/logout`, { method: "POST", credentials: "include", keepalive: true });
    await router.replace({ name: "login" });
  } catch (error) {
    meError.value = error instanceof Error ? error.message : "Logout failed";
  } finally {
    isLoggingOut.value = false;
  }
}

onMounted(() => { void fetchCurrentUser(); });

const isInstallDisabled = computed(() => Boolean(me.value?.authenticated) && Boolean(me.value?.githubAppInstalled));
const isLogoutDisabled = computed(() => !Boolean(me.value?.authenticated) || isLoggingOut.value);

const isAuthenticated = computed(() => Boolean(me.value?.authenticated));

function isActive(names: string[]) {
  return names.includes(route.name as string);
}
</script>

<template>
  <div class="layout">
    <aside class="sidebar">
      <!-- Brand -->
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
            <path d="M12 2C10.9 2 10 2.9 10 4V5C10 5.55 9.55 6 9 6S8 5.55 8 5V4C8 1.79 9.79 0 12 0S16 1.79 16 4V5C16 5.55 15.55 6 15 6S14 5.55 14 5V4C14 2.9 13.1 2 12 2ZM12 22C10.9 22 10 21.1 10 20V19C10 18.45 9.55 18 9 18S8 18.45 8 19V20C8 22.21 9.79 24 12 24S16 22.21 16 20V19C16 18.45 15.55 18 15 18S14 18.45 14 19V20C14 21.1 13.1 22 12 22ZM2 12C2 10.9 2.9 10 4 10H5C5.55 10 6 9.55 6 9S5.55 8 5 8H4C1.79 8 0 9.79 0 12S1.79 16 4 16H5C5.55 16 6 15.55 6 15S5.55 14 5 14H4C2.9 14 2 13.1 2 12ZM22 12C22 10.9 21.1 10 20 10H19C18.45 10 18 9.55 18 9S18.45 8 19 8H20C22.21 8 24 9.79 24 12S22.21 16 20 16H19C18.45 16 18 15.55 18 15S18.45 14 19 14H20C21.1 14 22 13.1 22 12Z"/>
          </svg>
        </div>
        <div class="brand-copy">
          <span class="brand-name">autoPMR</span>
          <span class="brand-tagline">PR Monitoring</span>
        </div>
      </div>

      <!-- Nav -->
      <nav class="nav" aria-label="Main navigation">
        <p class="nav-group-label">Menu</p>

        <RouterLink to="/" class="nav-link" :class="{ active: isActive(['dashboard']) }">
          <span class="nav-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
          </span>
          <span class="nav-label">Dashboard</span>
        </RouterLink>

        <RouterLink to="/repos" class="nav-link" :class="{ active: isActive(['repos', 'repo-details', 'pr-details']) }">
          <span class="nav-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.06 15.94 0 13.35 0c-1.46 0-2.67.6-3.55 1.55L9 3 8.2 1.55C7.32.6 6.11 0 4.65 0 2.06 0 0 2.06 0 4.65c0 .47.11.91.18 1.35H0v2h1l1 13h18l1-13h1V6h-2zm-6.65-4c1.06 0 1.96.8 2.1 1.82L13.53 6H10.5l-1.94-2.16C9.08 2.82 9.97 2 11.04 2h2.31z"/></svg>
          </span>
          <span class="nav-label">Repositories</span>
        </RouterLink>

        <RouterLink to="/settings" class="nav-link" :class="{ active: isActive(['settings']) }">
          <span class="nav-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a6.97 6.97 0 0 0-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.47.47 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.07.63-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
          </span>
          <span class="nav-label">Settings</span>
        </RouterLink>
      </nav>

      <!-- Spacer -->
      <div class="spacer"></div>

      <!-- GitHub App install prompt -->
      <button
        v-if="isAuthenticated && !isInstallDisabled"
        class="install-prompt"
        @click="installGithubApp"
      >
        <span class="install-prompt-icon">⬢</span>
        <span class="install-prompt-text">Install GitHub App</span>
        <span class="install-prompt-arrow">→</span>
      </button>

      <!-- User identity + actions -->
      <div class="user-panel">
        <template v-if="isAuthenticated && username">
          <div class="user-identity">
            <div class="user-avatar" aria-hidden="true">{{ avatarLetter }}</div>
            <div class="user-info">
              <span class="user-name">{{ username }}</span>
              <span class="user-role">Connected via GitHub</span>
            </div>
          </div>
          <div class="user-actions">
            <button
              class="action-btn logout-btn"
              :disabled="isLogoutDisabled"
              @click="logout"
            >
              <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
              {{ isLoggingOut ? "Signing out…" : "Sign out" }}
            </button>
          </div>
        </template>
        <template v-else-if="!isAuthenticated && me !== null">
          <button class="action-btn login-btn" @click="loginWithGithub">
            Sign in with GitHub
          </button>
        </template>
      </div>
    </aside>

    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: 256px minmax(0, 1fr);
  min-height: 100vh;
  background: #eef3fa;
  font-family: var(--font-sans, "Manrope", sans-serif);
}

/* ─── Sidebar ───────────────────────────────────────────────── */
.sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 16px 12px;
  background: linear-gradient(175deg, #0f1e3b 0%, #142848 55%, #0e2248 100%);
  border-right: 1px solid rgba(255,255,255,0.07);
  overflow-y: auto;
  overflow-x: hidden;
}

/* ─── Brand ─────────────────────────────────────────────────── */
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 22px;
  flex-shrink: 0;
}

.brand-mark {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #1469a0, #0d90c5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(13, 126, 164, 0.5);
}

.brand-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.brand-name {
  font-size: 1rem;
  font-weight: 800;
  color: #f0f6ff;
  letter-spacing: -0.01em;
  line-height: 1;
}

.brand-tagline {
  font-size: 0.68rem;
  font-weight: 600;
  color: #7da4c9;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

/* ─── Nav ───────────────────────────────────────────────────── */
.nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-group-label {
  margin: 0 0 6px 10px;
  font-size: 0.67rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #4d6a8f;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  border-radius: 12px;
  text-decoration: none;
  color: #8fb3d4;
  font-weight: 600;
  font-size: 0.88rem;
  transition: background 0.15s ease, color 0.15s ease;
  border: 1px solid transparent;
}

.nav-link:hover {
  background: rgba(255,255,255,0.08);
  color: #d8eaf8;
  border-color: rgba(255,255,255,0.1);
}

.nav-link.active {
  background: rgba(13, 144, 197, 0.18);
  color: #7dccf0;
  border-color: rgba(13, 144, 197, 0.3);
}

.nav-icon {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.09);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.nav-icon svg {
  width: 15px;
  height: 15px;
}

.nav-link:hover .nav-icon {
  background: rgba(255,255,255,0.12);
}

.nav-link.active .nav-icon {
  background: rgba(13, 144, 197, 0.3);
  border-color: rgba(13, 144, 197, 0.4);
}

.nav-label {
  line-height: 1;
}

/* ─── Spacer ─────────────────────────────────────────────────── */
.spacer { flex: 1; }

/* ─── Install prompt ─────────────────────────────────────────── */
.install-prompt {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px dashed rgba(125, 204, 240, 0.4);
  background: rgba(13, 144, 197, 0.1);
  color: #7dccf0;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  margin-bottom: 10px;
  transition: background 0.15s ease, border-color 0.15s ease;
  width: 100%;
  text-align: left;
}

.install-prompt:hover {
  background: rgba(13, 144, 197, 0.18);
  border-color: rgba(125, 204, 240, 0.6);
}

.install-prompt-icon { font-size: 1rem; }
.install-prompt-text { flex: 1; }
.install-prompt-arrow { opacity: 0.6; }

/* ─── User panel ─────────────────────────────────────────────── */
.user-panel {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex-shrink: 0;
}

.user-identity {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 12px;
  background: rgba(255,255,255,0.05);
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #1469a0, #0a8cb5);
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.user-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #d8eaf8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role {
  font-size: 0.68rem;
  color: #4d6a8f;
  font-weight: 600;
}

.user-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 9px 12px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
  border: 1px solid transparent;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

.logout-btn {
  background: rgba(255,255,255,0.06);
  border-color: rgba(255,255,255,0.1);
  color: #8fb3d4;
}

.logout-btn:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.3);
  color: #fca5a5;
}

.login-btn {
  background: linear-gradient(135deg, #1469a0, #0d90c5);
  color: #ffffff;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(13, 126, 164, 0.4);
}

.login-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(13, 126, 164, 0.55);
}

/* ─── Main content area ──────────────────────────────────────── */
.content {
  padding: 20px;
  min-height: 100vh;
  min-width: 0;
}

/* ─── Responsive ─────────────────────────────────────────────── */
@media (max-width: 860px) {
  .layout {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr;
  }

  .sidebar {
    position: static;
    height: auto;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    padding: 12px 14px;
    gap: 10px;
    overflow: visible;
  }

  .brand { margin-bottom: 0; flex: 0 0 auto; }
  .nav {
    flex-direction: row;
    gap: 4px;
  }
  .nav-group-label { display: none; }
  .spacer { display: none; }
  .install-prompt { display: none; }
  .user-panel {
    border-top: none;
    padding-top: 0;
    flex-direction: row;
    align-items: center;
    margin-left: auto;
  }
  .user-identity { padding: 6px 8px; }
  .user-role { display: none; }
  .content { padding: 14px; }
}
</style>
