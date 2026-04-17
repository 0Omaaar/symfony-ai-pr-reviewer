<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import {
  getTeamDashboard,
  getTeamDashboardStats,
  getTeamDashboardActivity,
  getTeamDashboardPrDetail,
  refreshTeamDashboard,
  type PrSnapshot,
  type DashboardStats,
  type ActivityEvent,
} from "@/api/teamDashboard";
import { getSubscriptions } from "@/api/subscriptions";

const router = useRouter();

// State
const pullRequests = ref<PrSnapshot[]>([]);
const stats = ref<DashboardStats>({ totalOpen: 0, needsReview: 0, stale: 0, aiReviewed: 0, ciFailing: 0 });
const pagination = ref({ total: 0, page: 1, perPage: 25, totalPages: 1 });
const isLoading = ref(true);
const loadError = ref("");
const lastRefreshed = ref<Date | null>(null);
const isRefreshing = ref(false);
const hasSubscriptions = ref(true);

// Layout
type Layout = "table" | "kanban" | "focus";
const layout = ref<Layout>("table");

// Filters
const filterRepo = ref("");
const filterAuthor = ref("");
const filterStatus = ref("open");
const filterReviewStatus = ref("");
const filterAiStatus = ref("");
const filterCiStatus = ref("");
const filterStaleOnly = ref(false);
const filterMyPRsOnly = ref(false);
const filterNeedsAttention = ref(false);
const sortBy = ref("lastActivityAt");
const sortDir = ref("desc");
const searchQuery = ref("");

// Sidebar
const selectedPr = ref<PrSnapshot | null>(null);
const isSidebarOpen = ref(false);
const sidebarLoading = ref(false);

// Activity feed
const activityEvents = ref<ActivityEvent[]>([]);
const showActivity = ref(false);

// Auto-refresh
let statsInterval: ReturnType<typeof setInterval> | null = null;
let prevStatsHash = "";

// Computed
const filteredPRs = computed(() => {
  let items = pullRequests.value;
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    items = items.filter((pr) => pr.title.toLowerCase().includes(q) || pr.repoFullName.toLowerCase().includes(q));
  }
  if (filterMyPRsOnly.value) {
    // myPRs filter is handled by local filtering since we don't have current username easily
    // Stats-level filter; server already handles author filter
  }
  if (filterNeedsAttention.value) {
    items = items.filter((pr) => pr.needsMyAttention);
  }
  return items;
});

const kanbanColumns = computed(() => ({
  needsReview: filteredPRs.value.filter((pr) => pr.reviewStatus === "none" || pr.reviewStatus === "review_requested"),
  inReview: filteredPRs.value.filter((pr) => pr.reviewStatus === "review_requested" && pr.completedReviews.length > 0),
  changesRequested: filteredPRs.value.filter((pr) => pr.reviewStatus === "changes_requested"),
  approved: filteredPRs.value.filter((pr) => pr.reviewStatus === "approved"),
}));

const focusItems = computed(() => ({
  actionNeeded: filteredPRs.value.filter((pr) => pr.needsMyAttention),
  waitingOnOthers: filteredPRs.value.filter((pr) => !pr.needsMyAttention),
}));

const refreshAgoText = computed(() => {
  if (!lastRefreshed.value) return "Never";
  const seconds = Math.floor((Date.now() - lastRefreshed.value.getTime()) / 1000);
  if (seconds < 60) return `${seconds}s ago`;
  const minutes = Math.floor(seconds / 60);
  return `${minutes}m ago`;
});

// Data loading
async function loadDashboard() {
  isLoading.value = true;
  loadError.value = "";
  try {
    const params: Record<string, string> = {
      status: filterStatus.value,
      sortBy: sortBy.value,
      sortDir: sortDir.value,
      page: String(pagination.value.page),
      perPage: String(pagination.value.perPage),
    };
    if (filterRepo.value) params["repos[]"] = filterRepo.value;
    if (filterAuthor.value) params["authors[]"] = filterAuthor.value;
    if (filterReviewStatus.value) params.reviewStatus = filterReviewStatus.value;
    if (filterAiStatus.value) params.aiStatus = filterAiStatus.value;
    if (filterCiStatus.value) params.ciStatus = filterCiStatus.value;
    if (filterStaleOnly.value) params.stale = "true";

    const res = await getTeamDashboard(params);
    pullRequests.value = res.data.pullRequests;
    stats.value = res.data.stats;
    pagination.value = res.data.pagination;
    lastRefreshed.value = new Date();
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : "Failed to load dashboard";
  } finally {
    isLoading.value = false;
  }
}

async function pollStats() {
  try {
    const res = await getTeamDashboardStats();
    const hash = JSON.stringify(res.data);
    if (hash !== prevStatsHash) {
      prevStatsHash = hash;
      stats.value = res.data;
      // Counts changed — refetch full list
      await loadDashboard();
    }
  } catch { /* silent */ }
}

async function handleRefresh() {
  isRefreshing.value = true;
  try {
    await refreshTeamDashboard();
    await loadDashboard();
  } catch { /* silent */ }
  isRefreshing.value = false;
}

async function loadActivity() {
  try {
    const res = await getTeamDashboardActivity();
    activityEvents.value = res.data;
  } catch { /* silent */ }
}

async function openSidebar(pr: PrSnapshot) {
  selectedPr.value = pr;
  isSidebarOpen.value = true;
}

function closeSidebar() {
  isSidebarOpen.value = false;
  selectedPr.value = null;
}

