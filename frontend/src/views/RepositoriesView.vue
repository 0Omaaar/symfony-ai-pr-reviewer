<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import type { Repository } from "@/types/repository";
import { useRouter } from "vue-router";

const search = ref("");
const router = useRouter();
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const repos = ref<Repository[]>([]);
const isLoading = ref(false);
const fetchError = ref("");

function goToRepo(id: number) {
  router.push({ name: "repo-details", params: { id } });
}

type GithubRepositoryApiItem = {
  id?: number;
  name?: string;
  full_name?: string;
};

type GithubRepositoriesApiResponse = {
  ok?: boolean;
  repositories?: GithubRepositoryApiItem[];
};

function mapApiRepository(item: GithubRepositoryApiItem): Repository | null {
  if (typeof item.id !== "number") return null;
  const fullName = typeof item.full_name === "string" && item.full_name !== "" ? item.full_name : item.name;
  if (typeof fullName !== "string" || fullName === "") return null;

  return {
    id: item.id,
    provider: "github",
    fullName,
  };
}

const userRepos = async (): Promise<GithubRepositoriesApiResponse> => {
  const res = await fetch(`${apiBaseUrl}/api/github/repositories`, {
    credentials: "include",
  });

  if (!res.ok) {
    throw new Error(`Failed to fetch repositories (${res.status})`);
  }

  return (await res.json()) as GithubRepositoriesApiResponse;
};

async function loadUserRepos() {
  isLoading.value = true;
  fetchError.value = "";

  try {
    const data = await userRepos();
    if (!Array.isArray(data.repositories)) {
      repos.value = [];
      return;
    }

    const mapped = data.repositories
      .map(mapApiRepository)
      .filter((item): item is Repository => item !== null);

    repos.value = mapped;
  } catch (error) {
    repos.value = [];
    fetchError.value = error instanceof Error ? error.message : "Failed to fetch repositories.";
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  void loadUserRepos();
});

const filteredRepos = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return repos.value;
  return repos.value.filter((r: Repository) =>
    r.fullName.toLowerCase().includes(q)
  );
});

const pageSize = 8;
const currentPage = ref(1);

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredRepos.value.length / pageSize))
);

const paginatedRepos = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return filteredRepos.value.slice(start, start + pageSize);
});

const pageStart = computed(() => {
  if (filteredRepos.value.length === 0) return 0;
  return (currentPage.value - 1) * pageSize + 1;
});

const pageEnd = computed(() =>
  Math.min(currentPage.value * pageSize, filteredRepos.value.length)
);

const pageNumbers = computed(() =>
  Array.from({ length: totalPages.value }, (_, i) => i + 1)
);

watch(search, () => {
  currentPage.value = 1;
});

watch(filteredRepos, () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value;
  }
});

function goToPage(page: number) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
}

const resultsLabel = computed(() => {
  const count = filteredRepos.value.length;
  return `${count} repositor${count === 1 ? "y" : "ies"}`;
});

function providerLabel(provider: Repository["provider"]) {
  if (provider === "github") return "GitHub";
  if (provider === "gitlab") return "GitLab";
  return provider;
}

function providerClass(provider: Repository["provider"]) {
  if (provider === "github") return "is-github";
  if (provider === "gitlab") return "is-gitlab";
  return "is-unknown";
}
</script>

<template>
  <section class="repos-view">
    <header class="page-head">
      <div class="head-copy">
        <h1 class="title">Repositories</h1>
        <p class="subtitle">
          Track connected source repositories from one place.
        </p>
      </div>

      <div class="controls">
        <label class="search-wrap" for="repo-search">
          <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path
              d="M10.5 3a7.5 7.5 0 015.89 12.14l4.24 4.25a1 1 0 01-1.41 1.41l-4.25-4.24A7.5 7.5 0 1110.5 3zm0 2a5.5 5.5 0 100 11 5.5 5.5 0 000-11z"
            />
          </svg>
          <input
            id="repo-search"
            v-model="search"
            class="search"
            placeholder="Search repositories"
          />
        </label>
        <p class="results">{{ resultsLabel }}</p>
      </div>
    </header>

    <div v-if="fetchError" class="alert error" role="alert">
      {{ fetchError }}
    </div>

    <div v-if="isLoading" class="loader-shell" role="status" aria-live="polite">
      <span class="loader" aria-hidden="true"></span>
      <p>Loading repositories...</p>
    </div>

    <div v-else class="repos-grid-wrap">
      <div v-if="paginatedRepos.length > 0" class="repos-grid">
        <button
          v-for="repo in paginatedRepos"
          :key="repo.id"
          class="repo-card"
          @click="goToRepo(repo.id)"
        >
          <div class="repo-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.06 15.94 0 13.35 0c-1.46 0-2.67.6-3.55 1.55L9 3 8.2 1.55C7.32.6 6.11 0 4.65 0 2.06 0 0 2.06 0 4.65c0 .47.11.91.18 1.35H0v2h1l1 13h18l1-13h1V6zm-7.5 0h-3l.93-1.04c.5-.56 1.2-.96 2.07-.96 1.06 0 1.96.8 2.1 1.82L13.53 6h-.84 .81z"/></svg>
          </div>
          <div class="repo-card-body">
            <span class="repo-card-name">{{ repo.fullName }}</span>
            <span class="chip provider-chip" :class="providerClass(repo.provider)">
              {{ providerLabel(repo.provider) }}
            </span>
          </div>
          <span class="repo-card-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
          </span>
        </button>
      </div>

      <div v-else class="empty">
        <div class="empty-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        </div>
        <p class="empty-title">No repositories found</p>
        <p class="empty-hint">{{ search ? 'Try a different search term.' : 'Connect a GitHub installation to get started.' }}</p>
      </div>
    </div>

    <footer v-if="!isLoading && filteredRepos.length > 0" class="pagination">
      <p class="page-summary">
        Showing {{ pageStart }}-{{ pageEnd }} of {{ filteredRepos.length }}
      </p>

      <div class="page-controls">
        <button
          type="button"
          class="page-btn"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          Prev
        </button>

        <button
          v-for="page in pageNumbers"
          :key="page"
          type="button"
          class="page-btn"
          :class="{ active: page === currentPage }"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>

        <button
          type="button"
          class="page-btn"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
      </div>
    </footer>
  </section>
