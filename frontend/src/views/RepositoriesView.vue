<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { mockRepos, type Repository } from "../mocks/repos";
import { useRouter } from "vue-router";

const search = ref("");
const router = useRouter();

function goToRepo(id: number) {
  router.push({name: "repo-details", params: {id}})
}

const filteredRepos = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return mockRepos;
  return mockRepos.filter((r: Repository) =>
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

function formatDate(iso: string | null) {
  if (!iso) return "Never";
  return new Date(iso).toLocaleString("en-US", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

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

function reviewStatus(lastReviewAt: string | null) {
  if (!lastReviewAt) return { label: "Never", className: "never" };
  return { label: "Reviewed", className: "reviewed" };
}
</script>

<template>
  <section class="repos-view">
    <header class="page-head">
      <div class="head-copy">
        <h1 class="title">Repositories</h1>
        <p class="subtitle">
          Track connected source repositories and policy pack coverage from one place.
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

    <div class="table-shell">
      <table class="table" aria-label="Repositories list">
        <colgroup>
          <col class="col-provider" />
          <col class="col-repo" />
          <col class="col-policy" />
          <col class="col-review" />
        </colgroup>

        <thead>
          <tr>
            <th>Provider</th>
            <th>Repository</th>
            <th>Policy pack</th>
            <th>Last review</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="repo in paginatedRepos" :key="repo.id" @click="goToRepo(repo.id)" class="row">
            <td data-label="Provider">
              <span class="chip provider-chip" :class="providerClass(repo.provider)">
                {{ providerLabel(repo.provider) }}
              </span>
            </td>

            <td data-label="Repository" class="repo-cell">
              {{ repo.fullName }}
            </td>

            <td data-label="Policy pack">
              <span class="policy-pill mono">{{ repo.policyPack }}</span>
            </td>

            <td data-label="Last review" class="review-cell">
              <span class="review-chip" :class="reviewStatus(repo.lastReviewAt).className">
                {{ reviewStatus(repo.lastReviewAt).label }}
              </span>
              <span v-if="repo.lastReviewAt" class="review-date">
                {{ formatDate(repo.lastReviewAt) }}
              </span>
            </td>
          </tr>

          <tr v-if="filteredRepos.length === 0" class="empty-row">
            <td colspan="4" class="empty-cell">
              <div class="empty">
                <span class="empty-icon" aria-hidden="true">0</span>
                <p class="empty-title">No repositories found.</p>
                <p class="empty-hint">Try a different search term.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <footer v-if="filteredRepos.length > 0" class="pagination">
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
  gap: 16px;
}

.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--line);
  background: linear-gradient(135deg, #ffffff 0%, #f4f9ff 100%);
}

.head-copy {
  min-width: 0;
}

.title {
  margin: 0;
  font-size: 1.85rem;
  line-height: 1.1;
  letter-spacing: -0.02em;
  color: var(--ink-strong);
}

.subtitle {
  margin: 8px 0 0;
  max-width: 60ch;
  color: var(--ink-soft);
}

.controls {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  width: min(100%, 320px);
  margin-left: auto;
}

.search-wrap {
  position: relative;
  width: 90%;
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 12px;
  width: 18px;
  height: 18px;
  transform: translateY(-50%);
  fill: var(--ink-soft);
  pointer-events: none;
}

.search {
  width: 85%;
  padding: 11px 12px 11px 38px;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  color: var(--ink-strong);
  background: #fff;
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.search::placeholder {
  color: #8493a8;
}

.search:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.14);
}

.results {
  margin: 0;
  font-size: 0.86rem;
  color: var(--ink-soft);
  letter-spacing: 0.02em;
}

.table-shell {
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
  overflow: hidden;
  box-shadow: var(--shadow);
}

.table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
}

.col-provider {
  width: 17%;
}

.col-repo {
  width: 37%;
}

.col-policy,
.col-review {
  width: 23%;
}

thead th {
  padding: 12px 16px;
  border-bottom: 1px solid var(--line);
  background: var(--surface-soft);
  text-align: left;
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-soft);
  font-weight: 700;
}

