<script setup lang="ts">
import { computed } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { mockPRs } from "@/mocks/prs";
import { mockRepos } from "@/mocks/repos";

const route = useRoute();
const prId = Number(route.params.id);

const pr = computed(() => mockPRs.find((item) => item.id === prId) ?? null);
const repo = computed(() => {
    if (!pr.value) return null;
    return mockRepos.find((item) => item.id === pr.value?.repoId) ?? null;
});

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

function prStatusClass(status: "open" | "merged" | "closed") {
    if (status === "open") return "is-open";
    if (status === "merged") return "is-merged";
    return "is-closed";
}

function resultClass(status: "pass" | "warn" | "fail") {
    if (status === "pass") return "is-pass";
    if (status === "warn") return "is-warn";
    return "is-fail";
}

const checks = computed(() => {
    if (!pr.value) return [];

    if (pr.value.status === "merged") {
        return [
            { id: "lint", name: "Lint", status: "pass" as const, note: "No style issues detected" },
            { id: "tests", name: "Unit tests", status: "pass" as const, note: "All tests are passing" },
            { id: "security", name: "Security scan", status: "warn" as const, note: "1 dependency advisory" },
        ];
    }

    if (pr.value.status === "closed") {
        return [
            { id: "lint", name: "Lint", status: "pass" as const, note: "No style issues detected" },
            { id: "tests", name: "Unit tests", status: "fail" as const, note: "3 failing tests" },
            { id: "security", name: "Security scan", status: "pass" as const, note: "No vulnerabilities" },
        ];
    }

    return [
        { id: "lint", name: "Lint", status: "pass" as const, note: "No style issues detected" },
        { id: "tests", name: "Unit tests", status: "warn" as const, note: "Test run still in progress" },
        { id: "security", name: "Security scan", status: "pass" as const, note: "No vulnerabilities" },
    ];
});

const summary = computed(() => {
    if (!pr.value) return null;

    if (pr.value.status === "merged") {
        return {
            risk: "Low",
            files: 8,
            additions: 142,
            deletions: 37,
            recommendation: "Merged cleanly. Keep an eye on dependency updates flagged by security scan.",
        };
    }

    if (pr.value.status === "closed") {
        return {
            risk: "High",
            files: 12,
            additions: 219,
            deletions: 121,
            recommendation: "Address failing tests and re-open with a narrower scoped change set.",
        };
    }

    return {
        risk: "Medium",
        files: 6,
        additions: 96,
        deletions: 24,
        recommendation: "Request one more reviewer for auth/session edge cases before merge.",
    };
});
</script>

