<template>
  <div class="page">
    <div class="info-banner">
      Webhook events processed by the platform. Detailed PR data is not stored persistently — this shows delivery IDs as received from GitHub.
    </div>

    <div class="filters">
      <input v-model="search" class="search-input" type="text" placeholder="Search by delivery ID…" @input="debouncedLoad" />
      <input v-model="dateFrom" class="date-input" type="date" @change="load(1)" />
      <span class="date-sep">to</span>
      <input v-model="dateTo" class="date-input" type="date" @change="load(1)" />
    </div>

    <div v-if="loading" class="loading">Loading events…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>GitHub Delivery ID</th>
              <th>Processed At</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="events.length === 0">
              <td colspan="3" class="empty">No webhook events found.</td>
            </tr>
            <tr v-for="e in events" :key="e.id">
              <td class="muted">{{ e.id }}</td>
              <td><code class="delivery-id">{{ e.delivery_id }}</code></td>
              <td class="muted">{{ formatDateTime(e.processed_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button class="page-btn" :disabled="page === 1" @click="load(page - 1)">← Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }} ({{ total }} events)</span>
        <button class="page-btn" :disabled="page >= totalPages" @click="load(page + 1)">Next →</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminPullRequests } from "@/api/admin";

const events = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 25;
const loading = ref(true);
const error = ref("");
const search = ref("");
const dateFrom = ref("");
const dateTo = ref("");

let debounceTimer: ReturnType<typeof setTimeout>;
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)));

async function load(p = 1) {
  loading.value = true;
  error.value = "";
  page.value = p;
  try {
    const data: any = await fetchAdminPullRequests({
      page: p, pageSize, search: search.value,
      date_from: dateFrom.value, date_to: dateTo.value,
    });
    events.value = data.data;
    total.value = data.total;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load";
  } finally {
    loading.value = false;
  }
}

function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => load(1), 300);
}

function formatDateTime(dt: string): string {
  if (!dt) return "—";
  return new Date(dt).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

onMounted(() => load());
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }

.info-banner {
  padding: 10px 16px;
  background: rgba(99,102,241,0.08);
  border: 1px solid rgba(99,102,241,0.2);
  border-radius: 9px;
  font-size: 0.82rem;
  color: #a5b4fc;
}

.filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.search-input, .date-input {
  height: 40px; padding: 0 14px;
  background: #13131f; border: 1px solid rgba(255,255,255,0.09);
  border-radius: 9px; color: #c4cde8; font-size: 0.85rem; font-family: inherit; outline: none;
}

.search-input { flex: 1; min-width: 200px; }
.search-input::placeholder { color: #3d4a6b; }
.date-sep { color: #3d4a6b; font-size: 0.82rem; }

.loading { color: #6b7a99; font-size: 0.9rem; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; color: #f87171; font-size: 0.85rem; }

.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.07); }
.table { width: 100%; border-collapse: collapse; font-size: 0.83rem; background: #13131f; }
.table th { text-align: left; padding: 11px 14px; color: #3d4a6b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.table td { padding: 11px 14px; color: #c4cde8; border-bottom: 1px solid rgba(255,255,255,0.04); }
.table tr:last-child td { border-bottom: none; }
.muted { color: #6b7a99 !important; }
.empty { text-align: center; color: #3d4a6b; padding: 24px !important; }

.delivery-id {
  font-family: monospace;
  font-size: 0.78rem;
  background: rgba(255,255,255,0.04);
  padding: 2px 8px;
  border-radius: 5px;
  color: #94a3c4;
  word-break: break-all;
}

.pagination { display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: #6b7a99; }
.page-btn { padding: 6px 14px; background: #13131f; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #94a3c4; font-size: 0.82rem; font-family: inherit; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
