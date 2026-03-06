<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink, useRoute } from "vue-router";
import type { PullRequest } from "@/types/pr";

const route = useRoute();
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const prId = computed(() => Number(route.params.id));
const fallbackRepoId = computed(() => {
    const queryValue = route.query.repoId;
    const raw = Array.isArray(queryValue) ? queryValue[0] : queryValue;
    const parsed = Number(raw);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

type GithubRepositoryApiItem = {
    id?: number;
    full_name?: string;
    name?: string;
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

type GithubPullRequestDetailsApiResponse = {
    ok?: boolean;
    repository?: GithubRepositoryApiItem;
    pull_request?: GithubPullRequestApiItem;
};

type GithubPullRequestChangesApiResponse = {
    ok?: boolean;
    summary?: GithubPullRequestChangesSummaryApiItem;
    files?: GithubPullRequestChangedFileApiItem[];
};

type GithubPullRequestChangesSummaryApiItem = {
    changed_files?: number;
    additions?: number;
    deletions?: number;
    commits?: number;
    comments?: number;
    review_comments?: number;
};

type GithubPullRequestChangedFileApiItem = {
    filename?: string;
    status?: string;
    additions?: number;
    deletions?: number;
    changes?: number;
    patch?: string | null;
    previous_filename?: string | null;
};

type RepoForPrDetails = {
    id: number;
    provider: "github";
    fullName: string;
};

type PullRequestChangesSummary = {
    changedFiles: number;
    additions: number;
    deletions: number;
    commits: number;
    comments: number;
    reviewComments: number;
};

type PullRequestChangedFile = {
    filename: string;
    status: string;
    additions: number;
    deletions: number;
    changes: number;
    patch: string | null;
    previousFilename: string | null;
};

type VisiblePullRequestChangedFile = PullRequestChangedFile & {
    key: string;
    previewLines: string[];
    hasMorePatch: boolean;
    reachedPatchRenderCap: boolean;
};

const pr = ref<PullRequest | null>(null);
const repo = ref<RepoForPrDetails | null>(null);
const changesSummary = ref<PullRequestChangesSummary | null>(null);
const changedFiles = ref<PullRequestChangedFile[]>([]);
const isLoading = ref(false);
const loadError = ref("");
const visibleFileCount = ref(15);
const patchLineLimits = ref<Record<string, number>>({});

const INITIAL_VISIBLE_FILES = 15;
const FILES_INCREMENT = 15;
const INITIAL_PATCH_LINES = 140;
const PATCH_LINES_INCREMENT = 160;
const MAX_PATCH_LINES_PER_FILE = 1200;

function mapApiRepository(item: GithubRepositoryApiItem | undefined): RepoForPrDetails | null {
    if (!item || typeof item.id !== "number") {
        return null;
    }

    const fullName = typeof item.full_name === "string" && item.full_name !== "" ? item.full_name : item.name;
    if (typeof fullName !== "string" || fullName === "") {
        return null;
    }

    return {
        id: item.id,
        provider: "github",
        fullName,
    };
}

function mapApiPullRequest(item: GithubPullRequestApiItem | undefined, fallbackRepoId: number): PullRequest | null {
    if (!item || typeof item.id !== "number" || typeof item.number !== "number") {
        return null;
    }

    const status = item.status === "merged" || item.status === "closed" ? item.status : "open";

    return {
        id: item.id,
        repoId: typeof item.repo_id === "number" ? item.repo_id : fallbackRepoId,
        number: item.number,
        title: typeof item.title === "string" && item.title !== "" ? item.title : "(No title)",
        status,
        headSha: typeof item.head_sha === "string" && item.head_sha !== "" ? item.head_sha : "N/A",
        updatedAt: typeof item.updated_at === "string" ? item.updated_at : new Date().toISOString(),
    };
}

function mapChangesSummary(item: GithubPullRequestChangesSummaryApiItem | undefined): PullRequestChangesSummary | null {
    if (!item) return null;

    return {
        changedFiles: typeof item.changed_files === "number" ? item.changed_files : 0,
        additions: typeof item.additions === "number" ? item.additions : 0,
        deletions: typeof item.deletions === "number" ? item.deletions : 0,
        commits: typeof item.commits === "number" ? item.commits : 0,
        comments: typeof item.comments === "number" ? item.comments : 0,
        reviewComments: typeof item.review_comments === "number" ? item.review_comments : 0,
    };
}

function mapChangedFile(item: GithubPullRequestChangedFileApiItem): PullRequestChangedFile | null {
    if (typeof item.filename !== "string" || item.filename === "") {
        return null;
    }

    return {
        filename: item.filename,
        status: typeof item.status === "string" && item.status !== "" ? item.status : "modified",
        additions: typeof item.additions === "number" ? item.additions : 0,
        deletions: typeof item.deletions === "number" ? item.deletions : 0,
        changes: typeof item.changes === "number" ? item.changes : 0,
        patch: typeof item.patch === "string" ? item.patch : null,
        previousFilename: typeof item.previous_filename === "string" ? item.previous_filename : null,
    };
}

function fileStatusClass(status: string): string {
    if (status === "added") return "is-merged";
    if (status === "removed") return "is-closed";
    return "is-open";
}

function diffLineClass(line: string): string {
    if (line.startsWith("@@")) return "diff-line hunk";
    if (line.startsWith("diff --git") || line.startsWith("index ") || line.startsWith("+++ ") || line.startsWith("--- ")) {
        return "diff-line meta";
    }
    if (line.startsWith("+")) return "diff-line added";
    if (line.startsWith("-")) return "diff-line removed";
    return "diff-line context";
}

function fileKey(file: PullRequestChangedFile): string {
    return `${file.filename}::${file.previousFilename ?? ""}`;
}

function currentPatchLineLimit(file: PullRequestChangedFile): number {
    const key = fileKey(file);
    return patchLineLimits.value[key] ?? INITIAL_PATCH_LINES;
}

function extractPatchPreview(patch: string, maxLines: number): { lines: string[]; hasMore: boolean } {
    const lines: string[] = [];
    let start = 0;

    for (let i = 0; i < patch.length; i++) {
        if (patch.charCodeAt(i) !== 10) continue;

        lines.push(patch.slice(start, i));
        start = i + 1;
        if (lines.length > maxLines) {
            return {
                lines: lines.slice(0, maxLines),
                hasMore: true,
            };
        }
    }

    lines.push(patch.slice(start));

    if (lines.length > maxLines) {
        return {
            lines: lines.slice(0, maxLines),
            hasMore: true,
        };
    }

    return {
        lines,
        hasMore: false,
    };
}

function buildVisibleFile(file: PullRequestChangedFile): VisiblePullRequestChangedFile {
    const key = fileKey(file);
    const requestedLineCount = Math.min(currentPatchLineLimit(file), MAX_PATCH_LINES_PER_FILE);
    let previewLines: string[] = [];
    let hasMorePatch = false;

    if (file.patch) {
        const preview = extractPatchPreview(file.patch, requestedLineCount);
        previewLines = preview.lines;
        hasMorePatch = preview.hasMore;
    }

    return {
        ...file,
        key,
        previewLines,
        hasMorePatch,
        reachedPatchRenderCap: hasMorePatch && requestedLineCount >= MAX_PATCH_LINES_PER_FILE,
    };
}

function showMorePatchLines(file: PullRequestChangedFile) {
    const key = fileKey(file);
    const current = currentPatchLineLimit(file);
    const next = Math.min(current + PATCH_LINES_INCREMENT, MAX_PATCH_LINES_PER_FILE);
    patchLineLimits.value = {
        ...patchLineLimits.value,
        [key]: next,
    };
}

function showMoreFiles() {
    visibleFileCount.value = Math.min(changedFiles.value.length, visibleFileCount.value + FILES_INCREMENT);
}

const visibleChangedFiles = computed<VisiblePullRequestChangedFile[]>(() => {
    return changedFiles.value.slice(0, visibleFileCount.value).map(buildVisibleFile);
});

const canShowMoreFiles = computed(() => changedFiles.value.length > visibleFileCount.value);

async function loadPullRequestDetails() {
    if (!Number.isFinite(prId.value) || prId.value <= 0) {
        pr.value = null;
        repo.value = null;
        changesSummary.value = null;
        changedFiles.value = [];
        loadError.value = "The URL contains an invalid pull request identifier.";
        return;
    }

    isLoading.value = true;
    loadError.value = "";

    try {
        const response = await fetch(`${apiBaseUrl}/api/github/pull-requests/${prId.value}`, {
            credentials: "include",
        });

        if (response.status === 404) {
            pr.value = null;
            repo.value = null;
            changesSummary.value = null;
            changedFiles.value = [];
            return;
        }

        if (!response.ok) {
            throw new Error(`Failed to fetch pull request (${response.status})`);
        }

        const data = (await response.json()) as GithubPullRequestDetailsApiResponse;
        const mappedRepo = mapApiRepository(data.repository);
        const mappedPr = mapApiPullRequest(data.pull_request, mappedRepo?.id ?? 0);

        repo.value = mappedRepo;
        pr.value = mappedPr;

        if (mappedPr) {
            const changesResponse = await fetch(`${apiBaseUrl}/api/github/pull-requests/${prId.value}/changes`, {
                credentials: "include",
            });

            if (changesResponse.ok) {
                const changesData = (await changesResponse.json()) as GithubPullRequestChangesApiResponse;
                changesSummary.value = mapChangesSummary(changesData.summary);
                changedFiles.value = Array.isArray(changesData.files)
                    ? changesData.files.map(mapChangedFile).filter((file): file is PullRequestChangedFile => file !== null)
                    : [];
                visibleFileCount.value = INITIAL_VISIBLE_FILES;
                patchLineLimits.value = {};
            } else {
                changesSummary.value = null;
                changedFiles.value = [];
                loadError.value = `Failed to fetch pull request changes (${changesResponse.status}).`;
            }
        } else {
            changesSummary.value = null;
            changedFiles.value = [];
        }
    } catch (error) {
        pr.value = null;
        repo.value = null;
        changesSummary.value = null;
        changedFiles.value = [];
        loadError.value = error instanceof Error ? error.message : "Failed to load pull request.";
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    void loadPullRequestDetails();
});

watch(() => route.params.id, () => {
    visibleFileCount.value = INITIAL_VISIBLE_FILES;
    patchLineLimits.value = {};
    void loadPullRequestDetails();
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

const notFoundTitle = computed(() => {
    if (!Number.isFinite(prId.value) || prId.value <= 0) {
        return "Invalid pull request id";
    }
    if (!pr.value) {
        return `Pull request #${prId.value} not found`;
    }
    return "Repository not found";
});

const notFoundMessage = computed(() => {
    if (loadError.value !== "") {
        return loadError.value;
    }
    if (!Number.isFinite(prId.value) || prId.value <= 0) {
        return "The URL contains an invalid pull request identifier.";
    }
    if (!pr.value) {
        return "This pull request was not found in your connected repositories.";
    }
    return "The repository linked to this pull request is unavailable.";
});

const notFoundLink = computed(() => {
    if (pr.value?.repoId) {
        return { name: "repo-details", params: { id: pr.value.repoId } };
    }
    if (fallbackRepoId.value) {
        return { name: "repo-details", params: { id: fallbackRepoId.value } };
    }
    return { name: "repos" };
});

const notFoundLinkLabel = computed(() => {
    if (pr.value?.repoId || fallbackRepoId.value) {
        return "Go to repository";
    }
    return "Go to repositories";
});

const backLinkTo = computed(() => {
    if (repo.value?.id) {
        return { name: "repo-details", params: { id: repo.value.id } };
    }
    if (fallbackRepoId.value) {
        return { name: "repo-details", params: { id: fallbackRepoId.value } };
    }
    return { name: "repos" };
});

const backLinkLabel = computed(() => {
    if (repo.value?.id || fallbackRepoId.value) {
        return "Back to repository";
    }
    return "Back to repositories";
});
</script>

<template>
    <section class="pr-details-view">
        <RouterLink :to="backLinkTo" class="back-link">{{ backLinkLabel }}</RouterLink>

        <article v-if="isLoading" class="panel loading-panel" role="status" aria-live="polite">
            <span class="loader" aria-hidden="true"></span>
            <h1 class="title">Loading pull request...</h1>
            <p class="subtitle">Please wait while we fetch the latest pull request details.</p>
        </article>

        <article v-else-if="pr && repo" class="panel">
            <div v-if="loadError" class="alert error" role="alert">{{ loadError }}</div>

            <header class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">Pull Request Details</p>
                    <h1 class="title">#{{ pr.number }} {{ pr.title }}</h1>
                    <p class="subtitle">{{ repo.fullName }}</p>
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
                    <p class="meta-label">Pull request ID</p>
                    <p class="meta-value mono">{{ pr.id }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Head SHA</p>
                    <p class="meta-value mono">{{ pr.headSha }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Last update</p>
                    <p class="meta-value">{{ formatDate(pr.updatedAt) }}</p>
                </div>

                <div class="meta-item">
                    <p class="meta-label">Repository</p>
                    <p class="meta-value mono">{{ repo.fullName }}</p>
                </div>
            </section>

            <section v-if="changesSummary" class="changes-panel" aria-label="Pull request changes summary">
                <h2 class="section-title">Changes Summary</h2>
                <div class="summary-grid">
                    <article class="summary-card">
                        <p class="meta-label">Files changed</p>
                        <p class="summary-strong">{{ changesSummary.changedFiles }}</p>
                    </article>
                    <article class="summary-card">
                        <p class="meta-label">Lines added</p>
                        <p class="summary-strong plus">+{{ changesSummary.additions }}</p>
                    </article>
                    <article class="summary-card">
                        <p class="meta-label">Lines removed</p>
                        <p class="summary-strong minus">-{{ changesSummary.deletions }}</p>
                    </article>
                    <article class="summary-card">
                        <p class="meta-label">Commits</p>
                        <p class="summary-strong">{{ changesSummary.commits }}</p>
                    </article>
                    <article class="summary-card">
                        <p class="meta-label">Comments</p>
                        <p class="summary-strong">{{ changesSummary.comments }}</p>
                    </article>
                    <article class="summary-card">
                        <p class="meta-label">Review comments</p>
                        <p class="summary-strong">{{ changesSummary.reviewComments }}</p>
                    </article>
                </div>
            </section>

            <section class="changes-panel" aria-label="Changed files and diffs">
                <h2 class="section-title">Files & Diffs</h2>
                <div v-if="changedFiles.length > 0" class="files-list">
                    <article v-for="file in visibleChangedFiles" :key="file.key" class="file-card">
                        <header class="file-head">
                            <p class="file-name mono">{{ file.filename }}</p>
                            <span class="chip status-pill" :class="fileStatusClass(file.status)">
                                {{ file.status }}
                            </span>
                        </header>

                        <p v-if="file.previousFilename" class="file-rename">
                            Renamed from <span class="mono">{{ file.previousFilename }}</span>
                        </p>

                        <div class="file-metrics">
                            <span class="summary-strong plus">+{{ file.additions }}</span>
                            <span class="summary-strong minus">-{{ file.deletions }}</span>
                            <span class="file-total">{{ file.changes }} total</span>
                        </div>

                        <template v-if="file.patch">
                            <pre class="diff-block mono"><code>
<span v-for="(line, index) in file.previewLines" :key="`${file.key}-${index}`" :class="diffLineClass(line)">{{ line || " " }}</span>
</code></pre>
                            <div v-if="file.hasMorePatch" class="diff-actions">
                                <button v-if="!file.reachedPatchRenderCap" type="button" class="diff-btn" @click="showMorePatchLines(file)">
                                    Load more lines
                                </button>
                                <p v-else class="section-note">
                                    Diff preview limited to {{ MAX_PATCH_LINES_PER_FILE }} lines for stability.
                                </p>
                            </div>
                        </template>
                        <p v-else class="section-note">Diff not available (binary or too large).</p>
                    </article>
                    <div v-if="canShowMoreFiles" class="files-actions">
                        <button type="button" class="diff-btn" @click="showMoreFiles">
                            Load more files
                        </button>
                    </div>
                </div>
                <p v-else class="section-note">No changed files data available.</p>
            </section>
        </article>

        <article v-else class="panel not-found">
            <h1 class="title">{{ notFoundTitle }}</h1>
            <p class="subtitle">{{ notFoundMessage }}</p>
            <RouterLink :to="notFoundLink" class="back-link inline">{{ notFoundLinkLabel }}</RouterLink>
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
    --accent-soft: #e0f2fe;
    --github-bg: #eef3ff;
    --github-ink: #304e9b;
    --gitlab-bg: #ffefe7;
    --gitlab-ink: #a14b21;
    --ok-bg: #e8f8ee;
    --ok-ink: #21693c;
    --shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.2);
    display: grid;
    gap: 14px;
    min-width: 0;
    overflow-x: hidden;
}

.pr-details-view > * {
    min-width: 0;
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
    min-width: 0;
}

.alert {
    border-radius: 12px;
    border: 1px solid;
    padding: 10px 12px;
    font-size: 0.9rem;
}

.alert.error {
    border-color: #f4c1c1;
    background: #fff1f1;
    color: #8f1f1f;
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
    overflow-wrap: anywhere;
    word-break: break-word;
}

.mono {
    font-family: "JetBrains Mono", "Fira Code", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.changes-panel {
    border: 1px dashed #bfd7ea;
    border-radius: 12px;
    background: #f9fcff;
    padding: 16px;
    min-width: 0;
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

.summary-grid {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    font-size: 1.05rem;
    font-weight: 800;
    color: #1d3552;
}

.summary-strong.plus {
    color: #21693c;
}

.summary-strong.minus {
    color: #8f1f1f;
}

.files-list {
    margin-top: 12px;
    display: grid;
    gap: 12px;
    min-width: 0;
}

.file-card {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    padding: 12px;
    min-width: 0;
}

.file-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}

.file-name {
    margin: 0;
    color: #1d3552;
    font-weight: 700;
    font-size: 0.92rem;
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.file-rename {
    margin: 8px 0 0;
    color: var(--ink-soft);
    font-size: 0.86rem;
}

.file-metrics {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-total {
    color: var(--ink-soft);
    font-size: 0.86rem;
    font-weight: 700;
}

.diff-block {
    margin: 10px 0 0;
    padding: 10px 0;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 0.75rem;
    line-height: 1.5;
    white-space: pre;
    overflow: auto;
    max-width: 100%;
}

.diff-actions {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.files-actions {
    display: flex;
    justify-content: center;
}

.diff-btn {
    border: 1px solid #9fd2ea;
    border-radius: 10px;
    background: #e8f6ff;
    color: #0f4f77;
    padding: 8px 12px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}

.diff-btn:hover {
    background: #d7efff;
    border-color: #84c2e2;
}

.diff-line {
    display: block;
    padding: 0 10px;
}

.diff-line.context {
    color: #dbe5f0;
}

.diff-line.added {
    color: #9cf0be;
    background: rgba(34, 197, 94, 0.18);
}

.diff-line.removed {
    color: #ffc8c8;
    background: rgba(239, 68, 68, 0.2);
}

.diff-line.meta {
    color: #93c5fd;
    background: rgba(59, 130, 246, 0.14);
}

.diff-line.hunk {
    color: #facc15;
    background: rgba(250, 204, 21, 0.12);
}

.not-found {
    justify-items: start;
}

.loading-panel {
    min-height: 200px;
    justify-items: center;
    align-content: center;
    text-align: center;
}

.loader {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border: 3px solid #dbe5f0;
    border-top-color: #0ea5e9;
    animation: spin 0.8s linear infinite;
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

@media (max-width: 520px) {
    .title {
        font-size: 1.45rem;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
