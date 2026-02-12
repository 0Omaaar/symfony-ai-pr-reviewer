import { createRouter, createWebHistory } from "vue-router";
import DashboardView from "@/views/DashboardView.vue";
import RepositoriesView from "@/views/RepositoriesView.vue";
import RepositoryDetailsView from "@/views/RepositoryDetailsView.vue";

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/", name: "dashboard", component: DashboardView},
        { path: "/repos", name: "repos", component: RepositoriesView},
        { path: "/repos/:id", name: "repo-details", component: RepositoryDetailsView}
    ]
})