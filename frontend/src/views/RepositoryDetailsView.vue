<script setup lang="ts">
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { mockRepos } from "../mocks/repos";
import { mockPRs } from "@/mocks/prs";
import { router } from "@/router";

const route = useRoute();
const repoId = Number(route.params.id);

const repo = computed(() => mockRepos.find((r) => r.id === repoId) ?? null);
const prs = computed(() => mockPRs.filter((p) => p.repoId === repoId) ?? []);

function formatDate(iso: string | null) {
    if (!iso) return "Never";
    return new Date(iso).toLocaleString("en-US", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

function providerLabel(provider: "github" | "gitlab") {
    if (provider === "github") return "GitHub";
    return "GitLab";
}

function providerClass(provider: "github" | "gitlab") {
    if (provider === "github") return "is-github";
    return "is-gitlab";
}

function reviewStatus(lastReviewAt: string | null) {
    if (!lastReviewAt) return { label: "Never reviewed", className: "never" };
    return { label: "Reviewed", className: "reviewed" };
}

function prStatusClass(status: "open" | "merged" | "closed") {
    if (status === "open") return "is-open";
    if (status === "merged") return "is-merged";
    return "is-closed";
}

function goToPr(id: number) {
    router.push({name: 'pr-details', params: {id}})
}
</script>

<template>
    <section class="repo-details-view">
        <RouterLink to="/repos" class="back-link">Back to repositories</RouterLink>

        <article v-if="repo" class="panel">
            <header class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">Repository Details</p>
                    <h1 class="title">{{ repo.fullName }}</h1>
                    <p class="subtitle">Configuration and recent review status for this repository.</p>
                </div>

                <div class="hero-badges">
                    <span class="chip provider-chip" :class="providerClass(repo.provider)">
                        {{ providerLabel(repo.provider) }}
                    </span>
                    <span class="chip review-chip" :class="reviewStatus(repo.lastReviewAt).className">
                        {{ reviewStatus(repo.lastReviewAt).label }}
                    </span>
                </div>
            </header>

            <section class="meta-grid" aria-label="Repository metadata">
                <div class="meta-item">
                    <p class="meta-label">Provider</p>
                    <p class="meta-value">{{ providerLabel(repo.provider) }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Policy pack</p>
                    <p class="meta-value mono">{{ repo.policyPack }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Last review</p>
                    <p class="meta-value">{{ formatDate(repo.lastReviewAt) }}</p>
                </div>
            </section>

            <section class="pr-panel" aria-label="Pull requests placeholder">
                <h2 class="section-title">Pull Requests</h2>
                <div class="pr-table-shell">
                    <table class="pr-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Head SHA</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="pr in prs" :key="pr.id" @click="goToPr(pr.id)" class="row">
                                <td data-label="#" class="mono pr-number">#{{ pr.number }}</td>
                                <td data-label="Title" class="pr-title">{{ pr.title }}</td>
                                <td data-label="Status">
                                    <span class="status-pill" :class="prStatusClass(pr.status)">
                                        {{ pr.status }}
                                    </span>
                                </td>
                                <td data-label="Head SHA">
                                    <span class="sha-pill mono">{{ pr.headSha }}</span>
                                </td>
                                <td data-label="Updated" class="pr-updated">{{ formatDate(pr.updatedAt) }}</td>
                            </tr>
                            <tr v-if="prs.length === 0">
                                <td colspan="5" class="pr-empty">No PRs yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </article>

        <article v-else class="panel not-found">
            <h1 class="title">Repository not found</h1>
            <p class="subtitle">Check the URL or return to the repositories page.</p>
            <RouterLink to="/repos" class="back-link inline">Go to repositories</RouterLink>
        </article>
    </section>
</template>

<style scoped>
.repo-details-view {
    --surface: #ffffff;
    --surface-soft: #f8fbff;
    --ink-strong: #0f172a;
    --ink-body: #334155;
    --ink-soft: #64748b;
    --line: #dbe5f0;
    --line-strong: #c5d4e6;
    --accent: #0ea5e9;
    --accent-soft: #e0f2fe;
    --github-bg: #eef3ff;
    --github-ink: #304e9b;
    --gitlab-bg: #ffefe7;
    --gitlab-ink: #a14b21;
    --ok-bg: #e8f8ee;
    --ok-ink: #21693c;
    --never-bg: #f3f4f6;
    --never-ink: #4b5563;
    --shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.2);
    display: grid;
    gap: 14px;
}

.back-link {
    width: fit-content;
    text-decoration: none;
    color: #17608a;
    font-weight: 600;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 8px 12px;
    background: var(--surface);
    transition: border-color 150ms ease, background-color 150ms ease;
}

.back-link:hover {
    border-color: #9dd7ef;
    background: var(--accent-soft);
}

.back-link.inline {
    margin-top: 4px;
}

.row {
  cursor: pointer;
}

.row:hover {
  background: #fafafa;
}

.panel {
    border: 1px solid var(--line);
    border-radius: 18px;
    background: var(--surface);
    box-shadow: var(--shadow);
    padding: 18px;
    display: grid;
    gap: 18px;
}

.hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.eyebrow {
    margin: 0;
    color: var(--ink-soft);
    font-size: 0.77rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}

.title {
    margin: 4px 0 0;
    color: var(--ink-strong);
    font-size: 1.65rem;
    line-height: 1.15;
}

.subtitle {
    margin: 8px 0 0;
    color: var(--ink-soft);
    max-width: 64ch;
}

.hero-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    border: 1px solid transparent;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.provider-chip.is-github {
    background: var(--github-bg);
    color: var(--github-ink);
    border-color: #cad7fb;
}

.provider-chip.is-gitlab {
    background: var(--gitlab-bg);
    color: var(--gitlab-ink);
    border-color: #ffd8c5;
}

.review-chip.reviewed {
    color: var(--ok-ink);
    background: var(--ok-bg);
    border-color: #c8e8d3;
}

.review-chip.never {
    color: var(--never-ink);
    background: var(--never-bg);
    border-color: #e2e8f0;
}

.meta-grid {
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface-soft);
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    overflow: hidden;
}

.meta-item {
    padding: 14px;
    border-right: 1px solid var(--line);
}

.meta-item:last-child {
    border-right: none;
}

.meta-label {
    margin: 0;
    color: var(--ink-soft);
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    font-weight: 700;
}

.meta-value {
    margin: 8px 0 0;
    color: var(--ink-strong);
    font-weight: 600;
}

.mono {
    font-family: "JetBrains Mono", "Fira Code", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.pr-panel {
    border: 1px dashed #bfd7ea;
    border-radius: 12px;
    background: #f9fcff;
    padding: 16px;
}

.section-title {
    margin: 0;
    color: var(--ink-strong);
    font-size: 1.05rem;
}

.section-note {
    margin: 8px 0 0;
    color: var(--ink-soft);
}

.pr-table-shell {
    margin-top: 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
}

.pr-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.pr-table thead th {
    padding: 11px 12px;
    border-bottom: 1px solid var(--line);
    background: #f3f8ff;
    color: var(--ink-soft);
    text-align: left;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}

.pr-table tbody tr {
    transition: background-color 140ms ease;
}

.pr-table tbody tr:hover {
    background: #f7fbff;
}

.pr-table td {
    padding: 12px;
    border-bottom: 1px solid var(--line);
    color: var(--ink-body);
    vertical-align: top;
    overflow-wrap: anywhere;
}

.pr-table tbody tr:last-child td {
    border-bottom: none;
}

.pr-number {
    color: #1d3552;
    font-weight: 700;
}

.pr-title {
    color: #1d3552;
    font-weight: 600;
}

.status-pill {
    display: inline-flex;
    border-radius: 999px;
    padding: 4px 10px;
    border: 1px solid transparent;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-pill.is-open {
    background: #e8f6ff;
    border-color: #bfe3f7;
    color: #165a83;
}

.status-pill.is-merged {
    background: #e8f8ee;
    border-color: #c5e7d2;
    color: #24623d;
}

.status-pill.is-closed {
    background: #f3f4f6;
    border-color: #e0e4ea;
    color: #4b5563;
}

.sha-pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 8px;
    border: 1px solid #d9e5f3;
    background: #f2f7ff;
    color: #2c4c6f;
    font-size: 0.78rem;
}

.pr-updated {
    color: var(--ink-soft);
    font-size: 0.86rem;
}

.pr-empty {
    text-align: center;
    color: var(--ink-soft);
    padding: 18px 12px;
}

.not-found {
    justify-items: start;
}

@media (max-width: 900px) {
    .hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .hero-badges {
        justify-content: flex-start;
    }

    .meta-grid {
        grid-template-columns: 1fr;
    }

    .meta-item {
        border-right: none;
        border-bottom: 1px solid var(--line);
    }

    .meta-item:last-child {
        border-bottom: none;
    }

    .pr-table,
    .pr-table tbody,
    .pr-table tr,
    .pr-table td {
        display: block;
        width: 100%;
    }

    .pr-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .pr-table tbody {
        display: grid;
        gap: 10px;
        padding: 10px;
    }

    .pr-table tbody tr {
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #fff;
        padding: 10px;
    }

    .pr-table tbody td {
        border: none;
        padding: 8px 0;
        display: grid;
        grid-template-columns: 92px 1fr;
        gap: 10px;
    }

    .pr-table tbody td::before {
        content: attr(data-label);
        color: var(--ink-soft);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .pr-empty,
    .pr-empty::before {
        display: block;
        content: none;
        text-align: left;
    }
}
</style>
