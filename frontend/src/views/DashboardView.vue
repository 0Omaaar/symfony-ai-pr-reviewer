<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";

type DashboardSetup = {
  github_app_installed?: boolean;
  repositories_connected?: boolean;
};

type DashboardKpis = {
  repositories?: number;
  pull_requests_total?: number;
  pull_requests_open?: number;
  pull_requests_merged?: number;
  pull_requests_closed?: number;
};

type DashboardRecentPullRequest = {
  id?: number;
  repo_id?: number;
  repo_full_name?: string;
  number?: number;
  title?: string;
  status?: "open" | "merged" | "closed";
  updated_at?: string | null;
};

type DashboardTopRepository = {
  repo_id?: number;
  full_name?: string;
  open_pull_requests?: number;
  total_pull_requests?: number;
};

type DashboardResponse = {
  ok?: boolean;
  generated_at?: string;
  setup?: DashboardSetup;
  kpis?: DashboardKpis;
  recent_pull_requests?: DashboardRecentPullRequest[];
  top_repositories?: DashboardTopRepository[];
};

type UiRecentPullRequest = {
  id: number;
  repoId: number;
  repoFullName: string;
  number: number;
  title: string;
  status: "open" | "merged" | "closed";
  updatedAt: string | null;
};

type UiTopRepository = {
  repoId: number;
  fullName: string;
  openPullRequests: number;
  totalPullRequests: number;
};

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const DASHBOARD_CACHE_KEY = "dashboard.payload.v1";
const isLoading = ref(false);
const loadError = ref("");
const generatedAt = ref<string | null>(null);
const setup = ref({
  githubAppInstalled: false,
  repositoriesConnected: false,
});
const kpis = ref({
  repositories: 0,
  pullRequestsTotal: 0,
  pullRequestsOpen: 0,
  pullRequestsMerged: 0,
  pullRequestsClosed: 0,
});
const recentPullRequests = ref<UiRecentPullRequest[]>([]);
const topRepositories = ref<UiTopRepository[]>([]);

function applyDashboardData(data: DashboardResponse) {
  generatedAt.value = typeof data.generated_at === "string" ? data.generated_at : null;

  setup.value = {
    githubAppInstalled: Boolean(data.setup?.github_app_installed),
    repositoriesConnected: Boolean(data.setup?.repositories_connected),
  };

  kpis.value = {
    repositories: typeof data.kpis?.repositories === "number" ? data.kpis.repositories : 0,
    pullRequestsTotal: typeof data.kpis?.pull_requests_total === "number" ? data.kpis.pull_requests_total : 0,
    pullRequestsOpen: typeof data.kpis?.pull_requests_open === "number" ? data.kpis.pull_requests_open : 0,
    pullRequestsMerged: typeof data.kpis?.pull_requests_merged === "number" ? data.kpis.pull_requests_merged : 0,
    pullRequestsClosed: typeof data.kpis?.pull_requests_closed === "number" ? data.kpis.pull_requests_closed : 0,
  };

  recentPullRequests.value = Array.isArray(data.recent_pull_requests)
    ? data.recent_pull_requests.map(mapRecentPullRequest).filter((item): item is UiRecentPullRequest => item !== null)
    : [];

  topRepositories.value = Array.isArray(data.top_repositories)
    ? data.top_repositories.map(mapTopRepository).filter((item): item is UiTopRepository => item !== null)
    : [];
}

function mapRecentPullRequest(item: DashboardRecentPullRequest): UiRecentPullRequest | null {
  if (typeof item.id !== "number" || typeof item.repo_id !== "number" || typeof item.number !== "number") {
    return null;
  }

  const status = item.status === "merged" || item.status === "closed" ? item.status : "open";

  return {
    id: item.id,
    repoId: item.repo_id,
    repoFullName: typeof item.repo_full_name === "string" && item.repo_full_name !== "" ? item.repo_full_name : "Unknown repository",
    number: item.number,
    title: typeof item.title === "string" && item.title !== "" ? item.title : "(No title)",
    status,
    updatedAt: typeof item.updated_at === "string" ? item.updated_at : null,
  };
}

function mapTopRepository(item: DashboardTopRepository): UiTopRepository | null {
  if (typeof item.repo_id !== "number") {
    return null;
  }

  return {
    repoId: item.repo_id,
    fullName: typeof item.full_name === "string" && item.full_name !== "" ? item.full_name : "Unknown repository",
    openPullRequests: typeof item.open_pull_requests === "number" ? item.open_pull_requests : 0,
    totalPullRequests: typeof item.total_pull_requests === "number" ? item.total_pull_requests : 0,
  };
}

