<template>
  <div class="page">
    <div class="toolbar">
      <input v-model="search" class="search-input" type="text" placeholder="Search logs…" @input="debouncedLoad" />
      <a :href="exportUrl" class="export-btn" target="_blank">Export CSV</a>
    </div>

    <div v-if="loading" class="loading">Loading logs…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Action</th>
              <th>Target</th>
              <th>Performed By</th>
              <th>Metadata</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="logs.length === 0">
              <td colspan="6" class="empty">No admin actions logged yet.</td>
            </tr>
            <tr v-for="log in logs" :key="log.id">
              <td class="muted">{{ log.id }}</td>
              <td><span class="action-badge">{{ log.action }}</span></td>
              <td class="muted">{{ log.target_type ? `${log.target_type} #${log.target_id}` : "—" }}</td>
              <td class="muted">{{ log.performed_by }}</td>
              <td>
                <button v-if="log.metadata && Object.keys(log.metadata).length" class="meta-btn" @click="toggleMeta(log.id)">
                  {{ expandedMeta === log.id ? "Hide" : "Show" }}
                </button>
                <span v-else class="muted">—</span>
                <div v-if="expandedMeta === log.id" class="meta-box">
                  <pre>{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                </div>
              </td>
              <td class="muted">{{ formatDateTime(log.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button class="page-btn" :disabled="page === 1" @click="load(page - 1)">← Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }} ({{ total }} entries)</span>
        <button class="page-btn" :disabled="page >= totalPages" @click="load(page + 1)">Next →</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminLogs, getAdminLogsExportUrl } from "@/api/admin";

const logs = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 50;
const loading = ref(true);
const error = ref("");
const search = ref("");
const expandedMeta = ref<number | null>(null);

let debounceTimer: ReturnType<typeof setTimeout>;
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)));
const exportUrl = computed(() => getAdminLogsExportUrl());

async function load(p = 1) {
  loading.value = true;
  error.value = "";
  page.value = p;
  expandedMeta.value = null;
  try {
    const data: any = await fetchAdminLogs({ page: p, pageSize, search: search.value });
    logs.value = data.data;
    total.value = data.total;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load logs";
  } finally {
    loading.value = false;
  }
}

function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => load(1), 300);
}

function toggleMeta(id: number) {
  expandedMeta.value = expandedMeta.value === id ? null : id;
}

function formatDateTime(dt: string): string {
  if (!dt) return "—";
  return new Date(dt).toLocaleString("en-US", {
    month: "short", day: "numeric", year: "numeric",
    hour: "2-digit", minute: "2-digit", second: "2-digit",
  });
}

onMounted(() => load());
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }

.toolbar { display: flex; gap: 10px; align-items: center; }

.search-input {
  height: 40px; padding: 0 14px; background: #13131f;
  border: 1px solid rgba(255,255,255,0.09); border-radius: 9px;
  color: #c4cde8; font-size: 0.85rem; font-family: inherit; outline: none; flex: 1;
}
.search-input::placeholder { color: #3d4a6b; }

.export-btn {
  padding: 0 18px; height: 40px; display: flex; align-items: center;
  background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.25);
  border-radius: 9px; color: #a5b4fc; font-size: 0.82rem; font-weight: 700;
  text-decoration: none; white-space: nowrap;
}
.export-btn:hover { background: rgba(99,102,241,0.2); }

.loading { color: #6b7a99; font-size: 0.9rem; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; color: #f87171; font-size: 0.85rem; }

.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.07); }
.table { width: 100%; border-collapse: collapse; font-size: 0.83rem; background: #13131f; }
.table th { text-align: left; padding: 11px 14px; color: #3d4a6b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.table td { padding: 10px 14px; color: #c4cde8; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
.table tr:last-child td { border-bottom: none; }
.muted { color: #6b7a99 !important; }
.empty { text-align: center; color: #3d4a6b; padding: 24px !important; }

.action-badge {
  display: inline-block; padding: 2px 9px; border-radius: 5px; font-size: 0.72rem; font-weight: 700;
  background: rgba(99,102,241,0.1); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.2);
}

.meta-btn {
  padding: 2px 9px; border-radius: 5px; font-size: 0.72rem; font-weight: 700; cursor: pointer;
  background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #94a3c4; font-family: inherit;
}
.meta-btn:hover { background: rgba(255,255,255,0.09); }

.meta-box {
  margin-top: 8px; padding: 10px; background: #0d0d14;
  border: 1px solid rgba(255,255,255,0.07); border-radius: 8px;
}

.meta-box pre {
  margin: 0; font-size: 0.73rem; color: #94a3c4; white-space: pre-wrap; word-break: break-all; font-family: monospace;
}

.pagination { display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: #6b7a99; }
.page-btn { padding: 6px 14px; background: #13131f; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #94a3c4; font-size: 0.82rem; font-family: inherit; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
