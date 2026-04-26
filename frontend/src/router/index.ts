import { createRouter, createWebHistory } from "vue-router";
import LandingView from "@/views/LandingView.vue";
import DashboardView from "@/views/DashboardView.vue";
import TeamDashboardView from "@/views/TeamDashboardView.vue";
import RepositoriesView from "@/views/RepositoriesView.vue";
import WorkspacesView from "@/views/WorkspacesView.vue";
import RepositoryDetailsView from "@/views/RepositoryDetailsView.vue";
import PrDetailsView from "@/views/PrDetailsView.vue";
import LoginView from "@/views/auth/LoginView.vue";
import SettingsView from "@/views/SettingsView.vue";
import UnsubscribeView from "@/views/UnsubscribeView.vue";
import AdminLoginView from "@/views/admin/AdminLoginView.vue";
import AdminLayout from "@/views/admin/AdminLayout.vue";
import AdminDashboardView from "@/views/admin/AdminDashboardView.vue";
import AdminUsersView from "@/views/admin/AdminUsersView.vue";
import AdminUserDetailView from "@/views/admin/AdminUserDetailView.vue";
import AdminReposView from "@/views/admin/AdminReposView.vue";
import AdminPullRequestsView from "@/views/admin/AdminPullRequestsView.vue";
import AdminNotificationsView from "@/views/admin/AdminNotificationsView.vue";
import AdminLogsView from "@/views/admin/AdminLogsView.vue";
import AdminSettingsView from "@/views/admin/AdminSettingsView.vue";
import { fetchMe, getCachedAuth, setCachedAuth } from "@/api/auth";
import { isAdminAuthenticated } from "@/api/admin";

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        // ── User app ──────────────────────────────────────────────────────
        { path: "/", name: "landing", component: LandingView, meta: { guestOnly: true } },
        { path: "/login", name: "login", component: LoginView, meta: { guestOnly: true } },
        { path: "/dashboard", name: "dashboard", component: DashboardView, meta: { requiresAuth: true } },
        { path: "/dashboard/team", name: "team-dashboard", component: TeamDashboardView, meta: { requiresAuth: true } },
        { path: "/workspaces", name: "workspaces", component: WorkspacesView, meta: { requiresAuth: true } },
        { path: "/repos", name: "repos", component: RepositoriesView, meta: { requiresAuth: true } },
        { path: "/repos/:id", name: "repo-details", component: RepositoryDetailsView, meta: { requiresAuth: true } },
        { path: "/pr/:id", name: "pr-details", component: PrDetailsView, meta: { requiresAuth: true } },
        { path: "/settings", name: "settings", component: SettingsView, meta: { requiresAuth: true } },
        { path: "/unsubscribe", name: "unsubscribe", component: UnsubscribeView },

        // ── Admin app ─────────────────────────────────────────────────────
        {
            path: "/admin/login",
            name: "admin-login",
            component: AdminLoginView,
            meta: { adminLoginPage: true },
        },
        {
            path: "/admin",
            component: AdminLayout,
            meta: { requiresAdmin: true },
            children: [
                { path: "", redirect: { name: "admin-dashboard" } },
                { path: "dashboard", name: "admin-dashboard", component: AdminDashboardView },
                { path: "users", name: "admin-users", component: AdminUsersView },
                { path: "users/:id", name: "admin-user-detail", component: AdminUserDetailView },
                { path: "repos", name: "admin-repos", component: AdminReposView },
                { path: "pull-requests", name: "admin-pull-requests", component: AdminPullRequestsView },
                { path: "notifications", name: "admin-notifications", component: AdminNotificationsView },
                { path: "logs", name: "admin-logs", component: AdminLogsView },
                { path: "settings", name: "admin-settings", component: AdminSettingsView },
            ],
        },
    ]
});

router.beforeEach(async (to, from) => {
    // ── Admin routes guard ────────────────────────────────────────────────
    if (to.meta.requiresAdmin) {
        if (!isAdminAuthenticated()) {
            return { name: "admin-login" };
        }
        return true;
    }

    if (to.meta.adminLoginPage) {
        if (isAdminAuthenticated()) {
            return { name: "admin-dashboard" };
        }
        return true;
    }

    // ── User app guard ────────────────────────────────────────────────────
    if (to.name === "login" && to.query.logged_out === "1") {
        setCachedAuth(false);
        return true;
    }

    // For routes that don't require auth and aren't guest-only, let them through
    if (!to.meta.requiresAuth && !to.meta.guestOnly) {
        return true;
    }

    // When navigating between authenticated pages, allow navigation immediately
    // but verify session in the background
    if (from.meta.requiresAuth && to.meta.requiresAuth) {
        void fetchMe().catch(() => {
            setCachedAuth(false);
        });
        return true;
    }

    // Always verify with the server — never redirect based on local cache alone
    try {
        const me = await fetchMe();
        const isAuthenticated = !!me?.authenticated;

        if (to.meta.requiresAuth && !isAuthenticated) {
            return { name: "login" };
        }
        if (to.meta.guestOnly && isAuthenticated) {
            return { name: "dashboard" };
        }

        return true;
    } catch {
        // Network error — use cached state as fallback
        const cachedAuth = getCachedAuth();
        if (to.meta.requiresAuth && cachedAuth !== true) {
            return { name: "login" };
        }
        return true;
    }
});
