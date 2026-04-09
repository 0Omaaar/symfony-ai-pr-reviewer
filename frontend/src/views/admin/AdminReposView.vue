<template>
  <div class="page">
    <div class="filters">
      <input v-model="search" class="search-input" type="text" placeholder="Search by account name…" @input="debouncedLoad" />
    </div>

    <div v-if="loading" class="loading">Loading repositories…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Account</th>
              <th>Type</th>
              <th>Installation ID</th>
              <th>Connected By</th>
              <th>Users</th>
              <th>Connected</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="repos.length === 0">
              <td colspan="8" class="empty">No installations found.</td>
            </tr>
            <tr v-for="r in repos" :key="r.id">
              <td class="muted">{{ r.id }}</td>
              <td><span class="account">{{ r.account_login ?? "—" }}</span></td>
              <td class="muted">{{ r.account_type ?? "—" }}</td>
              <td class="muted">{{ r.installation_id }}</td>
              <td class="muted">{{ r.connected_by ?? "—" }}</td>
              <td class="muted">{{ r.user_count }}</td>
              <td class="muted">{{ formatDate(r.created_at) }}</td>
              <td>
                <button class="action-btn danger" @click="confirmDisconnect(r)">Disconnect</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button class="page-btn" :disabled="page === 1" @click="load(page - 1)">← Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }} ({{ total }} installations)</span>
        <button class="page-btn" :disabled="page >= totalPages" @click="load(page + 1)">Next →</button>
      </div>
    </template>

    <!-- Disconnect modal -->
    <div v-if="disconnectTarget" class="modal-overlay" @click.self="disconnectTarget = null">
      <div class="modal">
        <h3 class="modal-title">Disconnect Installation</h3>
        <p class="modal-text">
          Disconnect GitHub installation <strong>{{ disconnectTarget.account_login }}</strong>?
          This will remove access for all linked users.
        </p>
        <div class="modal-actions">
          <button class="modal-btn cancel" @click="disconnectTarget = null">Cancel</button>
          <button class="modal-btn danger" :disabled="disconnecting" @click="doDisconnect">
            {{ disconnecting ? "Disconnecting…" : "Disconnect" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminRepos, disconnectAdminRepo } from "@/api/admin";

const repos = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 25;
const loading = ref(true);
const error = ref("");
const search = ref("");
const disconnectTarget = ref<any>(null);
const disconnecting = ref(false);

let debounceTimer: ReturnType<typeof setTimeout>;
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)));

async function load(p = 1) {
  loading.value = true;
  error.value = "";
  page.value = p;
  try {
    const data: any = await fetchAdminRepos({ page: p, pageSize, search: search.value });
    repos.value = data.data;
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

function confirmDisconnect(r: any) { disconnectTarget.value = r; }

async function doDisconnect() {
  if (!disconnectTarget.value) return;
  disconnecting.value = true;
  try {
    await disconnectAdminRepo(disconnectTarget.value.id);
    repos.value = repos.value.filter((r) => r.id !== disconnectTarget.value.id);
    total.value--;
    disconnectTarget.value = null;
  } catch {
    alert("Failed to disconnect installation.");
  } finally {
    disconnecting.value = false;
  }
}

function formatDate(dt: string | null): string {
  if (!dt) return "—";
  return new Date(dt).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

onMounted(() => load());
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }
.filters { display: flex; gap: 10px; }
.search-input { height: 40px; padding: 0 14px; background: #13131f; border: 1px solid rgba(255,255,255,0.09); border-radius: 9px; color: #c4cde8; font-size: 0.85rem; font-family: inherit; outline: none; min-width: 260px; }
.search-input::placeholder { color: #3d4a6b; }
.loading { color: #6b7a99; font-size: 0.9rem; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; color: #f87171; font-size: 0.85rem; }
.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.07); }
.table { width: 100%; border-collapse: collapse; font-size: 0.83rem; background: #13131f; }
.table th { text-align: left; padding: 11px 14px; color: #3d4a6b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.table td { padding: 11px 14px; color: #c4cde8; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(255,255,255,0.02); }
.muted { color: #6b7a99 !important; }
.empty { text-align: center; color: #3d4a6b; padding: 24px !important; }
.account { font-weight: 700; color: #a5b4fc; }
.action-btn { padding: 4px 10px; border-radius: 7px; font-size: 0.75rem; font-weight: 700; font-family: inherit; cursor: pointer; border: 1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06); color: #f87171; }
.action-btn:hover { background: rgba(239,68,68,0.12); }
.pagination { display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: #6b7a99; }
.page-btn { padding: 6px 14px; background: #13131f; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #94a3c4; font-size: 0.82rem; font-family: inherit; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: grid; place-items: center; z-index: 100; }
.modal { background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 28px; max-width: 400px; width: calc(100% - 32px); color: #e2e8f0; }
.modal-title { margin: 0 0 12px; font-size: 1.1rem; font-weight: 800; }
.modal-text { margin: 0 0 24px; font-size: 0.88rem; color: #94a3c4; line-height: 1.6; }
.modal-text strong { color: #e2e8f0; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.modal-btn { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; font-family: inherit; cursor: pointer; border: 1px solid transparent; }
.modal-btn.cancel { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #94a3c4; }
.modal-btn.danger { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.35); color: #f87171; }
.modal-btn:disabled { opacity: 0.5; cursor: wait; }
</style>
