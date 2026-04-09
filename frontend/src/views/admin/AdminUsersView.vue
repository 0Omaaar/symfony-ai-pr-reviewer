<template>
  <div class="page">
    <!-- Filters -->
    <div class="filters">
      <input v-model="search" class="search-input" type="text" placeholder="Search by username or email…" @input="debouncedLoad" />
      <select v-model="statusFilter" class="select" @change="loadUsers(1)">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Loading users…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Installations</th>
              <th>Joined</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0">
              <td colspan="7" class="empty">No users found.</td>
            </tr>
            <tr v-for="u in users" :key="u.id" :class="{ suspended: u.suspended_at }">
              <td class="muted">{{ u.id }}</td>
              <td>
                <RouterLink :to="`/admin/users/${u.id}`" class="link">
                  {{ u.github_username ?? "—" }}
                </RouterLink>
              </td>
              <td class="muted">{{ u.email }}</td>
              <td class="muted">{{ u.installation_count }}</td>
              <td class="muted">{{ formatDate(u.created_at) }}</td>
              <td>
                <span class="badge" :class="u.suspended_at ? 'badge-red' : 'badge-green'">
                  {{ u.suspended_at ? "Suspended" : "Active" }}
                </span>
              </td>
              <td class="actions">
                <RouterLink :to="`/admin/users/${u.id}`" class="action-btn">View</RouterLink>
                <button class="action-btn warn" @click="toggleSuspend(u)">
                  {{ u.suspended_at ? "Unsuspend" : "Suspend" }}
                </button>
                <button class="action-btn danger" @click="confirmDelete(u)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <button class="page-btn" :disabled="page === 1" @click="loadUsers(page - 1)">← Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }} ({{ total }} users)</span>
        <button class="page-btn" :disabled="page >= totalPages" @click="loadUsers(page + 1)">Next →</button>
      </div>
    </template>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal">
        <h3 class="modal-title">Delete User</h3>
        <p class="modal-text">
          Are you sure you want to permanently delete <strong>{{ deleteTarget.github_username ?? deleteTarget.email }}</strong>?
          This action is irreversible and will remove all their data.
        </p>
        <div class="modal-actions">
          <button class="modal-btn cancel" @click="deleteTarget = null">Cancel</button>
          <button class="modal-btn danger" :disabled="deleting" @click="doDelete">
            {{ deleting ? "Deleting…" : "Delete permanently" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminUsers, suspendAdminUser, deleteAdminUser } from "@/api/admin";

const users = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 25;
const loading = ref(true);
const error = ref("");
const search = ref("");
const statusFilter = ref("");
const deleteTarget = ref<any>(null);
const deleting = ref(false);

let debounceTimer: ReturnType<typeof setTimeout>;

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)));

async function loadUsers(p = 1) {
  loading.value = true;
  error.value = "";
  page.value = p;
  try {
    const data: any = await fetchAdminUsers({
      page: p,
      pageSize,
      search: search.value,
      status: statusFilter.value,
    });
    users.value = data.data;
    total.value = data.total;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load users";
  } finally {
    loading.value = false;
  }
}

function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => loadUsers(1), 300);
}

async function toggleSuspend(user: any) {
  try {
    const result: any = await suspendAdminUser(user.id);
    user.suspended_at = result.suspended_at ?? null;
  } catch {
    alert("Failed to update user suspension.");
  }
}

function confirmDelete(user: any) {
  deleteTarget.value = user;
}

async function doDelete() {
  if (!deleteTarget.value) return;
  deleting.value = true;
  try {
    await deleteAdminUser(deleteTarget.value.id);
    users.value = users.value.filter((u) => u.id !== deleteTarget.value.id);
    total.value--;
    deleteTarget.value = null;
  } catch {
    alert("Failed to delete user.");
  } finally {
    deleting.value = false;
  }
}

function formatDate(dt: string | null): string {
  if (!dt) return "—";
  return new Date(dt).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

onMounted(() => loadUsers());
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }

.filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.search-input, .select {
  height: 40px;
  padding: 0 14px;
  background: #13131f;
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 9px;
  color: #c4cde8;
  font-size: 0.85rem;
  font-family: inherit;
  outline: none;
}

.search-input { flex: 1; min-width: 200px; }
.search-input::placeholder { color: #3d4a6b; }
.select option { background: #13131f; }

.loading { color: #6b7a99; font-size: 0.9rem; }

.error-banner {
  padding: 12px 16px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 10px;
  color: #f87171;
  font-size: 0.85rem;
}

.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.07); }

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.83rem;
  background: #13131f;
}

.table th {
  text-align: left;
  padding: 11px 14px;
  color: #3d4a6b;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.table td {
  padding: 11px 14px;
  color: #c4cde8;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  vertical-align: middle;
}

.table tr:last-child td { border-bottom: none; }
.table tr.suspended td { opacity: 0.6; }
.table tr:hover td { background: rgba(255, 255, 255, 0.02); }

.muted { color: #6b7a99 !important; }

.empty { text-align: center; color: #3d4a6b; padding: 24px !important; }

.link { color: #a5b4fc; text-decoration: none; font-weight: 700; }
.link:hover { text-decoration: underline; }

.badge {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 5px;
  font-size: 0.72rem;
  font-weight: 700;
}

.badge-green { background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.2); }
.badge-red { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }

.actions { display: flex; gap: 6px; }

.action-btn {
  padding: 4px 10px;
  border-radius: 7px;
  font-size: 0.75rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  color: #94a3c4;
  text-decoration: none;
  transition: background 0.12s;
}

.action-btn:hover { background: rgba(255, 255, 255, 0.1); }
.action-btn.warn { color: #fbbf24; border-color: rgba(251, 191, 36, 0.2); background: rgba(251, 191, 36, 0.06); }
.action-btn.warn:hover { background: rgba(251, 191, 36, 0.12); }
.action-btn.danger { color: #f87171; border-color: rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.06); }
.action-btn.danger:hover { background: rgba(239, 68, 68, 0.12); }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.82rem;
  color: #6b7a99;
}

.page-btn {
  padding: 6px 14px;
  background: #13131f;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  color: #94a3c4;
  font-size: 0.82rem;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.12s;
}

.page-btn:hover:not(:disabled) { background: rgba(255, 255, 255, 0.06); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { color: #6b7a99; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: grid;
  place-items: center;
  z-index: 100;
}

.modal {
  background: #1a1a2e;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  padding: 28px;
  max-width: 420px;
  width: calc(100% - 32px);
}

.modal-title {
  margin: 0 0 12px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #f0f4ff;
}

.modal-text {
  margin: 0 0 24px;
  font-size: 0.88rem;
  color: #94a3c4;
  line-height: 1.6;
}

.modal-text strong { color: #e2e8f0; }

.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

.modal-btn {
  padding: 9px 18px;
  border-radius: 9px;
  font-size: 0.85rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  border: 1px solid transparent;
}

.modal-btn.cancel {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.1);
  color: #94a3c4;
}

.modal-btn.danger {
  background: rgba(239, 68, 68, 0.15);
  border-color: rgba(239, 68, 68, 0.35);
  color: #f87171;
}

.modal-btn:disabled { opacity: 0.5; cursor: wait; }
</style>
