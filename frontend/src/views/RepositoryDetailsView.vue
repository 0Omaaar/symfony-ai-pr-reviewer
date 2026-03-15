<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { router } from "@/router";
import type { PullRequest } from "@/types/pr";

const route = useRoute();
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const repoId = computed(() => Number(route.params.id));

type RepositoryDetails = {
    id: number;
    provider: "github";
    fullName: string;
    name: string;
    private: boolean;
    htmlUrl: string | null;
    defaultBranch: string | null;
    installationId: number | null;
};

type GithubRepositoryDetailsApiItem = {
    id?: number;
    name?: string;
    full_name?: string;
    private?: boolean;
    html_url?: string;
    default_branch?: string;
    installation_id?: number;
};

type GithubRepositoryDetailsApiResponse = {
    ok?: boolean;
    repository?: GithubRepositoryDetailsApiItem;
    branches?: GithubRepositoryBranchApiItem[];
    pull_requests?: GithubPullRequestApiItem[];
    insights?: GithubRepositoryInsightsApiItem;
    latest_pr_event?: GithubPullRequestEventApiItem | null;
};

type GithubRepositoryBranchApiItem = {
    name?: string;
    protected?: boolean;
    commit_sha?: string;
};

type RepositoryBranch = {
    name: string;
    protected: boolean;
    commitSha: string | null;
};

type GithubPullRequestApiItem = {
    id?: number;
    repo_id?: number;
    number?: number;
    title?: string;
    status?: "open" | "merged" | "closed";
    head_sha?: string;
    updated_at?: string;
};

type GithubRepositoryInsightsApiItem = {
    commits_count?: number;
    participants_count?: number;
    participants?: GithubRepositoryParticipantApiItem[];
    stargazers_count?: number;
    forks_count?: number;
    open_issues_count?: number;
    watchers_count?: number;
    size_kb?: number;
    primary_language?: string | null;
    topics?: string[];
    created_at?: string | null;
    updated_at?: string | null;
    pushed_at?: string | null;
};

type GithubPullRequestEventApiItem = {
    delivery_id?: string;
    action?: string;
    repo_id?: number;
    pr_number?: number;
    head_sha?: string;
    message?: string;
    occurred_at?: string;
};

type PullRequestEvent = {
    deliveryId: string;
    action: string;
    repoId: number;
    prNumber: number;
    headSha: string;
    message: string;
    occurredAt: string | null;
};

type GithubRepositoryParticipantApiItem = {
    login?: string;
    avatar_url?: string | null;
    html_url?: string | null;
    contributions?: number | null;
};

type RepositoryParticipant = {
    login: string;
    avatarUrl: string | null;
    htmlUrl: string | null;
    contributions: number | null;
};

type RepositoryInsights = {
    commitsCount: number;
    participantsCount: number;
    participants: RepositoryParticipant[];
    stargazersCount: number;
    forksCount: number;
    openIssuesCount: number;
    watchersCount: number;
    sizeKb: number;
    primaryLanguage: string | null;
    topics: string[];
    createdAt: string | null;
    updatedAt: string | null;
    pushedAt: string | null;
};

const repo = ref<RepositoryDetails | null>(null);
const branches = ref<RepositoryBranch[]>([]);
const prs = ref<PullRequest[]>([]);
const insights = ref<RepositoryInsights | null>(null);
const isLoading = ref(false);
const loadError = ref("");
const newPrAlert = ref("");
const lastSeenEventDeliveryId = ref<string | null>(null);
let clearAlertTimeout: ReturnType<typeof setTimeout> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;

const PR_POLL_INTERVAL_MS = 30000;

function mapRepositoryDetails(item: GithubRepositoryDetailsApiItem): RepositoryDetails | null {
    if (typeof item.id !== "number" || typeof item.name !== "string") {
        return null;
    }

    const fullName = typeof item.full_name === "string" && item.full_name !== "" ? item.full_name : item.name;

    return {
        id: item.id,
        provider: "github",
        fullName,
        name: item.name,
        private: Boolean(item.private),
        htmlUrl: typeof item.html_url === "string" ? item.html_url : null,
        defaultBranch: typeof item.default_branch === "string" ? item.default_branch : null,
        installationId: typeof item.installation_id === "number" ? item.installation_id : null,
    };
}