async function loadDashboard() {
  let hasCachedPayload = false;
  const cachedPayload = sessionStorage.getItem(DASHBOARD_CACHE_KEY);
  if (cachedPayload) {
    try {
      const cachedData = JSON.parse(cachedPayload) as DashboardResponse;
      applyDashboardData(cachedData);
      hasCachedPayload = true;
    } catch {
      sessionStorage.removeItem(DASHBOARD_CACHE_KEY);
    }
  }

  isLoading.value = !hasCachedPayload;
  loadError.value = "";

  try {
    const response = await fetch(`${apiBaseUrl}/api/dashboard`, {
      credentials: "include",
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch dashboard (${response.status})`);
    }

    const data = (await response.json()) as DashboardResponse;
    applyDashboardData(data);
    sessionStorage.setItem(DASHBOARD_CACHE_KEY, JSON.stringify(data));
  } catch (error) {
    if (!hasCachedPayload) {
      loadError.value = error instanceof Error ? error.message : "Failed to load dashboard.";
    }
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  void loadDashboard();
});

const setupCompletion = computed(() => {
  let completed = 0;
  if (setup.value.githubAppInstalled) completed += 1;
  if (setup.value.repositoriesConnected) completed += 1;
  return Math.round((completed / 2) * 100);
});

const primaryAction = computed(() => {
  if (!setup.value.githubAppInstalled) {
    return {
      label: "Install GitHub App",
      href: `${apiBaseUrl}/connect/github/app/install`,
      external: true,
    };
  }

  if (!setup.value.repositoriesConnected) {
    return {
      label: "Connect repositories",
      href: `${apiBaseUrl}/connect/github/app/install`,
      external: true,
    };
  }

  return {
    label: "Explore repositories",
    to: { name: "repos" },
    external: false,
  };
});

function formatDate(iso: string | null) {
  if (!iso) return "Unknown";
  return new Date(iso).toLocaleString("en-US", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

function prStatusClass(status: "open" | "merged" | "closed") {
  if (status === "merged") return "is-merged";
  if (status === "closed") return "is-closed";
  return "is-open";
}
</script>

<template>
  <section class="dashboard-view">
    <header class="hero">
      <div class="hero-copy">
        <p class="eyebrow">Workspace Overview</p>
        <h1 class="title">Delivery Dashboard</h1>
        <p class="subtitle">
          Track repository activity, pull request flow, and setup status from one operational view.
        </p>
      </div>

      <div class="hero-actions">
        <a
          v-if="primaryAction.external"
          :href="primaryAction.href"
          class="action-btn primary"
        >
          {{ primaryAction.label }}
        </a>
        <RouterLink v-else :to="primaryAction.to" class="action-btn primary">
          {{ primaryAction.label }}
        </RouterLink>

        <RouterLink :to="{ name: 'repos' }" class="action-btn secondary">Go to repositories</RouterLink>
      </div>
    </header>

    <div v-if="loadError" class="alert error" role="alert">
      {{ loadError }}
    </div>

    <article v-if="isLoading" class="panel loading-panel" role="status" aria-live="polite">
      <span class="loader" aria-hidden="true"></span>
      <p class="subtitle">Loading dashboard data...</p>
    </article>

    <template v-else>
      <section class="grid two-cols">
        <article class="panel setup-panel">
          <div class="section-head">
            <h2 class="section-title">Setup Progress</h2>
            <span class="progress-pill">{{ setupCompletion }}% complete</span>
          </div>

          <div class="progress-track" aria-hidden="true">
            <span class="progress-fill" :style="{ width: `${setupCompletion}%` }"></span>
          </div>

          <ul class="checklist">
            <li class="check-item">
              <span class="check-state" :class="setup.githubAppInstalled ? 'done' : 'todo'">
                {{ setup.githubAppInstalled ? "Done" : "Pending" }}
              </span>
              <div>
                <p class="check-title">GitHub App Installation</p>
                <p class="check-desc">Required to sync repositories and pull requests.</p>
              </div>
            </li>
            <li class="check-item">
              <span class="check-state" :class="setup.repositoriesConnected ? 'done' : 'todo'">
                {{ setup.repositoriesConnected ? "Done" : "Pending" }}
              </span>
              <div>
                <p class="check-title">Repository Sync</p>
                <p class="check-desc">At least one repository must be accessible for PR monitoring.</p>
              </div>
            </li>
          </ul>
        </article>

        <article class="panel kpi-panel">
          <h2 class="section-title">Key Metrics</h2>
          <div class="kpi-grid">
            <div class="kpi-card">
              <p class="kpi-label">Repositories</p>
              <p class="kpi-value">{{ kpis.repositories }}</p>
            </div>
            <div class="kpi-card">
              <p class="kpi-label">Total PRs</p>
              <p class="kpi-value">{{ kpis.pullRequestsTotal }}</p>
            </div>
            <div class="kpi-card">
              <p class="kpi-label">Open PRs</p>
              <p class="kpi-value">{{ kpis.pullRequestsOpen }}</p>
            </div>
            <div class="kpi-card">
              <p class="kpi-label">Merged PRs</p>
              <p class="kpi-value">{{ kpis.pullRequestsMerged }}</p>
            </div>
            <div class="kpi-card">
              <p class="kpi-label">Closed PRs</p>
              <p class="kpi-value">{{ kpis.pullRequestsClosed }}</p>
            </div>
          </div>
        </article>
      </section>

      <section class="grid two-cols">
        <article class="panel">
          <div class="section-head">
            <h2 class="section-title">Recent Pull Requests</h2>
            <span class="section-note">Latest 12 updates</span>
          </div>

          <ul v-if="recentPullRequests.length > 0" class="pr-list">
            <li v-for="pr in recentPullRequests" :key="pr.id" class="pr-item">
              <div class="pr-main">
                <RouterLink :to="{ name: 'pr-details', params: { id: pr.id }, query: { repoId: String(pr.repoId) } }" class="pr-link">
                  #{{ pr.number }} {{ pr.title }}
                </RouterLink>
                <p class="pr-repo">{{ pr.repoFullName }}</p>
              </div>
              <div class="pr-meta">
                <span class="status-pill" :class="prStatusClass(pr.status)">{{ pr.status }}</span>
                <span class="pr-updated">{{ formatDate(pr.updatedAt) }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="empty-copy">No pull requests found yet.</p>
        </article>

        <article class="panel">
          <div class="section-head">
            <h2 class="section-title">Repositories To Review</h2>
            <span class="section-note">Prioritized by open PRs</span>
          </div>

          <ul v-if="topRepositories.length > 0" class="repo-list">
            <li v-for="repo in topRepositories" :key="repo.repoId" class="repo-item">
              <div>
                <RouterLink :to="{ name: 'repo-details', params: { id: repo.repoId } }" class="repo-link">
                  {{ repo.fullName }}
                </RouterLink>
                <p class="repo-meta">{{ repo.totalPullRequests }} total pull requests</p>
              </div>
              <span class="open-pill">{{ repo.openPullRequests }} open</span>
            </li>
          </ul>
          <p v-else class="empty-copy">No repositories available yet.</p>
        </article>
      </section>

      <section class="panel quick-actions">
        <div class="section-head">
          <h2 class="section-title">Quick Navigation</h2>
          <span class="section-note" v-if="generatedAt">Updated {{ formatDate(generatedAt) }}</span>
        </div>

        <div class="quick-grid">
          <RouterLink :to="{ name: 'repos' }" class="quick-card">
            <p class="quick-title">Repositories Hub</p>
            <p class="quick-desc">Browse repos, branches, and pull request lists.</p>
          </RouterLink>
          <a class="quick-card" :href="`${apiBaseUrl}/connect/github/app/install`">
            <p class="quick-title">GitHub App Setup</p>
            <p class="quick-desc">Manage installation scope and repository access.</p>
          </a>
          <a class="quick-card" href="https://docs.github.com/en/apps" target="_blank" rel="noopener noreferrer">
            <p class="quick-title">GitHub App Docs</p>
            <p class="quick-desc">Reference docs to troubleshoot permissions and events.</p>
          </a>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.dashboard-view {
  display: grid;
  gap: 16px;
  max-width: 1200px;
  margin: 0 auto;
  padding-bottom: 24px;
}

/* ─── Hero ───────────────────────────────────────────────────── */
.hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: linear-gradient(135deg, #ffffff 0%, #f4f9ff 100%);
  padding: 20px 22px;
  box-shadow: var(--shadow-card);
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
  font-size: 1.7rem;
  line-height: 1.15;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.subtitle {
  margin: 8px 0 0;
  color: var(--ink-soft);
  max-width: 60ch;
  font-size: 0.9rem;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
  padding-top: 4px;
}

.action-btn {
  border-radius: var(--radius-inner);
  padding: 9px 16px;
  border: 1px solid transparent;
  font-weight: 700;
  text-decoration: none;
  font-size: 0.85rem;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  white-space: nowrap;
}

.action-btn.primary {
  background: var(--accent);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(13,126,164,0.3);
}

.action-btn.primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(13,126,164,0.45);
}

.action-btn.secondary {
  background: var(--accent-light);
  color: var(--accent-hover);
  border-color: var(--accent-mid);
}

.action-btn.secondary:hover {
  transform: translateY(-1px);
}

/* ─── Alert ──────────────────────────────────────────────────── */
.alert {
  border-radius: var(--radius-inner);
  border: 1px solid #fca5a5;
  background: #fff1f2;
  color: #991b1b;
  padding: 11px 14px;
  font-size: 0.88rem;
}

/* ─── Panel ──────────────────────────────────────────────────── */
.panel {
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  padding: 18px 20px;
}

.loading-panel {
  min-height: 200px;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 12px;
  color: var(--ink-soft);
}

.loader {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 3px solid var(--line);
  border-top-color: var(--accent);
  animation: spin 0.75s linear infinite;
}

/* ─── Grids ──────────────────────────────────────────────────── */
.grid { display: grid; gap: 16px; }
.two-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }

/* ─── Section head ───────────────────────────────────────────── */
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 14px;
}

.section-title {
  margin: 0;
  color: var(--ink-strong);
  font-size: 0.95rem;
  font-weight: 800;
}

.section-note {
  color: var(--ink-faint);
  font-size: 0.78rem;
  font-weight: 600;
}

/* ─── Setup Panel ────────────────────────────────────────────── */
.progress-pill {
  border-radius: var(--radius-pill);
  padding: 3px 10px;
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--accent-hover);
  background: var(--accent-light);
  border: 1px solid var(--accent-mid);
}

.progress-track {
  border-radius: var(--radius-pill);
  height: 6px;
  background: var(--surface-raised);
  overflow: hidden;
}

.progress-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--accent), #0a6b8f);
  border-radius: var(--radius-pill);
  transition: width 0.5s ease;
}

.checklist {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 8px;
}

.check-item {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
  align-items: start;
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  background: var(--surface-soft);
  padding: 12px;
}

.check-state {
  border-radius: var(--radius-pill);
  padding: 3px 9px;
  font-size: 0.7rem;
  font-weight: 800;
  white-space: nowrap;
  letter-spacing: 0.03em;
}

.check-state.done {
  background: var(--merged-bg);
  color: var(--merged-ink);
  border: 1px solid var(--merged-line);
}

.check-state.todo {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fcd34d;
}

.check-title {
  margin: 0;
  color: var(--ink-strong);
  font-weight: 700;
  font-size: 0.88rem;
}

.check-desc {
  margin: 3px 0 0;
  color: var(--ink-soft);
  font-size: 0.82rem;
  line-height: 1.5;
}

/* ─── KPI Panel ──────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.kpi-card {
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  padding: 14px;
  background: var(--surface-soft);
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--accent), #57c3e8);
  border-radius: var(--radius-inner) var(--radius-inner) 0 0;
}

.kpi-label {
  margin: 0;
  color: var(--ink-soft);
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  font-weight: 700;
}

.kpi-value {
  margin: 8px 0 0;
  color: var(--ink-strong);
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
}

/* ─── PR list ────────────────────────────────────────────────── */
.pr-list, .repo-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
}

.pr-item, .repo-item {
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  background: var(--surface);
  padding: 11px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pr-item:hover, .repo-item:hover {
  border-color: var(--accent-mid);
  box-shadow: 0 2px 8px rgba(13,126,164,0.08);
}

.pr-main, .repo-main { min-width: 0; flex: 1; }

.pr-link, .repo-link {
  color: var(--ink-strong);
  text-decoration: none;
  font-weight: 700;
  font-size: 0.88rem;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pr-link:hover, .repo-link:hover { color: var(--accent); }

.pr-repo, .repo-meta {
  margin: 2px 0 0;
  color: var(--ink-faint);
  font-size: 0.78rem;
}

.pr-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
  flex-shrink: 0;
}

.pr-updated {
  color: var(--ink-faint);
  font-size: 0.76rem;
  white-space: nowrap;
}

.open-pill {
  border-radius: var(--radius-pill);
  border: 1px solid var(--open-line);
  background: var(--open-bg);
  color: var(--open-ink);
  padding: 3px 10px;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}

/* ─── Quick Nav ──────────────────────────────────────────────── */
.quick-actions { display: grid; gap: 12px; }

.quick-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.quick-card {
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  text-decoration: none;
  background: var(--surface);
  padding: 14px 16px;
  transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
  display: block;
}

.quick-card:hover {
  transform: translateY(-2px);
  border-color: var(--accent-mid);
  box-shadow: var(--shadow-hover);
}

.quick-title {
  margin: 0;
  color: var(--ink-strong);
  font-weight: 700;
  font-size: 0.88rem;
}

.quick-desc {
  margin: 5px 0 0;
  color: var(--ink-soft);
  font-size: 0.82rem;
  line-height: 1.5;
}

.empty-copy {
  color: var(--ink-faint);
  font-size: 0.88rem;
  margin: 0;
  padding: 16px 0 4px;
  text-align: center;
}

/* ─── Responsive ─────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .two-cols { grid-template-columns: 1fr; }
  .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
  .hero { flex-direction: column; }
  .hero-actions { justify-content: flex-start; }
  .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pr-item, .repo-item { flex-direction: column; align-items: flex-start; }
  .pr-meta { flex-direction: row; align-items: center; }
  .quick-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