tbody tr {
  transition: background-color 160ms ease;
}

tbody tr:hover {
  background: #f5fbff;
}

td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--line);
  color: var(--ink-body);
  vertical-align: top;
  overflow-wrap: anywhere;
}

tbody tr:last-child td {
  border-bottom: none;
}

.repo-cell {
  color: #1d3552;
  font-weight: 600;
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

.provider-chip.is-unknown {
  background: #eef2f7;
  color: #475569;
  border-color: #d4dde8;
}

.policy-pill {
  display: inline-block;
  max-width: 100%;
  padding: 4px 10px;
  border-radius: 8px;
  border: 1px solid #d5e4fa;
  background: #eef5ff;
  color: #2f4f7c;
  font-size: 0.82rem;
  white-space: normal;
}

.review-cell {
  display: grid;
  gap: 6px;
}

.review-chip {
  width: fit-content;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.02em;
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

.review-date {
  font-size: 0.84rem;
  color: var(--ink-soft);
}

.mono {
  font-family: "JetBrains Mono", "Fira Code", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.empty-row td {
  border-bottom: none;
}

.empty {
  display: grid;
  justify-items: center;
  gap: 6px;
  padding: 34px 16px;
  text-align: center;
}

.empty-icon {
  width: 32px;
  height: 32px;
  display: inline-grid;
  place-items: center;
  border-radius: 999px;
  border: 1px dashed #bdd3ea;
  color: #7a95b0;
  font-weight: 700;
  font-size: 0.9rem;
  background: #f3f8ff;
}

.empty-title {
  margin: 0;
  font-weight: 700;
  color: #304861;
}

.empty-hint {
  margin: 0;
  font-size: 0.9rem;
  color: #718197;
}

.row {
  cursor: pointer;
}

.row:hover {
  background: #fafafa;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 10px 4px 0;
}

.page-summary {
  margin: 0;
  color: var(--ink-soft);
  font-size: 0.88rem;
}

.page-controls {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.page-btn {
  min-width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid var(--line-strong);
  background: #fff;
  color: #2f4f6a;
  font-weight: 600;
  cursor: pointer;
  padding: 0 10px;
  transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;
}

.page-btn:hover:not(:disabled) {
  border-color: #9fd6ed;
  background: #f2fbff;
  color: #155c7f;
}

.page-btn.active {
  background: var(--accent-soft);
  border-color: #9fd6ed;
  color: #145f82;
}

.page-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

@media (max-width: 1024px) {
  .page-head {
    flex-direction: column;
    align-items: stretch;
  }

  .controls {
    width: min(100%, 420px);
    margin-left: 0;
    align-items: flex-start;
  }
}

@media (max-width: 860px) {
  .table-shell {
    border: none;
    box-shadow: none;
    background: transparent;
    overflow: visible;
  }

  .table,
  tbody,
  tr,
  td {
    display: block;
    width: 100%;
  }

  thead {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }

  tbody {
    display: grid;
    gap: 10px;
  }

  tbody tr {
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface);
    padding: 12px;
  }

  tbody tr td {
    border: none;
    display: grid;
    grid-template-columns: 112px 1fr;
    gap: 10px;
    padding: 8px 0;
    align-items: start;
  }

  tbody tr td::before {
    content: attr(data-label);
    color: #6f8095;
    font-size: 0.74rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-weight: 700;
  }

  .review-cell {
    display: grid;
    grid-template-columns: 112px 1fr;
    gap: 10px;
  }

  .review-cell::before {
    content: attr(data-label);
    color: #6f8095;
    font-size: 0.74rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-weight: 700;
  }

  .empty-row td,
  .empty-row td::before {
    content: none;
    display: block;
  }

  .empty-row td {
    padding: 0;
  }
}

@media (max-width: 520px) {
  .title {
    font-size: 1.5rem;
  }

  .pagination {
    flex-direction: column;
    align-items: flex-start;
  }

  .page-controls {
    width: 100%;
  }

  tbody tr td,
  .review-cell {
    grid-template-columns: 92px 1fr;
  }
}
</style>