function mapRepositoryBranch(item: GithubRepositoryBranchApiItem): RepositoryBranch | null {
    if (typeof item.name !== "string" || item.name === "") {
        return null;
    }

    return {
        name: item.name,
        protected: Boolean(item.protected),
        commitSha: typeof item.commit_sha === "string" ? item.commit_sha : null,
    };
}

function mapPullRequest(item: GithubPullRequestApiItem): PullRequest | null {
    if (typeof item.id !== "number" || typeof item.number !== "number") {
        return null;
    }

    const status = item.status === "merged" || item.status === "closed" ? item.status : "open";
    const updatedAt = typeof item.updated_at === "string" ? item.updated_at : new Date().toISOString();

    return {
        id: item.id,
        repoId: typeof item.repo_id === "number" ? item.repo_id : repoId.value,
        number: item.number,
        title: typeof item.title === "string" && item.title !== "" ? item.title : "(No title)",
        status,
        headSha: typeof item.head_sha === "string" && item.head_sha !== "" ? item.head_sha : "N/A",
        updatedAt,
    };
}

function mapParticipant(item: GithubRepositoryParticipantApiItem): RepositoryParticipant | null {
    if (typeof item.login !== "string" || item.login === "") {
        return null;
    }

    return {
        login: item.login,
        avatarUrl: typeof item.avatar_url === "string" ? item.avatar_url : null,
        htmlUrl: typeof item.html_url === "string" ? item.html_url : null,
        contributions: typeof item.contributions === "number" ? item.contributions : null,
    };
}

function mapInsights(item: GithubRepositoryInsightsApiItem | undefined): RepositoryInsights | null {
    if (!item) return null;

    return {
        commitsCount: typeof item.commits_count === "number" ? item.commits_count : 0,
        participantsCount: typeof item.participants_count === "number" ? item.participants_count : 0,
        participants: Array.isArray(item.participants)
            ? item.participants.map(mapParticipant).filter((participant): participant is RepositoryParticipant => participant !== null)
            : [],
        stargazersCount: typeof item.stargazers_count === "number" ? item.stargazers_count : 0,
        forksCount: typeof item.forks_count === "number" ? item.forks_count : 0,
        openIssuesCount: typeof item.open_issues_count === "number" ? item.open_issues_count : 0,
        watchersCount: typeof item.watchers_count === "number" ? item.watchers_count : 0,
        sizeKb: typeof item.size_kb === "number" ? item.size_kb : 0,
        primaryLanguage: typeof item.primary_language === "string" ? item.primary_language : null,
        topics: Array.isArray(item.topics) ? item.topics.filter((topic): topic is string => typeof topic === "string") : [],
        createdAt: typeof item.created_at === "string" ? item.created_at : null,
        updatedAt: typeof item.updated_at === "string" ? item.updated_at : null,
        pushedAt: typeof item.pushed_at === "string" ? item.pushed_at : null,
    };
}

function mapPullRequestEvent(item: GithubPullRequestEventApiItem | null | undefined): PullRequestEvent | null {
    if (!item || typeof item.delivery_id !== "string" || item.delivery_id === "" || typeof item.pr_number !== "number") {
        return null;
    }

    return {
        deliveryId: item.delivery_id,
        action: typeof item.action === "string" ? item.action : "unknown",
        repoId: typeof item.repo_id === "number" ? item.repo_id : repoId.value,
        prNumber: item.pr_number,
        headSha: typeof item.head_sha === "string" ? item.head_sha : "N/A",
        message: typeof item.message === "string" && item.message !== ""
            ? item.message
            : `Pull request #${item.pr_number} event received.`,
        occurredAt: typeof item.occurred_at === "string" ? item.occurred_at : null,
    };
}