function openOnGithub(url: string) {
  window.open(url, "_blank", "noopener");
}

function goToOnboarding() {
  router.push({ name: "repos" });
}

// Filter by clicking a stat
function applyStatFilter(filter: string) {
  // Reset filters
  filterReviewStatus.value = "";
  filterAiStatus.value = "";
  filterCiStatus.value = "";
  filterStaleOnly.value = false;
  filterNeedsAttention.value = false;

  switch (filter) {
    case "needsReview":
      filterReviewStatus.value = "review_requested";
      break;
    case "stale":
      filterStaleOnly.value = true;
      break;
    case "aiReviewed":
      filterAiStatus.value = "completed";
      break;
    case "ciFailing":
      filterCiStatus.value = "failure";
      break;
  }
  pagination.value.page = 1;
  void loadDashboard();
}

function clearFilters() {
  filterRepo.value = "";
  filterAuthor.value = "";
  filterStatus.value = "open";
  filterReviewStatus.value = "";
  filterAiStatus.value = "";
  filterCiStatus.value = "";
  filterStaleOnly.value = false;
  filterMyPRsOnly.value = false;
  filterNeedsAttention.value = false;
  searchQuery.value = "";
  sortBy.value = "lastActivityAt";
  sortDir.value = "desc";
  pagination.value.page = 1;
  void loadDashboard();
}

function goToPage(page: number) {
  pagination.value.page = page;
  void loadDashboard();
}

function relativeTime(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins}m`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h`;
  const days = Math.floor(hours / 24);
  return `${days}d`;
}

function reviewStatusBadge(status: string): { label: string; cls: string } {
  switch (status) {
    case "approved": return { label: "Approved", cls: "badge-approved" };
    case "changes_requested": return { label: "Changes", cls: "badge-changes" };
    case "review_requested": return { label: "Review", cls: "badge-review" };
    default: return { label: "None", cls: "badge-none" };
  }
}

function aiBadge(pr: PrSnapshot): { label: string; cls: string } {
  switch (pr.aiReviewStatus) {
    case "completed":
      return pr.aiIssueCount > 0
        ? { label: `${pr.aiIssueCount} issues`, cls: "badge-ai-issues" }
        : { label: "Clean", cls: "badge-ai-clean" };
    case "processing": return { label: "Running", cls: "badge-ai-pending" };
    case "pending": return { label: "Pending", cls: "badge-ai-pending" };
    default: return { label: "None", cls: "badge-none" };
  }
}

function ciBadge(status: string | null): { label: string; cls: string } {
  switch (status) {
    case "success": return { label: "Passing", cls: "badge-ci-pass" };
    case "failure": return { label: "Failing", cls: "badge-ci-fail" };
    case "pending": return { label: "Pending", cls: "badge-ci-pending" };
    default: return { label: "-", cls: "badge-none" };
  }
}

function eventTypeLabel(type: string): string {
  switch (type) {
    case "pr_merged": return "merged";
    case "pr_closed": return "closed";
    case "ai_review_completed": return "AI reviewed";
    case "ai_review_started": return "AI started";
    default: return "updated";
  }
}

// Watch filters
watch([filterRepo, filterAuthor, filterStatus, filterReviewStatus, filterAiStatus, filterCiStatus, filterStaleOnly, sortBy, sortDir], () => {
  pagination.value.page = 1;
  void loadDashboard();
});

onMounted(async () => {
  // Check if user has subscriptions
  try {
    const subs = await getSubscriptions();
    hasSubscriptions.value = subs.count > 0;
  } catch { hasSubscriptions.value = false; }

  await loadDashboard();
  await loadActivity();

  // Auto-refresh stats every 60s
  statsInterval = setInterval(() => void pollStats(), 60000);
});

onUnmounted(() => {
  if (statsInterval) clearInterval(statsInterval);
});
</script>