<template>
    <section class="pr-details-view">
        <RouterLink v-if="repo" :to="{ name: 'repo-details', params: { id: repo.id } }" class="back-link">
            Back to repository
        </RouterLink>
        <RouterLink v-else to="/repos" class="back-link">Back to repositories</RouterLink>

        <article v-if="pr && repo && summary" class="panel">
            <header class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">Pull Request Details</p>
                    <h1 class="title">#{{ pr.number }} {{ pr.title }}</h1>
                    <p class="subtitle">
                        {{ repo.fullName }}
                    </p>
                </div>

                <div class="hero-badges">
                    <span class="chip provider-chip" :class="providerClass(repo.provider)">
                        {{ providerLabel(repo.provider) }}
                    </span>
                    <span class="chip status-pill" :class="prStatusClass(pr.status)">
                        {{ pr.status }}
                    </span>
                </div>
            </header>

            <section class="meta-grid" aria-label="Pull request metadata">
                <div class="meta-item">
                    <p class="meta-label">Head SHA</p>
                    <p class="meta-value mono">{{ pr.headSha }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Last update</p>
                    <p class="meta-value">{{ formatDate(pr.updatedAt) }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Policy pack</p>
                    <p class="meta-value mono">{{ repo.policyPack }}</p>
                </div>
            </section>

            <section class="summary-grid" aria-label="Analysis summary">
                <article class="summary-card">
                    <p class="meta-label">Risk</p>
                    <p class="summary-strong">{{ summary.risk }}</p>
                </article>

                <article class="summary-card">
                    <p class="meta-label">Files changed</p>
                    <p class="summary-strong">{{ summary.files }}</p>
                </article>

                <article class="summary-card">
                    <p class="meta-label">Lines added</p>
                    <p class="summary-strong plus">+{{ summary.additions }}</p>
                </article>

                <article class="summary-card">
                    <p class="meta-label">Lines removed</p>
                    <p class="summary-strong minus">-{{ summary.deletions }}</p>
                </article>
            </section>

            <section class="analysis-panel" aria-label="AI recommendation">
                <h2 class="section-title">AI Recommendation</h2>
                <p class="section-note">{{ summary.recommendation }}</p>
            </section>

            <section class="checks-panel" aria-label="Checks overview">
                <h2 class="section-title">Checks</h2>

                <div class="checks-table-shell">
                    <table class="checks-table">
                        <thead>
                            <tr>
                                <th>Check</th>
                                <th>Result</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="check in checks" :key="check.id">
                                <td data-label="Check" class="check-name">{{ check.name }}</td>
                                <td data-label="Result">
                                    <span class="result-pill" :class="resultClass(check.status)">
                                        {{ check.status }}
                                    </span>
                                </td>
                                <td data-label="Notes" class="check-note">{{ check.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </article>

        <article v-else class="panel not-found">
            <h1 class="title">Pull request not found</h1>
            <p class="subtitle">Check the URL or return to the repositories page.</p>
            <RouterLink to="/repos" class="back-link inline">Go to repositories</RouterLink>
        </article>
    </section>
</template>

<style scoped>
.pr-details-view {
    --surface: #ffffff;
    --surface-soft: #f8fbff;
    --ink-strong: #0f172a;
    --ink-body: #334155;
    --ink-soft: #64748b;
    --line: #dbe5f0;
    --line-strong: #c5d4e6;
    --accent-soft: #e0f2fe;
    --github-bg: #eef3ff;
    --github-ink: #304e9b;
    --gitlab-bg: #ffefe7;
    --gitlab-ink: #a14b21;
    --ok-bg: #e8f8ee;
    --ok-ink: #21693c;
    --warn-bg: #fff7e6;
    --warn-ink: #8a5a00;
    --fail-bg: #ffecec;
    --fail-ink: #8f1f1f;
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

.status-pill {
    text-transform: uppercase;
}

.status-pill.is-open {
    background: #e8f6ff;
    border-color: #bfe3f7;
    color: #165a83;
}

.status-pill.is-merged {
    background: var(--ok-bg);
    border-color: #c5e7d2;
    color: #24623d;
}

.status-pill.is-closed {
    background: #f3f4f6;
    border-color: #e0e4ea;
    color: #4b5563;
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

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.summary-card {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #ffffff;
    padding: 12px;
}

.summary-strong {
    margin: 8px 0 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #1d3552;
}

.summary-strong.plus {
    color: #21693c;
}

.summary-strong.minus {
    color: #8f1f1f;
}

.analysis-panel,
.checks-panel {
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

.checks-table-shell {
    margin-top: 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
}

.checks-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.checks-table thead th {
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

.checks-table td {
    padding: 12px;
    border-bottom: 1px solid var(--line);
    color: var(--ink-body);
    vertical-align: top;
    overflow-wrap: anywhere;
}

.checks-table tbody tr:last-child td {
    border-bottom: none;
}

.check-name {
    color: #1d3552;
    font-weight: 700;
}

.check-note {
    color: var(--ink-soft);
}

.result-pill {
    display: inline-flex;
    border-radius: 999px;
    padding: 4px 10px;
    border: 1px solid transparent;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.result-pill.is-pass {
    color: var(--ok-ink);
    background: var(--ok-bg);
    border-color: #c8e8d3;
}

.result-pill.is-warn {
    color: var(--warn-ink);
    background: var(--warn-bg);
    border-color: #f2dfb3;
}

.result-pill.is-fail {
    color: var(--fail-ink);
    background: var(--fail-bg);
    border-color: #f7caca;
}

.not-found {
    justify-items: start;
}

@media (max-width: 980px) {
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

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {

    .checks-table,
    .checks-table tbody,
    .checks-table tr,
    .checks-table td {
        display: block;
        width: 100%;
    }

    .checks-table thead {
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

    .checks-table tbody {
        display: grid;
        gap: 10px;
        padding: 10px;
    }

    .checks-table tbody tr {
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #fff;
        padding: 10px;
    }

    .checks-table tbody td {
        border: none;
        padding: 8px 0;
        display: grid;
        grid-template-columns: 92px 1fr;
        gap: 10px;
    }

    .checks-table tbody td::before {
        content: attr(data-label);
        color: var(--ink-soft);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
}

@media (max-width: 520px) {
    .title {
        font-size: 1.45rem;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