function showNewPrAlert(message: string) {
    newPrAlert.value = message;
    if (clearAlertTimeout) {
        clearTimeout(clearAlertTimeout);
    }
    clearAlertTimeout = setTimeout(() => {
        newPrAlert.value = "";
        clearAlertTimeout = null;
    }, 8000);
}

function dismissNewPrAlert() {
    newPrAlert.value = "";
    if (clearAlertTimeout) {
        clearTimeout(clearAlertTimeout);
        clearAlertTimeout = null;
    }
}

async function loadRepoDetails(options: { silent?: boolean } = {}) {
    const silent = options.silent === true;

    if (!Number.isFinite(repoId.value) || repoId.value <= 0) {
        if (!silent) {
            repo.value = null;
            branches.value = [];
            prs.value = [];
            insights.value = null;
        }
        loadError.value = "Invalid repository id.";
        return;
    }

    if (!silent) {
        isLoading.value = true;
        loadError.value = "";
    }

    try {
        const res = await fetch(`${apiBaseUrl}/api/github/repositories/${repoId.value}`, {
            credentials: "include",
        });

        if (res.status === 404) {
            if (!silent) {
                repo.value = null;
                branches.value = [];
                prs.value = [];
                insights.value = null;
            }
            return;
        }

        if (!res.ok) {
            throw new Error(`Failed to fetch repository (${res.status})`);
        }

        const data = (await res.json()) as GithubRepositoryDetailsApiResponse;
        const mapped = data.repository ? mapRepositoryDetails(data.repository) : null;
        const latestEvent = mapPullRequestEvent(data.latest_pr_event);
        const previousPrIds = new Set(prs.value.map((pullRequest) => pullRequest.id));
        const hadPreviousData = prs.value.length > 0;
        const nextPrs = Array.isArray(data.pull_requests)
            ? data.pull_requests.map(mapPullRequest).filter((item): item is PullRequest => item !== null)
            : [];

        repo.value = mapped;
        branches.value = Array.isArray(data.branches)
            ? data.branches.map(mapRepositoryBranch).filter((item): item is RepositoryBranch => item !== null)
            : [];
        prs.value = nextPrs;
        insights.value = mapInsights(data.insights);

        let webhookAlertShown = false;
        if (latestEvent) {
            if (silent && latestEvent.deliveryId !== lastSeenEventDeliveryId.value) {
                showNewPrAlert(latestEvent.message);
                webhookAlertShown = true;
            }
            lastSeenEventDeliveryId.value = latestEvent.deliveryId;
        }

        if (silent && hadPreviousData) {
            const newPullRequests = nextPrs.filter((pullRequest) => !previousPrIds.has(pullRequest.id));
            if (!webhookAlertShown && newPullRequests.length === 1) {
                showNewPrAlert(`New pull request #${newPullRequests[0].number} added to this repository.`);
            } else if (!webhookAlertShown && newPullRequests.length > 1) {
                showNewPrAlert(`${newPullRequests.length} new pull requests were added to this repository.`);
            }
        }
    } catch (error) {
        if (!silent) {
            repo.value = null;
            branches.value = [];
            prs.value = [];
            insights.value = null;
        }
        loadError.value = error instanceof Error ? error.message : "Failed to load repository.";
    } finally {
        if (!silent) {
            isLoading.value = false;
        }
    }
}

onMounted(() => {
    void loadRepoDetails();
    pollInterval = setInterval(() => {
        void loadRepoDetails({ silent: true });
    }, PR_POLL_INTERVAL_MS);
});

watch(() => route.params.id, () => {
    lastSeenEventDeliveryId.value = null;
    dismissNewPrAlert();
    void loadRepoDetails();
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    if (clearAlertTimeout) {
        clearTimeout(clearAlertTimeout);
        clearAlertTimeout = null;
    }
});

