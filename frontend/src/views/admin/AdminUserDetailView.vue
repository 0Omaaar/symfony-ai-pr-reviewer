<template>
  <div class="page">
    <div class="back-link">
      <RouterLink to="/admin/users" class="link">← Back to Users</RouterLink>
    </div>

    <div v-if="loading" class="loading">Loading user…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else-if="user">
      <!-- Header -->
      <div class="user-header">
        <div class="avatar">{{ (user.github_username ?? "?")[0].toUpperCase() }}</div>
        <div>
          <h2 class="user-name">{{ user.github_username ?? "Unknown" }}</h2>
          <p class="user-email">{{ user.email }}</p>
        </div>
        <div class="header-actions">
          <span class="badge" :class="user.is_suspended ? 'badge-red' : 'badge-green'">
            {{ user.is_suspended ? "Suspended" : "Active" }}
          </span>
          <button class="action-btn warn" @click="toggleSuspend">
            {{ user.is_suspended ? "Unsuspend" : "Suspend" }}
          </button>
          <button class="action-btn danger" @click="showDelete = true">Delete</button>
        </div>
      </div>

      <div class="grid-2">
        <!-- Profile info -->
        <div class="card">
          <h3 class="card-title">Profile</h3>
          <div class="info-rows">
            <div class="info-row"><span class="info-label">ID</span><span class="info-val">{{ user.id }}</span></div>
            <div class="info-row"><span class="info-label">GitHub ID</span><span class="info-val">{{ user.github_id ?? "—" }}</span></div>
            <div class="info-row"><span class="info-label">Username</span><span class="info-val">{{ user.github_username ?? "—" }}</span></div>
            <div class="info-row"><span class="info-label">Email</span><span class="info-val">{{ user.email }}</span></div>
            <div class="info-row"><span class="info-label">Joined</span><span class="info-val">{{ formatDate(user.created_at) }}</span></div>
            <div class="info-row"><span class="info-label">Email Notifications</span>
              <span class="badge" :class="user.email_notifications_enabled ? 'badge-green' : 'badge-gray'">
                {{ user.email_notifications_enabled ? "Enabled" : "Disabled" }}
              </span>
            </div>
            <div v-if="user.is_suspended" class="info-row">
              <span class="info-label">Suspended At</span>
              <span class="info-val red">{{ formatDate(user.suspended_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Notification preferences -->
        <div class="card">
          <h3 class="card-title">Notification Preferences</h3>
          <div v-if="user.notification_preferences">
            <p class="pref-section">Events</p>
            <div class="info-rows">
              <div v-for="(val, key) in user.notification_preferences.events" :key="key" class="info-row">
                <span class="info-label">{{ key }}</span>
                <span class="badge" :class="val ? 'badge-green' : 'badge-gray'">{{ val ? "On" : "Off" }}</span>
              </div>
            </div>
            <p class="pref-section">Repositories</p>
            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Mode</span>
                <span class="info-val">{{ user.notification_preferences.repos?.mode ?? "all" }}</span>
              </div>
              <div v-if="user.notification_preferences.repos?.allowed?.length" class="info-row">
                <span class="info-label">Allowed</span>
                <span class="info-val">{{ user.notification_preferences.repos.allowed.join(", ") }}</span>
              </div>
            </div>
          </div>
          <p v-else class="empty">Default preferences.</p>
        </div>
      </div>

      <!-- Installations -->
      <div class="card">
        <h3 class="card-title">GitHub Installations ({{ user.installations.length }})</h3>
        <div v-if="user.installations.length === 0" class="empty">No installations connected.</div>
        <table v-else class="table">
          <thead><tr><th>ID</th><th>Installation ID</th><th>Account</th><th>Type</th><th>Connected</th></tr></thead>
          <tbody>
            <tr v-for="inst in user.installations" :key="inst.id">
              <td class="muted">{{ inst.id }}</td>
              <td>{{ inst.installation_id }}</td>
              <td>{{ inst.account_login ?? "—" }}</td>
              <td class="muted">{{ inst.account_type ?? "—" }}</td>
              <td class="muted">{{ formatDate(inst.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Delete modal -->
    <div v-if="showDelete" class="modal-overlay" @click.self="showDelete = false">
      <div class="modal">
        <h3 class="modal-title">Delete User</h3>
        <p class="modal-text">Permanently delete <strong>{{ user?.github_username ?? user?.email }}</strong>? This cannot be undone.</p>
        <div class="modal-actions">
          <button class="modal-btn cancel" @click="showDelete = false">Cancel</button>
          <button class="modal-btn danger" :disabled="deleting" @click="doDelete">
            {{ deleting ? "Deleting…" : "Delete permanently" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { fetchAdminUser, suspendAdminUser, deleteAdminUser } from "@/api/admin";

const route = useRoute();
const router = useRouter();
const id = Number(route.params.id);

const user = ref<any>(null);
const loading = ref(true);
const error = ref("");
const showDelete = ref(false);
const deleting = ref(false);

onMounted(async () => {
  try {
    user.value = await fetchAdminUser(id);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load user";
  } finally {
    loading.value = false;
  }
});

async function toggleSuspend() {
  try {
    const result: any = await suspendAdminUser(id);
    user.value.is_suspended = result.suspended;
    user.value.suspended_at = result.suspended_at ?? null;
  } catch {
    alert("Failed to update suspension.");
  }
}

async function doDelete() {
  deleting.value = true;
  try {
    await deleteAdminUser(id);
    router.push({ name: "admin-users" });
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
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; color: #e2e8f0; }

.back-link { font-size: 0.82rem; }
.link { color: #a5b4fc; text-decoration: none; }
.link:hover { text-decoration: underline; }

.loading { color: #6b7a99; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius:10px; color: #f87171; }

.user-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #13131f;
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 14px;
  flex-wrap: wrap;
}

.avatar {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: linear-gradient(135deg, #4f46e5, #6366f1);
  color: #fff;
  font-size: 1.3rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-name { margin: 0; font-size: 1.2rem; font-weight: 800; color: #e2e8f0; }
.user-email { margin: 4px 0 0; font-size: 0.83rem; color: #6b7a99; }

.header-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }

.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 0.73rem;
  font-weight: 700;
}

.badge-green { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.2); }
.badge-red { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
.badge-gray { background: rgba(107,122,153,0.15); color: #6b7a99; border: 1px solid rgba(107,122,153,0.2); }

.action-btn {
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.05);
  color: #94a3c4;
}

.action-btn.warn { color: #fbbf24; border-color: rgba(251,191,36,0.2); background: rgba(251,191,36,0.06); }
.action-btn.danger { color: #f87171; border-color: rgba(239,68,68,0.2); background: rgba(239,68,68,0.06); }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.card {
  background: #13131f;
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 14px;
  padding: 20px;
}

.card-title { margin: 0 0 16px; font-size: 0.85rem; font-weight: 700; color: #94a3c4; }

.info-rows { display: flex; flex-direction: column; gap: 10px; }

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  gap: 12px;
}

.info-row:last-child { border-bottom: none; }

.info-label { font-size: 0.78rem; font-weight: 600; color: #6b7a99; }
.info-val { font-size: 0.82rem; color: #c4cde8; text-align: right; }
.info-val.red { color: #f87171; }

.pref-section { margin: 16px 0 8px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #3d4a6b; }
.empty { font-size: 0.82rem; color: #3d4a6b; }

.table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.table th { text-align: left; padding: 8px 12px; color: #3d4a6b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.table td { padding: 10px 12px; color: #c4cde8; border-bottom: 1px solid rgba(255,255,255,0.04); }
.table tr:last-child td { border-bottom: none; }
.muted { color: #6b7a99 !important; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: grid; place-items: center; z-index: 100; }
.modal { background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 28px; max-width: 400px; width: calc(100% - 32px); }
.modal-title { margin: 0 0 12px; font-size: 1.1rem; font-weight: 800; color: #f0f4ff; }
.modal-text { margin: 0 0 24px; font-size: 0.88rem; color: #94a3c4; line-height: 1.6; }
.modal-text strong { color: #e2e8f0; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.modal-btn { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; font-family: inherit; cursor: pointer; border: 1px solid transparent; }
.modal-btn.cancel { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #94a3c4; }
.modal-btn.danger { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.35); color: #f87171; }
.modal-btn:disabled { opacity: 0.5; cursor: wait; }

@media (max-width: 700px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
