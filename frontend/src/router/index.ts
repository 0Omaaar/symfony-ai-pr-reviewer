import { createRouter, createWebHistory } from "vue-router";
import LandingView from "@/views/LandingView.vue";
import DashboardView from "@/views/DashboardView.vue";
import RepositoriesView from "@/views/RepositoriesView.vue";
import RepositoryDetailsView from "@/views/RepositoryDetailsView.vue";
import PrDetailsView from "@/views/PrDetailsView.vue";
import LoginView from "@/views/auth/LoginView.vue";
import SettingsView from "@/views/SettingsView.vue";
import UnsubscribeView from "@/views/UnsubscribeView.vue";
import { fetchMe, getCachedAuth, setCachedAuth } from "@/api/auth";

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/", name: "landing", component: LandingView, meta: { guestOnly: true } },
        { path: "/login", name: "login", component: LoginView, meta: { guestOnly: true } },
        { path: "/dashboard", name: "dashboard", component: DashboardView, meta: { requiresAuth: true } },
        { path: "/repos", name: "repos", component: RepositoriesView, meta: { requiresAuth: true } },
        { path: "/repos/:id", name: "repo-details", component: RepositoryDetailsView, meta: { requiresAuth: true } },
        { path: "/pr/:id", name: "pr-details", component: PrDetailsView, meta: { requiresAuth: true } },
        { path: "/settings", name: "settings", component: SettingsView, meta: { requiresAuth: true } },
        { path: "/unsubscribe", name: "unsubscribe", component: UnsubscribeView },
    ]
});

router.beforeEach(async (to, from) => {
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
