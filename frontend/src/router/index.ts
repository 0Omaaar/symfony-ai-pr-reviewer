import { createRouter, createWebHistory } from "vue-router";
import DashboardView from "@/views/DashboardView.vue";
import RepositoriesView from "@/views/RepositoriesView.vue";
import RepositoryDetailsView from "@/views/RepositoryDetailsView.vue";
import PrDetailsView from "@/views/PrDetailsView.vue";
import LoginView from "@/views/auth/LoginView.vue";
import { fetchMe } from "@/api/auth";

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/", name: "dashboard", component: DashboardView, meta: { requiresAuth: true }},
        { path: "/login", name: "login", component: LoginView, meta: { guestOnly: true}},
        { path: "/repos", name: "repos", component: RepositoriesView, meta: { requiresAuth: true }},
        { path: "/repos/:id", name: "repo-details", component: RepositoryDetailsView, meta: { requiresAuth: true }},
        { path: "/pr/:id", name: "pr-details", component: PrDetailsView, meta: { requiresAuth: true }},
    ]
})

router.beforeEach(async (to) => {
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
})