<template>
  <div class="team-dashboard">
    <!-- Empty state -->
    <div v-if="!isLoading && !hasSubscriptions" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.06 15.94 0 13.35 0c-1.46 0-2.67.6-3.55 1.55L9 3 8.2 1.55C7.32.6 6.11 0 4.65 0 2.06 0 0 2.06 0 4.65c0 .47.11.91.18 1.35H0v2h1l1 13h18l1-13h1V6z"/></svg>
      </div>
      <h2 class="empty-title">No repositories monitored yet</h2>
      <p class="empty-text">Activate your first branch to start seeing pull requests here.</p>
      <button class="btn-cta" @click="goToOnboarding">Activate your first branch</button>
    </div>

    <template v-else>
      <!-- Metrics bar -->
      <div class="metrics-bar">
        <button class="metric" :class="{ active: filterStatus === 'open' && !filterReviewStatus && !filterStaleOnly && !filterCiStatus && !filterAiStatus }" @click="clearFilters">
          <span class="metric-number">{{ stats.totalOpen }}</span>
          <span class="metric-label">Open</span>
        </button>
        <button class="metric" :class="{ active: filterReviewStatus === 'review_requested' }" @click="applyStatFilter('needsReview')">
          <span class="metric-number">{{ stats.needsReview }}</span>
          <span class="metric-label">Needs Review</span>
        </button>
        <button class="metric" :class="{ active: filterStaleOnly }" @click="applyStatFilter('stale')">
          <span class="metric-number">{{ stats.stale }}</span>
          <span class="metric-label">Stale</span>
        </button>
        <button class="metric" :class="{ active: filterAiStatus === 'completed' }" @click="applyStatFilter('aiReviewed')">
          <span class="metric-number">{{ stats.aiReviewed }}</span>
          <span class="metric-label">AI Reviewed</span>
        </button>
        <button class="metric" :class="{ active: filterCiStatus === 'failure' }" @click="applyStatFilter('ciFailing')">
          <span class="metric-number">{{ stats.ciFailing }}</span>
          <span class="metric-label">CI Failing</span>
        </button>
        <div class="metric-spacer"></div>
        <span class="last-refresh">{{ refreshAgoText }}</span>
        <button class="refresh-btn" :disabled="isRefreshing" @click="handleRefresh" title="Refresh data">
          <svg :class="{ spinning: isRefreshing }" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17.65 6.35A7.96 7.96 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
        </button>
      </div>

      <!-- Toolbar -->
      <div class="toolbar">
        <div class="toolbar-left">
          <!-- Layout switcher -->
          <div class="layout-switcher">
            <button :class="{ active: layout === 'table' }" @click="layout = 'table'" title="Table view">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M3 14h4v-4H3v4zm0 5h4v-4H3v4zM3 9h4V5H3v4zm5 5h13v-4H8v4zm0 5h13v-4H8v4zM8 5v4h13V5H8z"/></svg>
            </button>
            <button :class="{ active: layout === 'kanban' }" @click="layout = 'kanban'" title="Kanban view">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M10 18h5V5h-5v13zm-6 0h5V5H4v13zM16 5v13h5V5h-5z"/></svg>
            </button>
            <button :class="{ active: layout === 'focus' }" @click="layout = 'focus'" title="Focus view">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm-7 7H3v4c0 1.1.9 2 2 2h4v-2H5v-4zM5 5h4V3H5c-1.1 0-2 .9-2 2v4h2V5zm14-2h-4v2h4v4h2V5c0-1.1-.9-2-2-2zm0 16h-4v2h4c1.1 0 2-.9 2-2v-4h-2v4z"/></svg>
            </button>
          </div>

          <!-- Search -->
          <input v-model="searchQuery" class="toolbar-search" type="text" placeholder="Search PRs..." />

          <!-- Toggle filters -->
          <label class="toggle-filter">
            <input type="checkbox" v-model="filterNeedsAttention" />
            <span>Needs attention</span>
          </label>
        </div>

        <div class="toolbar-right">
          <select v-model="sortBy" class="toolbar-select">
            <option value="lastActivityAt">Last activity</option>
            <option value="openedAt">Opened</option>
            <option value="prNumber">PR number</option>
            <option value="commentCount">Comments</option>
          </select>
          <button class="sort-dir-btn" @click="sortDir = sortDir === 'desc' ? 'asc' : 'desc'" :title="sortDir === 'desc' ? 'Descending' : 'Ascending'">
            {{ sortDir === "desc" ? "&#9660;" : "&#9650;" }}
          </button>
          <button class="toolbar-btn" @click="showActivity = !showActivity">
            {{ showActivity ? "Hide" : "Activity" }}
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="loading-state">
        <div class="skeleton-row" v-for="i in 5" :key="i"></div>
      </div>

      <!-- Error -->
      <div v-else-if="loadError" class="error-state">{{ loadError }}</div>

      <!-- TABLE LAYOUT -->
      <template v-else-if="layout === 'table'">
        <div class="table-wrap">
          <table class="pr-table" v-if="filteredPRs.length > 0">
            <thead>
              <tr>
                <th>PR</th>
                <th>Repository</th>
                <th>Branch</th>
                <th>Author</th>
                <th>Status</th>
                <th>AI</th>
                <th>CI</th>
                <th>Reviewers</th>
                <th>Age</th>
                <th>Activity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pr in filteredPRs" :key="pr.id" class="pr-row" :class="{ 'is-stale': pr.isStale, 'needs-attention': pr.needsMyAttention }" @click="openSidebar(pr)">
                <td class="col-pr">
                  <span class="pr-number">#{{ pr.prNumber }}</span>
                  <span class="pr-title" :title="pr.title">{{ pr.title }}</span>
                </td>
                <td class="col-repo">{{ pr.repoFullName.split("/")[1] }}</td>
                <td class="col-branch">
                  <span class="branch-tag">{{ pr.sourceBranch }}</span>
                  <span class="branch-arrow">&rarr;</span>
                  <span class="branch-tag target">{{ pr.targetBranch }}</span>
                </td>
                <td class="col-author">
                  <img v-if="pr.authorAvatarUrl" :src="pr.authorAvatarUrl" class="avatar" :alt="pr.authorLogin" />
                  <span>{{ pr.authorLogin }}</span>
                </td>
                <td><span class="badge" :class="reviewStatusBadge(pr.reviewStatus).cls">{{ reviewStatusBadge(pr.reviewStatus).label }}</span></td>
                <td><span class="badge" :class="aiBadge(pr).cls">{{ aiBadge(pr).label }}</span></td>
                <td><span class="badge" :class="ciBadge(pr.ciStatus).cls">{{ ciBadge(pr.ciStatus).label }}</span></td>
                <td class="col-reviewers">
                  <div class="avatar-stack">
                    <img v-for="(rev, i) in pr.assignedReviewers.slice(0, 3)" :key="i" :src="rev.avatarUrl || ''" class="avatar-mini" :title="rev.login" />
                    <span v-if="pr.assignedReviewers.length > 3" class="avatar-more">+{{ pr.assignedReviewers.length - 3 }}</span>
                  </div>
                </td>
                <td class="col-age" :class="{ 'age-stale': pr.isStale }">{{ relativeTime(pr.openedAt) }}</td>
                <td class="col-activity">{{ relativeTime(pr.lastActivityAt) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-else class="no-results">No pull requests match your filters.</div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.totalPages > 1" class="pagination">
          <button class="page-btn" :disabled="pagination.page <= 1" @click="goToPage(pagination.page - 1)">Prev</button>
          <span class="page-info">{{ pagination.page }} / {{ pagination.totalPages }}</span>
          <button class="page-btn" :disabled="pagination.page >= pagination.totalPages" @click="goToPage(pagination.page + 1)">Next</button>
        </div>
      </template>

      <!-- KANBAN LAYOUT -->
      <template v-else-if="layout === 'kanban'">
        <div class="kanban-board">
          <div class="kanban-col" v-for="(col, key) in { 'Needs Review': kanbanColumns.needsReview, 'In Review': kanbanColumns.inReview, 'Changes Requested': kanbanColumns.changesRequested, 'Approved': kanbanColumns.approved }" :key="key">
            <h3 class="kanban-col-title">{{ key }} <span class="kanban-count">{{ col.length }}</span></h3>
            <div class="kanban-cards">
              <div v-for="pr in col" :key="pr.id" class="kanban-card" :class="{ 'is-stale': pr.isStale }" @click="openSidebar(pr)">
                <div class="kc-header">
                  <span class="kc-repo">{{ pr.repoFullName.split("/")[1] }}</span>
                  <span class="kc-number">#{{ pr.prNumber }}</span>
                </div>
                <p class="kc-title">{{ pr.title }}</p>
                <div class="kc-footer">
                  <img v-if="pr.authorAvatarUrl" :src="pr.authorAvatarUrl" class="avatar-mini" :alt="pr.authorLogin" />
                  <span class="badge-sm" :class="aiBadge(pr).cls">{{ aiBadge(pr).label }}</span>
                  <span class="badge-sm" :class="ciBadge(pr.ciStatus).cls">{{ ciBadge(pr.ciStatus).label }}</span>
                  <span class="kc-age" :class="{ 'age-stale': pr.isStale }">{{ relativeTime(pr.openedAt) }}</span>
                </div>
              </div>
              <div v-if="col.length === 0" class="kanban-empty">No PRs</div>
            </div>
          </div>
        </div>
      </template>

      <!-- FOCUS LAYOUT -->
      <template v-else-if="layout === 'focus'">
        <div class="focus-view">
          <div v-if="focusItems.actionNeeded.length === 0 && focusItems.waitingOnOthers.length === 0" class="focus-empty">
            <div class="focus-empty-icon">&#127881;</div>
            <h3>You're all caught up</h3>
            <p>No pull requests need your attention right now.</p>
          </div>

          <template v-else>
            <section v-if="focusItems.actionNeeded.length > 0" class="focus-section">
              <h3 class="focus-section-title">Action needed by you</h3>
              <div class="focus-cards">
                <div v-for="pr in focusItems.actionNeeded" :key="pr.id" class="focus-card attention" @click="openSidebar(pr)">
                  <div class="fc-top">
                    <span class="fc-repo">{{ pr.repoFullName }}</span>
                    <span class="fc-number">#{{ pr.prNumber }}</span>
                  </div>
                  <p class="fc-title">{{ pr.title }}</p>
                  <div class="fc-meta">
                    <img v-if="pr.authorAvatarUrl" :src="pr.authorAvatarUrl" class="avatar-mini" :alt="pr.authorLogin" />
                    <span>{{ pr.authorLogin }}</span>
                    <span class="fc-age">{{ relativeTime(pr.openedAt) }}</span>
                  </div>
                </div>
              </div>
            </section>

            <section v-if="focusItems.waitingOnOthers.length > 0" class="focus-section">
              <h3 class="focus-section-title">Waiting on others (your PRs)</h3>
              <div class="focus-cards">
                <div v-for="pr in focusItems.waitingOnOthers" :key="pr.id" class="focus-card" @click="openSidebar(pr)">
                  <div class="fc-top">
                    <span class="fc-repo">{{ pr.repoFullName }}</span>
                    <span class="fc-number">#{{ pr.prNumber }}</span>
                  </div>
                  <p class="fc-title">{{ pr.title }}</p>
                  <div class="fc-meta">
                    <span class="badge-sm" :class="reviewStatusBadge(pr.reviewStatus).cls">{{ reviewStatusBadge(pr.reviewStatus).label }}</span>
                    <span class="fc-age">{{ relativeTime(pr.openedAt) }}</span>
                  </div>
                </div>
              </div>
            </section>
          </template>
        </div>
      </template>

      <!-- Activity feed drawer -->
      <div v-if="showActivity" class="activity-drawer">
        <div class="activity-header">
          <h3>Recent Activity</h3>
          <button class="close-btn" @click="showActivity = false">&times;</button>
        </div>
        <div class="activity-list">
          <div v-for="(evt, i) in activityEvents.slice(0, 50)" :key="i" class="activity-item">
            <img v-if="evt.authorAvatarUrl" :src="evt.authorAvatarUrl" class="avatar-mini" :alt="evt.authorLogin" />
            <div class="activity-text">
              <span class="activity-action">PR #{{ evt.prNumber }} {{ eventTypeLabel(evt.type) }}</span>
              <span class="activity-repo">{{ evt.repoFullName }}</span>
            </div>
            <span class="activity-time">{{ relativeTime(evt.occurredAt) }}</span>
          </div>
          <div v-if="activityEvents.length === 0" class="activity-empty">No recent activity.</div>
        </div>
      </div>

      <!-- PR Detail Sidebar -->
      <Teleport to="body">
        <div v-if="isSidebarOpen && selectedPr" class="sidebar-overlay" @click.self="closeSidebar">
          <div class="sidebar-panel">
            <button class="sidebar-close" @click="closeSidebar">&times;</button>

            <div class="sidebar-header">
              <h2 class="sidebar-title">{{ selectedPr.title }}</h2>
              <div class="sidebar-meta">
                <span class="badge" :class="reviewStatusBadge(selectedPr.reviewStatus).cls">{{ reviewStatusBadge(selectedPr.reviewStatus).label }}</span>
                <span v-if="selectedPr.isDraft" class="badge badge-draft">Draft</span>
                <span class="sidebar-pr-num">#{{ selectedPr.prNumber }}</span>
              </div>
            </div>

            <div class="sidebar-info">
              <div class="si-row"><span class="si-label">Repository</span><span class="si-value">{{ selectedPr.repoFullName }}</span></div>
              <div class="si-row"><span class="si-label">Branch</span><span class="si-value">{{ selectedPr.sourceBranch }} &rarr; {{ selectedPr.targetBranch }}</span></div>
              <div class="si-row"><span class="si-label">Author</span><span class="si-value"><img v-if="selectedPr.authorAvatarUrl" :src="selectedPr.authorAvatarUrl" class="avatar-mini" /> {{ selectedPr.authorLogin }}</span></div>
              <div class="si-row"><span class="si-label">Opened</span><span class="si-value">{{ new Date(selectedPr.openedAt).toLocaleDateString() }}</span></div>
            </div>

            <!-- Description -->
            <div v-if="selectedPr.description" class="sidebar-section">
              <h4>Description</h4>
              <p class="sidebar-desc">{{ selectedPr.description }}</p>
            </div>

            <!-- AI Review -->
            <div class="sidebar-section">
              <h4>AI Review</h4>
              <span class="badge" :class="aiBadge(selectedPr).cls">{{ aiBadge(selectedPr).label }}</span>
              <p v-if="selectedPr.aiReviewSummary" class="sidebar-ai-summary">{{ selectedPr.aiReviewSummary }}</p>
            </div>

            <!-- Reviewers -->
            <div class="sidebar-section">
              <h4>Reviewers</h4>
              <div v-if="selectedPr.assignedReviewers.length > 0 || selectedPr.completedReviews.length > 0" class="reviewer-list">
                <div v-for="rev in selectedPr.completedReviews" :key="rev.login" class="reviewer-item">
                  <img v-if="rev.avatarUrl" :src="rev.avatarUrl" class="avatar-mini" />
                  <span>{{ rev.login }}</span>
                  <span class="badge-sm" :class="rev.state === 'APPROVED' ? 'badge-approved' : rev.state === 'CHANGES_REQUESTED' ? 'badge-changes' : 'badge-none'">{{ rev.state }}</span>
                </div>
                <div v-for="rev in selectedPr.assignedReviewers" :key="'a-' + rev.login" class="reviewer-item">
                  <img v-if="rev.avatarUrl" :src="rev.avatarUrl" class="avatar-mini" />
                  <span>{{ rev.login }}</span>
                  <span class="badge-sm badge-review">Requested</span>
                </div>
              </div>
              <p v-else class="sidebar-empty">No reviewers</p>
            </div>

            <!-- Stats -->
            <div class="sidebar-section">
              <h4>Stats</h4>
              <div class="stat-grid">
                <div class="stat-item"><span class="stat-num">{{ selectedPr.changedFiles }}</span><span class="stat-lbl">Files</span></div>
                <div class="stat-item"><span class="stat-num add">+{{ selectedPr.additions }}</span><span class="stat-lbl">Additions</span></div>
                <div class="stat-item"><span class="stat-num del">-{{ selectedPr.deletions }}</span><span class="stat-lbl">Deletions</span></div>
                <div class="stat-item"><span class="stat-num">{{ selectedPr.commentCount }}</span><span class="stat-lbl">Comments</span></div>
              </div>
            </div>

            <!-- Labels -->
            <div v-if="selectedPr.labels.length > 0" class="sidebar-section">
              <h4>Labels</h4>
              <div class="label-list">
                <span v-for="label in selectedPr.labels" :key="label" class="label-tag">{{ label }}</span>
              </div>
            </div>

            <!-- CI -->
            <div class="sidebar-section">
              <h4>CI Status</h4>
              <span class="badge" :class="ciBadge(selectedPr.ciStatus).cls">{{ ciBadge(selectedPr.ciStatus).label }}</span>
            </div>

            <div class="sidebar-actions">
              <button class="btn-primary" @click="openOnGithub(selectedPr.githubUrl)">Open on GitHub</button>
            </div>
          </div>
        </div>
      </Teleport>
    </template>
  </div>
</template>

<style scoped>
.team-dashboard {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 24px;
}

/* ─── Metrics bar ─────────────────────────────────── */
.metrics-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: var(--radius-card, 16px);
  border: 1px solid var(--line, #e2e8f0);
  background: var(--surface, #fff);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0,0,0,0.06));
  position: sticky;
  top: 0;
  z-index: 10;
}

.metric {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: inherit;
}

.metric:hover { background: var(--surface-soft, #f8fafc); }
.metric.active { background: var(--accent-light, #e0f4ff); border-color: var(--accent-mid, #7dccf0); }
.metric-number { font-size: 1.3rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.metric-label { font-size: 0.7rem; font-weight: 700; color: var(--ink-soft, #64748b); text-transform: uppercase; letter-spacing: 0.04em; }
.metric-spacer { flex: 1; }
.last-refresh { font-size: 0.74rem; color: var(--ink-faint, #94a3b8); font-weight: 600; }

.refresh-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--line-strong, #cbd5e1);
  background: var(--surface, #fff);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-soft, #64748b);
}
.refresh-btn:hover { border-color: var(--accent-mid, #7dccf0); color: var(--accent, #0d90c5); }
.refresh-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.spinning { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Toolbar ─────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 16px;
  border-radius: var(--radius-card, 16px);
  border: 1px solid var(--line, #e2e8f0);
  background: var(--surface, #fff);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0,0,0,0.06));
  flex-wrap: wrap;
}

.toolbar-left, .toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.layout-switcher {
  display: flex;
  border: 1px solid var(--line-strong, #cbd5e1);
  border-radius: 8px;
  overflow: hidden;
}

.layout-switcher button {
  padding: 6px 10px;
  border: none;
  background: var(--surface, #fff);
  cursor: pointer;
  color: var(--ink-soft, #64748b);
  display: flex;
  align-items: center;
}
.layout-switcher button:not(:last-child) { border-right: 1px solid var(--line-strong, #cbd5e1); }
.layout-switcher button.active { background: var(--accent-light, #e0f4ff); color: var(--accent, #0d90c5); }
.layout-switcher button:hover:not(.active) { background: var(--surface-soft, #f8fafc); }

.toolbar-search {
  padding: 6px 12px;
  font-size: 0.82rem;
  border: 1px solid var(--line-strong, #cbd5e1);
  border-radius: 8px;
  background: var(--surface, #fff);
  color: var(--ink-strong, #1e293b);
  width: 200px;
  font-family: inherit;
}
.toolbar-search:focus { outline: none; border-color: var(--accent, #0d90c5); }

.toggle-filter {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--ink-soft, #64748b);
  cursor: pointer;
}
.toggle-filter input { accent-color: var(--accent, #0d90c5); }

.toolbar-select {
  padding: 6px 10px;
  font-size: 0.78rem;
  border: 1px solid var(--line-strong, #cbd5e1);
  border-radius: 8px;
  background: var(--surface, #fff);
  font-family: inherit;
  color: var(--ink-body, #334155);
}

.sort-dir-btn {
  padding: 6px 8px;
  border: 1px solid var(--line-strong, #cbd5e1);
  border-radius: 8px;
  background: var(--surface, #fff);
  cursor: pointer;
  font-size: 0.7rem;
  color: var(--ink-soft, #64748b);
}

.toolbar-btn {
  padding: 6px 14px;
  font-size: 0.78rem;
  font-weight: 700;
  border: 1px solid var(--line-strong, #cbd5e1);
  border-radius: 8px;
  background: var(--surface, #fff);
  cursor: pointer;
  color: var(--ink-body, #334155);
  font-family: inherit;
}
.toolbar-btn:hover { border-color: var(--accent-mid, #7dccf0); }

/* ─── Loading / Error ─────────────────────────────── */
.loading-state { display: flex; flex-direction: column; gap: 8px; padding: 16px; }
.skeleton-row { height: 48px; border-radius: 8px; background: linear-gradient(90deg, var(--surface-soft, #f1f5f9) 25%, var(--surface-raised, #e2e8f0) 50%, var(--surface-soft, #f1f5f9) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.error-state { padding: 20px; color: var(--error); font-weight: 600; text-align: center; }

/* ─── Table ───────────────────────────────────────── */
.table-wrap {
  border-radius: var(--radius-card, 16px);
  border: 1px solid var(--line, #e2e8f0);
  background: var(--surface, #fff);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0,0,0,0.06));
  overflow-x: auto;
}

.pr-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.pr-table th { text-align: left; padding: 10px 12px; font-size: 0.72rem; font-weight: 700; color: var(--ink-faint, #94a3b8); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--line, #e2e8f0); white-space: nowrap; }
.pr-table td { padding: 10px 12px; border-bottom: 1px solid var(--line, #e2e8f0); vertical-align: middle; }
.pr-row { cursor: pointer; transition: background 0.12s ease; }
.pr-row:hover { background: var(--surface-soft, #f8fafc); }
.pr-row:last-child td { border-bottom: none; }
.pr-row.is-stale { background: var(--tile-warning-bg-2); }
.pr-row.needs-attention { border-left: 3px solid var(--accent, #0d90c5); }

.col-pr { max-width: 280px; }
.pr-number { font-weight: 700; color: var(--accent, #0d90c5); margin-right: 6px; white-space: nowrap; }
.pr-title { font-weight: 600; color: var(--ink-strong, #1e293b); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; max-width: 220px; vertical-align: bottom; }
.col-repo { font-weight: 600; color: var(--ink-body, #334155); white-space: nowrap; }
.col-branch { white-space: nowrap; }
.branch-tag { font-size: 0.74rem; padding: 2px 6px; border-radius: 4px; background: var(--surface-raised, #e2e8f0); color: var(--ink-body, #334155); font-family: ui-monospace, monospace; font-weight: 600; }
.branch-tag.target { background: var(--accent-light, #e0f4ff); color: var(--accent, #0d90c5); }
.branch-arrow { color: var(--ink-faint, #94a3b8); margin: 0 4px; }
.col-author { white-space: nowrap; }
.col-author span { font-weight: 600; }
.avatar { width: 20px; height: 20px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
.avatar-mini { width: 18px; height: 18px; border-radius: 50%; }
.col-reviewers { white-space: nowrap; }
.avatar-stack { display: flex; align-items: center; }
.avatar-stack .avatar-mini { margin-left: -4px; border: 2px solid var(--surface, #fff); }
.avatar-stack .avatar-mini:first-child { margin-left: 0; }
.avatar-more { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint, #94a3b8); margin-left: 4px; }
.col-age, .col-activity { font-weight: 600; color: var(--ink-soft, #64748b); white-space: nowrap; }
.age-stale { color: var(--warning); font-weight: 800; }

/* ─── Badges ──────────────────────────────────────── */
.badge, .badge-sm { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
.badge-sm { padding: 1px 6px; font-size: 0.66rem; }
.badge-approved { background: var(--badge-approved-bg); color: var(--badge-approved-text); }
.badge-changes { background: var(--badge-changes-bg); color: var(--badge-changes-text); }
.badge-review { background: var(--badge-review-bg); color: var(--badge-review-text); }
.badge-none { background: var(--surface-raised); color: var(--ink-faint); }
.badge-draft { background: var(--badge-draft-bg); color: var(--badge-draft-text); }
.badge-ai-clean { background: var(--badge-ai-clean-bg); color: var(--badge-ai-clean-text); }
.badge-ai-issues { background: var(--badge-ai-issues-bg); color: var(--badge-ai-issues-text); }
.badge-ai-pending { background: var(--badge-ai-pending-bg); color: var(--badge-ai-pending-text); }
.badge-ci-pass { background: var(--badge-ci-pass-bg); color: var(--badge-ci-pass-text); }
.badge-ci-fail { background: var(--badge-ci-fail-bg); color: var(--badge-ci-fail-text); }
.badge-ci-pending { background: var(--badge-ci-pending-bg); color: var(--badge-ci-pending-text); }

.no-results { padding: 40px 20px; text-align: center; color: var(--ink-faint, #94a3b8); font-weight: 600; }

/* ─── Pagination ──────────────────────────────────── */
.pagination { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 8px; }
.page-btn { padding: 6px 14px; border-radius: 8px; border: 1px solid var(--line-strong, #cbd5e1); background: var(--surface, #fff); font-weight: 700; font-size: 0.82rem; cursor: pointer; font-family: inherit; }
.page-btn:hover:not(:disabled) { border-color: var(--accent-mid, #7dccf0); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.82rem; font-weight: 600; color: var(--ink-soft, #64748b); }

/* ─── Kanban ──────────────────────────────────────── */
.kanban-board { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; min-height: 400px; }
.kanban-col { background: var(--surface-soft, #f8fafc); border-radius: 12px; border: 1px solid var(--line, #e2e8f0); padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.kanban-col-title { margin: 0 0 4px; font-size: 0.82rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.kanban-count { font-size: 0.74rem; font-weight: 700; color: var(--ink-faint, #94a3b8); }
.kanban-cards { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.kanban-card { padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line, #e2e8f0); background: var(--surface, #fff); cursor: pointer; transition: box-shadow 0.15s ease; }
.kanban-card:hover { box-shadow: var(--shadow-sm); }
.kanban-card.is-stale { border-color: var(--warning); }
.kc-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.kc-repo { font-size: 0.72rem; font-weight: 700; color: var(--ink-soft, #64748b); }
.kc-number { font-size: 0.72rem; font-weight: 700; color: var(--accent, #0d90c5); }
.kc-title { margin: 0 0 6px; font-size: 0.8rem; font-weight: 600; color: var(--ink-strong, #1e293b); line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.kc-footer { display: flex; align-items: center; gap: 6px; }
.kc-age { margin-left: auto; font-size: 0.68rem; font-weight: 600; color: var(--ink-faint, #94a3b8); }
.kanban-empty { text-align: center; padding: 20px; color: var(--ink-faint, #94a3b8); font-size: 0.8rem; }

/* ─── Focus ───────────────────────────────────────── */
.focus-view { display: flex; flex-direction: column; gap: 20px; }
.focus-empty { text-align: center; padding: 60px 20px; }
.focus-empty-icon { font-size: 3rem; margin-bottom: 10px; }
.focus-empty h3 { margin: 0 0 6px; font-size: 1.2rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.focus-empty p { margin: 0; color: var(--ink-soft, #64748b); }
.focus-section-title { margin: 0 0 10px; font-size: 0.92rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.focus-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 10px; }
.focus-card { padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line, #e2e8f0); background: var(--surface, #fff); cursor: pointer; transition: box-shadow 0.15s ease; }
.focus-card:hover { box-shadow: var(--shadow-md); }
.focus-card.attention { border-left: 4px solid var(--accent, #0d90c5); }
.fc-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
.fc-repo { font-size: 0.76rem; font-weight: 700; color: var(--ink-soft, #64748b); }
.fc-number { font-size: 0.76rem; font-weight: 700; color: var(--accent, #0d90c5); }
.fc-title { margin: 0 0 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink-strong, #1e293b); }
.fc-meta { display: flex; align-items: center; gap: 8px; font-size: 0.76rem; font-weight: 600; color: var(--ink-soft, #64748b); }
.fc-age { margin-left: auto; }

/* ─── Activity drawer ─────────────────────────────── */
.activity-drawer {
  position: fixed;
  top: 0;
  right: 0;
  width: 360px;
  height: 100vh;
  background: var(--surface, #fff);
  border-left: 1px solid var(--line, #e2e8f0);
  box-shadow: var(--shadow-lg);
  z-index: 100;
  display: flex;
  flex-direction: column;
}

.activity-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 18px;
  border-bottom: 1px solid var(--line, #e2e8f0);
}

.activity-header h3 { margin: 0; font-size: 0.95rem; font-weight: 800; }

.close-btn {
  background: none;
  border: none;
  font-size: 1.3rem;
  cursor: pointer;
  color: var(--ink-soft, #64748b);
  padding: 0;
  line-height: 1;
}

.activity-list { flex: 1; overflow-y: auto; padding: 12px 18px; }

.activity-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var(--line, #e2e8f0);
}

.activity-text { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.activity-action { font-size: 0.8rem; font-weight: 600; color: var(--ink-strong, #1e293b); }
.activity-repo { font-size: 0.72rem; color: var(--ink-faint, #94a3b8); }
.activity-time { font-size: 0.7rem; font-weight: 600; color: var(--ink-faint, #94a3b8); white-space: nowrap; }
.activity-empty { padding: 20px; text-align: center; color: var(--ink-faint, #94a3b8); }

/* ─── Sidebar ─────────────────────────────────────── */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: var(--overlay-scrim);
  z-index: 200;
  display: flex;
  justify-content: flex-end;
}

.sidebar-panel {
  width: 420px;
  max-width: 100vw;
  height: 100vh;
  background: var(--surface, #fff);
  overflow-y: auto;
  padding: 24px;
  position: relative;
}

.sidebar-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--ink-soft, #64748b);
  z-index: 1;
}

.sidebar-header { margin-bottom: 20px; }
.sidebar-title { margin: 0 0 8px; font-size: 1.1rem; font-weight: 800; color: var(--ink-strong, #1e293b); padding-right: 30px; }
.sidebar-meta { display: flex; align-items: center; gap: 8px; }
.sidebar-pr-num { font-size: 0.82rem; font-weight: 700; color: var(--ink-faint, #94a3b8); }

.sidebar-info { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
.si-row { display: flex; gap: 10px; }
.si-label { font-size: 0.78rem; font-weight: 700; color: var(--ink-faint, #94a3b8); min-width: 80px; }
.si-value { font-size: 0.82rem; font-weight: 600; color: var(--ink-strong, #1e293b); display: flex; align-items: center; gap: 6px; }

.sidebar-section { margin-bottom: 18px; }
.sidebar-section h4 { margin: 0 0 8px; font-size: 0.82rem; font-weight: 800; color: var(--ink-soft, #64748b); text-transform: uppercase; letter-spacing: 0.04em; }
.sidebar-desc { margin: 0; font-size: 0.82rem; color: var(--ink-body, #334155); line-height: 1.5; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
.sidebar-ai-summary { margin: 8px 0 0; font-size: 0.82rem; color: var(--ink-body, #334155); line-height: 1.5; }
.sidebar-empty { margin: 0; font-size: 0.82rem; color: var(--ink-faint, #94a3b8); }

.reviewer-list { display: flex; flex-direction: column; gap: 6px; }
.reviewer-item { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.stat-item { text-align: center; padding: 8px; border-radius: 8px; background: var(--surface-soft, #f8fafc); }
.stat-num { display: block; font-size: 1rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.stat-num.add { color: var(--success); }
.stat-num.del { color: var(--error); }
.stat-lbl { font-size: 0.68rem; font-weight: 600; color: var(--ink-faint, #94a3b8); text-transform: uppercase; }

.label-list { display: flex; flex-wrap: wrap; gap: 4px; }
.label-tag { padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; background: var(--surface-raised, #e2e8f0); color: var(--ink-body, #334155); }

.sidebar-actions { margin-top: 20px; }
.btn-primary {
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, var(--accent-mid), var(--accent));
  color: var(--accent-foreground);
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: transform 0.1s ease;
}
.btn-primary:hover { transform: translateY(-1px); }

/* ─── Empty state ─────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 80px 20px;
  border-radius: var(--radius-card, 16px);
  border: 1px solid var(--line, #e2e8f0);
  background: var(--surface, #fff);
}

.empty-icon { color: var(--ink-faint, #94a3b8); margin-bottom: 16px; }
.empty-title { margin: 0 0 8px; font-size: 1.3rem; font-weight: 800; color: var(--ink-strong, #1e293b); }
.empty-text { margin: 0 0 20px; color: var(--ink-soft, #64748b); }

.btn-cta {
  padding: 12px 24px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, var(--accent-mid), var(--accent));
  color: var(--accent-foreground);
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}
.btn-cta:hover { transform: translateY(-1px); }

/* ─── Responsive ──────────────────────────────────── */
@media (max-width: 1000px) {
  .kanban-board { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 700px) {
  .kanban-board { grid-template-columns: 1fr; }
  .metrics-bar { flex-wrap: wrap; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .toolbar-left, .toolbar-right { justify-content: flex-start; }
  .toolbar-search { width: 100%; }
  .sidebar-panel { width: 100vw; }
  .activity-drawer { width: 100vw; }
}
</style>
