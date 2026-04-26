<template>
  <div class="admin-layout" data-theme="dark">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-icon">⚙</span>
        <div>
          <p class="brand-name">autoPMR</p>
          <p class="brand-badge">Admin Panel</p>
        </div>
      </div>

      <nav class="nav">
        <p class="nav-section">Navigation</p>
        <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to" class="nav-link" :class="{ active: isActive(link.name) }">
          <span class="nav-icon" v-html="link.icon"></span>
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <div class="spacer"></div>

      <div class="sidebar-footer">
        <p class="admin-badge">Logged in as admin</p>
        <button class="logout-btn" @click="logout">Sign out</button>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1 class="page-title">{{ currentPageTitle }}</h1>
        <span class="admin-label">Admin Panel</span>
      </header>
      <div class="content">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { clearAdminToken } from "@/api/admin";

const route = useRoute();
const router = useRouter();

const navLinks = [
  {
    to: "/admin/dashboard",
    name: "admin-dashboard",
    label: "Dashboard",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>',
  },
  {
    to: "/admin/users",
    name: "admin-users",
    label: "Users",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>',
  },
  {
    to: "/admin/repos",
    name: "admin-repos",
    label: "Repositories",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.06 15.94 0 13.35 0c-1.46 0-2.67.6-3.55 1.55L9 3 8.2 1.55C7.32.6 6.11 0 4.65 0 2.06 0 0 2.06 0 4.65c0 .47.11.91.18 1.35H0v2h1l1 13h18l1-13h1V6h-2z"/></svg>',
  },
  {
    to: "/admin/pull-requests",
    name: "admin-pull-requests",
    label: "Pull Requests",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14zm-1-6H8v-2h8v2zm0-4H8V7h8v2z"/></svg>',
  },
  {
    to: "/admin/notifications",
    name: "admin-notifications",
    label: "Notifications",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>',
  },
  {
    to: "/admin/logs",
    name: "admin-logs",
    label: "Logs",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>',
  },
  {
    to: "/admin/settings",
    name: "admin-settings",
    label: "System Settings",
    icon: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a6.97 6.97 0 0 0-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.47.47 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.07.63-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>',
  },
];

const pageTitles: Record<string, string> = {
  "admin-dashboard": "Dashboard",
  "admin-users": "Users",
  "admin-user-detail": "User Detail",
  "admin-repos": "Repositories",
  "admin-pull-requests": "Pull Requests",
  "admin-notifications": "Notifications",
  "admin-logs": "Logs",
  "admin-settings": "System Settings",
};

const currentPageTitle = computed(() => pageTitles[route.name as string] ?? "Admin");

function isActive(name: string): boolean {
  const routeName = route.name as string;
  if (name === "admin-users") return routeName === "admin-users" || routeName === "admin-user-detail";
  return routeName === name;
}

function logout() {
  clearAdminToken();
  router.push({ name: "admin-login" });
}
</script>

<style scoped>
.admin-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  min-height: 100vh;
  background: #0d0d14;
  font-family: "Manrope", sans-serif;
}

/* Sidebar */
.sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 16px 12px;
  background: #13131f;
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  overflow-y: auto;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.07);
  margin-bottom: 20px;
}

.brand-icon {
  font-size: 1.4rem;
  width: 38px;
  height: 38px;
  background: #1e1e3a;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-name {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #e2e8f0;
  line-height: 1;
}

.brand-badge {
  margin: 3px 0 0;
  font-size: 0.62rem;
  font-weight: 700;
  color: #6366f1;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-section {
  margin: 0 0 8px 10px;
  font-size: 0.63rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #3d4a6b;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  text-decoration: none;
  color: #6b7a99;
  font-size: 0.85rem;
  font-weight: 600;
  transition: background 0.12s, color 0.12s;
  border: 1px solid transparent;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #c4cde8;
}

.nav-link.active {
  background: rgba(99, 102, 241, 0.15);
  color: #a5b4fc;
  border-color: rgba(99, 102, 241, 0.25);
}

.nav-icon {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 7px;
  background: rgba(255, 255, 255, 0.04);
  flex-shrink: 0;
}

.nav-link.active .nav-icon {
  background: rgba(99, 102, 241, 0.2);
}

.spacer { flex: 1; }

.sidebar-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.admin-badge {
  margin: 0;
  font-size: 0.75rem;
  color: #3d4a6b;
  font-weight: 600;
  padding: 0 4px;
}

.logout-btn {
  width: 100%;
  padding: 9px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 9px;
  color: #f87171;
  font-size: 0.82rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.12s;
}

.logout-btn:hover {
  background: rgba(239, 68, 68, 0.15);
}

/* Main */
.main {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 28px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  background: #13131f;
  position: sticky;
  top: 0;
  z-index: 10;
}

.page-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 800;
  color: #e2e8f0;
}

.admin-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #6366f1;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  background: rgba(99, 102, 241, 0.1);
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid rgba(99, 102, 241, 0.2);
}

.content {
  flex: 1;
  padding: 28px;
  background: #0d0d14;
}
</style>
