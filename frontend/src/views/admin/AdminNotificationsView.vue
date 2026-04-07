<template>
  <div class="page">
    <div class="info-banner">
      Notifications are not stored persistently. This view shows per-user notification configuration. Actual delivery logs would require a dedicated notification storage layer.
    </div>

    <div v-if="loading" class="loading">Loading…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Email Notifs</th>
              <th>Repo Mode</th>
              <th>Events</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="5" class="empty">No users found.</td>
            </tr>
            <tr v-for="u in items" :key="u.id">
              <td>
                <RouterLink :to="`/admin/users/${u.id}`" class="link">
                  {{ u.github_username ?? "—" }}
                </RouterLink>
              </td>
              <td class="muted">{{ u.email }}</td>
              <td>
                <span class="badge" :class="u.email_notifications_enabled ? 'badge-green' : 'badge-gray'">
                  {{ u.email_notifications_enabled ? "On" : "Off" }}
                </span>
              </td>
              <td class="muted">{{ u.notification_preferences?.repos?.mode ?? "all" }}</td>
              <td>
                <div class="events">
                  <span
                    v-for="(val, key) in (u.notification_preferences?.events ?? defaultEvents)"
                    :key="key"
                    class="event-chip"
                    :class="val ? 'chip-on' : 'chip-off'"
                  >{{ key }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button class="page-btn" :disabled="page === 1" @click="load(page - 1)">← Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }} ({{ total }} users)</span>
        <button class="page-btn" :disabled="page >= totalPages" @click="load(page + 1)">Next →</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminNotifications } from "@/api/admin";

const items = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 25;
const loading = ref(true);
const error = ref("");

const defaultEvents = { opened: true, closed: true, synchronize: true, ready_for_review: true, converted_to_draft: true };

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)));

async function load(p = 1) {
  loading.value = true;
  error.value = "";
  page.value = p;
  try {
    const data: any = await fetchAdminNotifications({ page: p, pageSize });
    items.value = data.data;
    total.value = data.total;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load";
  } finally {
    loading.value = false;
  }
}

onMounted(() => load());
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }

.info-banner { padding: 10px 16px; background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); border-radius: 9px; font-size: 0.82rem; color: #a5b4fc; }
.loading { color: #6b7a99; font-size: 0.9rem; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; color: #f87171; font-size: 0.85rem; }

.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(255,255,255,0.07); }
.table { width: 100%; border-collapse: collapse; font-size: 0.83rem; background: #13131f; }
.table th { text-align: left; padding: 11px 14px; color: #3d4a6b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.table td { padding: 10px 14px; color: #c4cde8; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.muted { color: #6b7a99 !important; }
.empty { text-align: center; color: #3d4a6b; padding: 24px !important; }

.link { color: #a5b4fc; text-decoration: none; font-weight: 700; }
.link:hover { text-decoration: underline; }

.badge { display: inline-block; padding: 2px 9px; border-radius: 5px; font-size: 0.72rem; font-weight: 700; }
.badge-green { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.2); }
.badge-gray { background: rgba(107,122,153,0.15); color: #6b7a99; border: 1px solid rgba(107,122,153,0.2); }

.events { display: flex; flex-wrap: wrap; gap: 4px; }
.event-chip { padding: 2px 7px; border-radius: 4px; font-size: 0.67rem; font-weight: 600; }
.chip-on { background: rgba(74,222,128,0.08); color: #4ade80; border: 1px solid rgba(74,222,128,0.15); }
.chip-off { background: rgba(107,122,153,0.08); color: #3d4a6b; border: 1px solid rgba(107,122,153,0.12); }

.pagination { display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: #6b7a99; }
.page-btn { padding: 6px 14px; background: #13131f; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #94a3c4; font-size: 0.82rem; font-family: inherit; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