</template>

<style scoped>
.repos-view {
  display: grid;
  gap: 16px;
  max-width: 1200px;
  margin: 0 auto;
  padding-bottom: 24px;
}

/* ─── Page header ────────────────────────────────────────────── */
.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 22px;
  border-radius: var(--radius-card);
  border: 1px solid var(--line);
  background: linear-gradient(135deg, #ffffff 0%, #f4f9ff 100%);
  box-shadow: var(--shadow-card);
}

.head-copy { min-width: 0; }

.title {
  margin: 0;
  font-size: 1.7rem;
  line-height: 1.15;
  letter-spacing: -0.02em;
  color: var(--ink-strong);
  font-weight: 800;
}

.subtitle {
  margin: 6px 0 0;
  color: var(--ink-soft);
  font-size: 0.88rem;
}

.controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.search-wrap {
  position: relative;
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 11px;
  width: 16px;
  height: 16px;
  transform: translateY(-50%);
  fill: var(--ink-soft);
  pointer-events: none;
}

.search {
  width: 260px;
  padding: 9px 12px 9px 34px;
  border: 1px solid var(--line-strong);
  border-radius: var(--radius-inner);
  color: var(--ink-strong);
  background: #fff;
  font-size: 0.88rem;
  font-family: var(--font-sans);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.search::placeholder { color: var(--ink-faint); }

.search:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(13,126,164,0.12);
}

.results {
  margin: 0;
  font-size: 0.82rem;
  color: var(--ink-faint);
  font-weight: 600;
  white-space: nowrap;
}

/* ─── Alerts ─────────────────────────────────────────────────── */
.alert.error {
  border-radius: var(--radius-inner);
  border: 1px solid #fca5a5;
  background: #fff1f2;
  color: #991b1b;
  padding: 11px 14px;
  font-size: 0.88rem;
}

/* ─── Loading ────────────────────────────────────────────────── */
.loader-shell {
  min-height: 280px;
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: var(--surface);
  box-shadow: var(--shadow-card);
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

/* ─── Card grid ──────────────────────────────────────────────── */
.repos-grid-wrap {
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

.repos-grid {
  display: grid;
  gap: 0;
}

.repo-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border: none;
  border-bottom: 1px solid var(--line);
  background: transparent;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s ease;
  width: 100%;
  font-family: var(--font-sans);
}

.repo-card:last-child { border-bottom: none; }

.repo-card:hover {
  background: var(--surface-soft);
}

.repo-card-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--accent-light);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--accent-mid);
}

.repo-card-body {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.repo-card-name {
  color: var(--ink-strong);
  font-weight: 700;
  font-size: 0.88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}

.repo-card-arrow {
  color: var(--ink-faint);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  transition: transform 0.15s ease, color 0.15s ease;
}

.repo-card:hover .repo-card-arrow {
  color: var(--accent);
  transform: translateX(2px);
}

/* ─── Provider chips ─────────────────────────────────────────── */
.chip {
  display: inline-flex;
  align-items: center;
  border-radius: var(--radius-pill);
  padding: 3px 9px;
  border: 1px solid transparent;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  flex-shrink: 0;
}

.provider-chip.is-github { background: #eef3ff; color: #304e9b; border-color: #c7d3f8; }
.provider-chip.is-gitlab { background: #fff1e6; color: #9a4418; border-color: #fdc9a5; }
.provider-chip.is-unknown { background: var(--surface-raised); color: var(--ink-soft); border-color: var(--line); }

/* ─── Empty state ────────────────────────────────────────────── */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 24px;
  text-align: center;
}

.empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--surface-raised);
  border: 1px solid var(--line);
  color: var(--ink-faint);
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  margin: 0;
  font-weight: 800;
  color: var(--ink-strong);
  font-size: 1rem;
}

.empty-hint {
  margin: 0;
  font-size: 0.88rem;
  color: var(--ink-soft);
}

/* ─── Pagination ─────────────────────────────────────────────── */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 4px 0;
}

.page-summary {
  margin: 0;
  color: var(--ink-soft);
  font-size: 0.84rem;
}

.page-controls {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.page-btn {
  min-width: 34px;
  height: 34px;
  border-radius: var(--radius-inner);
  border: 1px solid var(--line-strong);
  background: var(--surface);
  color: var(--ink-body);
  font-weight: 600;
  font-size: 0.84rem;
  cursor: pointer;
  padding: 0 10px;
  font-family: var(--font-sans);
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.page-btn:hover:not(:disabled) {
  border-color: var(--accent-mid);
  background: var(--accent-light);
  color: var(--accent-hover);
}

.page-btn.active {
  background: var(--accent-light);
  border-color: var(--accent-mid);
  color: var(--accent-hover);
  font-weight: 800;
}

.page-btn:disabled { cursor: not-allowed; opacity: 0.45; }

/* ─── Responsive ─────────────────────────────────────────────── */
@media (max-width: 860px) {
  .page-head { flex-direction: column; align-items: stretch; }
  .controls { flex-wrap: wrap; }
  .search { width: 100%; }
}

@media (max-width: 520px) {
  .title { font-size: 1.45rem; }
  .pagination { flex-direction: column; align-items: flex-start; }
  .page-controls { width: 100%; }
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
