import { createRouter, createWebHistory } from "vue-router";
import LandingView from "@/views/LandingView.vue";
import DashboardView from "@/views/DashboardView.vue";
import RepositoriesView from "@/views/RepositoriesView.vue";
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

    // If already-authenticated admin hits /admin/login → redirect to dashboard
    if (to.meta.adminLoginPage) {
        if (isAdminAuthenticated()) {
            return { name: "admin-dashboard" };
        }
        return true;
    }

    // ── User app guard ────────────────────────────────────────────────────
    // Fast-path for explicit logout redirect so user lands on login immediately.
    if (to.name === "login" && to.query.logged_out === "1") {
        return true;
    }

    const cachedAuth = getCachedAuth();

    // Do not block UI when navigating inside authenticated pages.
    if (from.meta.requiresAuth && to.meta.requiresAuth) {
        void fetchMe().catch(() => {
            setCachedAuth(false);
        });
        return true;
    }

    if (cachedAuth === true) {
        if (to.meta.guestOnly) return { name: "dashboard" };
        return true;
    }

    if (cachedAuth === false) {
        // Non-auth routes (landing, login) are always allowed without a network call.
        if (!to.meta.requiresAuth) return true;
        // For protected routes, re-validate in case auth state changed (e.g. just logged in).
        try {
            const me = await fetchMe();
            if (!me?.authenticated) return { name: "login" };
            return true;
        } catch {
            return { name: "login" };
        }
    }

    try {
        const me = await fetchMe();
        const isAuthenticated = !!me?.authenticated;

        if (to.meta.requiresAuth && !isAuthenticated) return { name: "login" };
        if (to.meta.guestOnly && isAuthenticated) return { name: "dashboard" };

        return true;
    } catch {
        if (to.meta.requiresAuth) return { name: "login" };
        return true;
    }
});