function formatDate(iso: string | null) {
    if (!iso) return "Never";
    return new Date(iso).toLocaleString("en-US", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

function providerLabel(provider: "github") {
    if (provider === "github") return "GitHub";
    return "GitHub";
}

function providerClass(provider: "github") {
    if (provider === "github") return "is-github";
    return "is-github";
}

function visibilityStatus(isPrivate: boolean) {
    if (isPrivate) return { label: "Private", className: "never" };
    return { label: "Public", className: "reviewed" };
}

function prStatusClass(status: "open" | "merged" | "closed") {
    if (status === "open") return "is-open";
    if (status === "merged") return "is-merged";
    return "is-closed";
}

function goToPr(id: number) {
    router.push({ name: "pr-details", params: { id }, query: { repoId: String(repoId.value) } });
}
</script>

<template>
    <section class="repo-details-view">
        <RouterLink to="/repos" class="back-link">Back to repositories</RouterLink>
        <transition name="toast-pop">
            <div v-if="newPrAlert" class="pr-toast" role="status" aria-live="polite">
                <div class="pr-toast-copy">
                    <p class="pr-toast-title">New Pull Request</p>
                    <p class="pr-toast-message">{{ newPrAlert }}</p>
                </div>
                <button type="button" class="alert-close" @click="dismissNewPrAlert">Dismiss</button>
            </div>
        </transition>

        <article v-if="isLoading" class="panel state-panel" role="status" aria-live="polite">
            <span class="loader" aria-hidden="true"></span>
            <p class="subtitle">Loading repository details...</p>
        </article>

        <article v-else-if="repo" class="panel">
            <div v-if="loadError" class="alert error" role="alert">{{ loadError }}</div>

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
                    <span class="chip review-chip" :class="visibilityStatus(repo.private).className">
                        {{ visibilityStatus(repo.private).label }}
                    </span>
                </div>
            </header>

            <section class="meta-grid" aria-label="Repository metadata">
                <div class="meta-item">
                    <p class="meta-label">Provider</p>
                    <p class="meta-value">{{ providerLabel(repo.provider) }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Default branch</p>
                    <p class="meta-value mono">{{ repo.defaultBranch ?? "Unknown" }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Repository ID</p>
                    <p class="meta-value mono">{{ repo.id }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Installation ID</p>
                    <p class="meta-value mono">{{ repo.installationId ?? "Unknown" }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Name</p>
                    <p class="meta-value mono">{{ repo.name }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Repository URL</p>
                    <p class="meta-value">
                        <a v-if="repo.htmlUrl" :href="repo.htmlUrl" target="_blank" rel="noopener noreferrer" class="repo-link">
                            Open on GitHub
                        </a>
                        <span v-else>Unavailable</span>
                    </p>
                </div>
            </section>

            <section class="branches-panel" aria-label="Repository branches">
                <h2 class="section-title">Branches</h2>
                <ul v-if="branches.length > 0" class="branches-list">
                    <li v-for="branch in branches" :key="branch.name" class="branch-item">
                        <span class="sha-pill mono">{{ branch.name }}</span>
                        <span class="status-pill" :class="branch.protected ? 'is-merged' : 'is-open'">
                            {{ branch.protected ? "protected" : "unprotected" }}
                        </span>
                        <span class="mono branch-sha">
                            {{ branch.commitSha ? branch.commitSha.slice(0, 12) : "No SHA" }}
                        </span>
                    </li>
                </ul>
                <p v-else class="section-note">No branches found.</p>
            </section>

            <section v-if="insights" class="insights-panel" aria-label="Repository insights">
                <h2 class="section-title">Repository Insights</h2>

                <div class="insights-grid">
                    <div class="insight-card">
                        <p class="insight-label">Commits</p>
                        <p class="insight-value">{{ insights.commitsCount }}</p>
                    </div>
                    <div class="insight-card">
                        <p class="insight-label">Participants</p>
                        <p class="insight-value">{{ insights.participantsCount }}</p>
                    </div>
                    <div class="insight-card">
                        <p class="insight-label">Stars</p>
                        <p class="insight-value">{{ insights.stargazersCount }}</p>
                    </div>
                    <div class="insight-card">
                        <p class="insight-label">Forks</p>
                        <p class="insight-value">{{ insights.forksCount }}</p>
                    </div>
                    <div class="insight-card">
                        <p class="insight-label">Open issues</p>
                        <p class="insight-value">{{ insights.openIssuesCount }}</p>
                    </div>
                    <div class="insight-card">
                        <p class="insight-label">Watchers</p>
                        <p class="insight-value">{{ insights.watchersCount }}</p>
                    </div>
                </div>

                <div class="meta-grid insights-meta">
                    <div class="meta-item">
                        <p class="meta-label">Primary language</p>
                        <p class="meta-value">{{ insights.primaryLanguage ?? "Unknown" }}</p>
                    </div>
                    <div class="meta-item">
                        <p class="meta-label">Size</p>
                        <p class="meta-value">{{ insights.sizeKb }} KB</p>
                    </div>
                    <div class="meta-item">
                        <p class="meta-label">Created</p>
                        <p class="meta-value">{{ formatDate(insights.createdAt) }}</p>
                    </div>
                    <div class="meta-item">
                        <p class="meta-label">Updated</p>
                        <p class="meta-value">{{ formatDate(insights.updatedAt) }}</p>
                    </div>
                    <div class="meta-item">
                        <p class="meta-label">Last push</p>
                        <p class="meta-value">{{ formatDate(insights.pushedAt) }}</p>
                    </div>
                    <div class="meta-item">
                        <p class="meta-label">Topics</p>
                        <p class="meta-value">
                            <span v-if="insights.topics.length === 0">None</span>
                            <span v-else class="topics">
                                <span v-for="topic in insights.topics" :key="topic" class="sha-pill">{{ topic }}</span>
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="insights.participants.length > 0" class="participants">
                    <p class="meta-label">Top participants</p>
                    <ul class="participants-list">
                        <li v-for="participant in insights.participants" :key="participant.login" class="participant-item">
                            <img
                                v-if="participant.avatarUrl"
                                :src="participant.avatarUrl"
                                :alt="participant.login"
                                class="participant-avatar"
                            />
                            <span v-else class="participant-avatar placeholder">{{ participant.login.slice(0, 1).toUpperCase() }}</span>
                            <a
                                v-if="participant.htmlUrl"
                                :href="participant.htmlUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="participant-link"
                            >
                                {{ participant.login }}
                            </a>
                            <span v-else class="participant-link">{{ participant.login }}</span>
                            <span class="branch-sha">{{ participant.contributions ?? 0 }} commits</span>
                        </li>
                    </ul>
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
            <p class="subtitle">{{ loadError || "Check the URL or return to the repositories page." }}</p>
            <RouterLink to="/repos" class="back-link inline">Go to repositories</RouterLink>
        </article>
    </section>
</template>

<style scoped>
.repo-details-view {
    display: grid;
    gap: 16px;
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 24px;
}

/* ─── Back link ──────────────────────────────────────────────── */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    text-decoration: none;
    color: var(--ink-soft);
    font-weight: 600;
    font-size: 0.84rem;
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    padding: 7px 13px;
    background: var(--surface);
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.back-link:hover {
    border-color: var(--accent-mid);
    color: var(--accent-hover);
    background: var(--accent-light);
}

.back-link.inline { margin-top: 4px; }

/* ─── Toast ──────────────────────────────────────────────────── */
.pr-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 70;
    width: min(420px, calc(100vw - 24px));
    border: 1px solid var(--accent-mid);
    border-radius: var(--radius-card);
    background: linear-gradient(135deg, #f0faff 0%, #e4f6ff 100%);
    box-shadow: 0 12px 32px -12px rgba(13,126,164,0.45);
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.pr-toast-title {
    margin: 0;
    color: var(--accent-hover);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 800;
}

.pr-toast-message {
    margin: 5px 0 0;
    color: var(--ink-strong);
    font-weight: 600;
    font-size: 0.88rem;
    line-height: 1.45;
}

.alert-close {
    border: 1px solid var(--accent-mid);
    border-radius: 8px;
    background: #ffffff;
    color: var(--accent-hover);
    font-weight: 700;
    font-size: 0.78rem;
    padding: 5px 10px;
    cursor: pointer;
    flex-shrink: 0;
    font-family: var(--font-sans);
}

.toast-pop-enter-active, .toast-pop-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-pop-enter-from, .toast-pop-leave-to { opacity: 0; transform: translateY(-8px) scale(0.98); }

/* ─── Panel ──────────────────────────────────────────────────── */
.panel {
    border: 1px solid var(--line);
    border-radius: var(--radius-card);
    background: var(--surface);
    box-shadow: var(--shadow-card);
    padding: 20px 22px;
    display: grid;
    gap: 20px;
}

.state-panel {
    justify-items: center;
    text-align: center;
    min-height: 200px;
    align-content: center;
}

/* ─── Alert ──────────────────────────────────────────────────── */
.alert.error {
    border-radius: var(--radius-inner);
    border: 1px solid #fca5a5;
    background: #fff1f2;
    color: #991b1b;
    padding: 11px 14px;
    font-size: 0.88rem;
}

/* ─── Hero ───────────────────────────────────────────────────── */
.hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.eyebrow {
    margin: 0;
    color: var(--accent);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 800;
}

.title {
    margin: 6px 0 0;
    color: var(--ink-strong);
    font-size: 1.55rem;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.subtitle {
    margin: 6px 0 0;
    color: var(--ink-soft);
    font-size: 0.88rem;
    max-width: 64ch;
}

.hero-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    padding-top: 4px;
}

/* ─── Chips ──────────────────────────────────────────────────── */
.chip {
    display: inline-flex;
    align-items: center;
    border-radius: var(--radius-pill);
    padding: 4px 10px;
    border: 1px solid transparent;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}

.provider-chip.is-github { background: #eef3ff; color: #304e9b; border-color: #c7d3f8; }
.provider-chip.is-gitlab { background: #fff1e6; color: #9a4418; border-color: #fdc9a5; }

.review-chip.reviewed { color: var(--merged-ink); background: var(--merged-bg); border-color: var(--merged-line); }
.review-chip.never    { color: var(--closed-ink); background: var(--closed-bg); border-color: var(--closed-line); }

/* ─── Meta grid ──────────────────────────────────────────────── */
.meta-grid {
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    background: var(--surface-soft);
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    overflow: hidden;
}

.meta-item {
    padding: 14px 16px;
    border-right: 1px solid var(--line);
}

.meta-item:last-child { border-right: none; }
.meta-item:nth-child(3n) { border-right: none; }

.meta-label {
    margin: 0;
    color: var(--ink-faint);
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}

.meta-value {
    margin: 6px 0 0;
    color: var(--ink-strong);
    font-weight: 600;
    font-size: 0.88rem;
}

.mono { font-family: var(--font-mono); }

/* ─── Section panels (replaced dashed borders) ───────────────── */
.branches-panel, .insights-panel, .pr-panel {
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    background: var(--surface-soft);
    padding: 16px 18px;
}

.section-title {
    margin: 0 0 14px;
    color: var(--ink-strong);
    font-size: 0.9rem;
    font-weight: 800;
}

.section-note {
    margin: 0;
    color: var(--ink-faint);
    font-size: 0.84rem;
}

/* ─── Insights ───────────────────────────────────────────────── */
.insights-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 14px;
}

.insight-card {
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    background: var(--surface);
    padding: 12px;
    position: relative;
    overflow: hidden;
}

.insight-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), #57c3e8);
}

.insight-label {
    margin: 0;
    color: var(--ink-faint);
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.insight-value {
    margin: 6px 0 0;
    color: var(--ink-strong);
    font-size: 1.2rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.insights-meta { margin-top: 0; }

.topics { display: inline-flex; flex-wrap: wrap; gap: 5px; }

/* ─── Participants ───────────────────────────────────────────── */
.participants { margin-top: 14px; }

.participants-list {
    margin: 10px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 6px;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    background: var(--surface);
    padding: 9px 12px;
}

.participant-avatar {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    object-fit: cover;
}

.participant-avatar.placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--accent-light);
    color: var(--accent-hover);
    font-size: 0.78rem;
    font-weight: 800;
}

.participant-link {
    color: var(--accent-hover);
    text-decoration: none;
    font-weight: 700;
    font-size: 0.88rem;
}

.participant-link:hover { text-decoration: underline; }

.branch-sha { color: var(--ink-faint); font-size: 0.8rem; margin-left: auto; }

/* ─── Branches ───────────────────────────────────────────────── */
.branches-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 6px;
}

.branch-item {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    padding: 9px 12px;
    background: var(--surface);
    flex-wrap: wrap;
}

.sha-pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid var(--line);
    background: var(--surface-raised);
    color: var(--ink-body);
    font-size: 0.78rem;
}

/* ─── PR table ───────────────────────────────────────────────── */
.pr-table-shell {
    border: 1px solid var(--line);
    border-radius: var(--radius-inner);
    background: var(--surface);
    overflow: hidden;
}

.pr-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.pr-table thead th {
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    background: var(--surface-soft);
    color: var(--ink-faint);
    text-align: left;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-weight: 700;
}

.pr-table tbody tr { transition: background 0.12s ease; cursor: pointer; }
.pr-table tbody tr:hover { background: var(--surface-soft); }

.pr-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    color: var(--ink-body);
    vertical-align: middle;
    overflow-wrap: anywhere;
    font-size: 0.88rem;
}

.pr-table tbody tr:last-child td { border-bottom: none; }

.pr-number { color: var(--ink-strong); font-weight: 800; }
.pr-title  { color: var(--ink-strong); font-weight: 600; }
.pr-updated { color: var(--ink-faint); font-size: 0.82rem; }

.row { cursor: pointer; }
.row:hover { background: var(--surface-soft); }

.pr-empty {
    text-align: center;
    color: var(--ink-faint);
    padding: 20px 14px;
}

.repo-link {
    color: var(--accent-hover);
    text-decoration: none;
    font-weight: 700;
}

.repo-link:hover { text-decoration: underline; }

/* ─── Loading ────────────────────────────────────────────────── */
.loader {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid var(--line);
    border-top-color: var(--accent);
    animation: spin 0.75s linear infinite;
}

.not-found { justify-items: start; }

/* ─── Responsive ─────────────────────────────────────────────── */
@media (max-width: 900px) {
    .pr-toast { right: 12px; left: 12px; width: auto; top: 12px; }
    .hero { flex-direction: column; align-items: flex-start; }
    .hero-badges { justify-content: flex-start; }
    .meta-grid { grid-template-columns: 1fr; }
    .insights-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .meta-item { border-right: none; border-bottom: 1px solid var(--line); }
    .meta-item:last-child { border-bottom: none; }

    .pr-table, .pr-table tbody, .pr-table tr, .pr-table td { display: block; width: 100%; }
    .pr-table thead {
        position: absolute; width: 1px; height: 1px; margin: -1px;
        padding: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }
    .pr-table tbody { display: grid; gap: 8px; padding: 10px; }
    .pr-table tbody tr { border: 1px solid var(--line); border-radius: var(--radius-inner); background: var(--surface); padding: 10px; }
    .pr-table tbody td { border: none; padding: 6px 0; display: grid; grid-template-columns: 88px 1fr; gap: 8px; }
    .pr-table tbody td::before { content: attr(data-label); color: var(--ink-faint); font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; }
    .pr-empty, .pr-empty::before { display: block; content: none; }
}

@media (max-width: 560px) {
    .insights-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
