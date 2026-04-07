<template>
  <div class="dashboard">
    <div v-if="loading" class="loading">Loading stats…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else-if="stats">
      <!-- Stat cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <p class="stat-label">Total Users</p>
          <p class="stat-value">{{ stats.users.total }}</p>
          <p class="stat-sub">+{{ stats.users.new_this_week }} this week</p>
        </div>
        <div class="stat-card">
          <p class="stat-label">Suspended Users</p>
          <p class="stat-value red">{{ stats.users.suspended }}</p>
          <p class="stat-sub">of {{ stats.users.total }} total</p>
        </div>
        <div class="stat-card">
          <p class="stat-label">GitHub Installations</p>
          <p class="stat-value">{{ stats.installations.total }}</p>
          <p class="stat-sub">Connected apps</p>
        </div>
        <div class="stat-card">
          <p class="stat-label">Webhook Events</p>
          <p class="stat-value">{{ stats.webhook_events.total }}</p>
          <p class="stat-sub">+{{ stats.webhook_events.today }} today</p>
        </div>
        <div class="stat-card">
          <p class="stat-label">Notifications On</p>
          <p class="stat-value green">{{ stats.users.notifications_enabled }}</p>
          <p class="stat-sub">users with email enabled</p>
        </div>
      </div>

      <!-- Charts row -->
      <div class="charts-row">
        <div class="chart-card">
          <h3 class="chart-title">Webhook Events — Last 30 days</h3>
          <div class="bar-chart">
            <div
              v-for="(point, i) in webhookChartData"
              :key="i"
              class="bar-col"
            >
              <div class="bar" :style="{ height: barHeight(point.count, maxWebhook) + '%' }" :title="`${point.day}: ${point.count}`"></div>
              <span v-if="i % 7 === 0" class="bar-label">{{ formatDay(point.day) }}</span>
            </div>
          </div>
        </div>

        <div class="chart-card">
          <h3 class="chart-title">New Signups — Last 30 days</h3>
          <div class="bar-chart">
            <div
              v-for="(point, i) in signupChartData"
              :key="i"
              class="bar-col"
            >
              <div class="bar signup" :style="{ height: barHeight(point.count, maxSignup) + '%' }" :title="`${point.day}: ${point.count}`"></div>
              <span v-if="i % 7 === 0" class="bar-label">{{ formatDay(point.day) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom row -->
      <div class="bottom-row">
        <!-- Recent signups -->
        <div class="list-card">
          <h3 class="list-title">Recent Signups</h3>
          <div v-if="stats.recent_signups.length === 0" class="empty">No signups yet.</div>
          <table v-else class="mini-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in stats.recent_signups" :key="u.id">
                <td>
                  <RouterLink :to="`/admin/users/${u.id}`" class="link">{{ u.github_username ?? "—" }}</RouterLink>
                </td>
                <td class="muted">{{ u.email ?? "—" }}</td>
                <td class="muted">{{ formatDate(u.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Recent admin actions -->
        <div class="list-card">
          <h3 class="list-title">Recent Admin Actions</h3>
          <div v-if="stats.recent_admin_actions.length === 0" class="empty">No admin actions yet.</div>
          <table v-else class="mini-table">
            <thead>
              <tr>
                <th>Action</th>
                <th>Target</th>
                <th>When</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(log, i) in stats.recent_admin_actions" :key="i">
                <td><span class="action-badge">{{ log.action }}</span></td>
                <td class="muted">{{ log.target_type ? `${log.target_type} #${log.target_id}` : "—" }}</td>
                <td class="muted">{{ formatDate(log.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { fetchAdminStats } from "@/api/admin";

const loading = ref(true);
const error = ref("");
const stats = ref<any>(null);

onMounted(async () => {
  try {
    stats.value = await fetchAdminStats();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load stats";
  } finally {
    loading.value = false;
  }
});

const webhookChartData = computed(() => (stats.value?.charts?.webhook_events_by_day ?? []) as Array<{ day: string; count: string }>);
const signupChartData = computed(() => (stats.value?.charts?.user_signups_by_day ?? []) as Array<{ day: string; count: string }>);
const maxWebhook = computed(() => Math.max(1, ...webhookChartData.value.map((p) => Number(p.count))));
const maxSignup = computed(() => Math.max(1, ...signupChartData.value.map((p) => Number(p.count))));

function barHeight(count: string | number, max: number): number {
  const n = Number(count);
  return max === 0 ? 0 : Math.max(4, Math.round((n / max) * 100));
}

function formatDay(day: string): string {
  if (!day) return "";
  const d = new Date(day);
  return `${d.getMonth() + 1}/${d.getDate()}`;
}

function formatDate(dt: string | null): string {
  if (!dt) return "—";
  return new Date(dt).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
  color: #e2e8f0;
}

.loading { color: #6b7a99; }

.error-banner {
  padding: 14px 18px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 10px;
  color: #f87171;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}

.stat-card {
  background: #13131f;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
  padding: 20px;
}

.stat-label {
  margin: 0 0 8px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #6b7a99;
}

.stat-value {
  margin: 0 0 4px;
  font-size: 2rem;
  font-weight: 800;
  color: #e2e8f0;
  line-height: 1;
}

.stat-value.red { color: #f87171; }
.stat-value.green { color: #4ade80; }

.stat-sub {
  margin: 0;
  font-size: 0.75rem;
  color: #4d5a78;
}

/* Charts */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.chart-card {
  background: #13131f;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
  padding: 20px;
}

.chart-title {
  margin: 0 0 16px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #94a3c4;
}

.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 80px;
  position: relative;
}

.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  height: 100%;
  gap: 4px;
}

.bar {
  width: 100%;
  background: rgba(99, 102, 241, 0.7);
  border-radius: 3px 3px 0 0;
  min-height: 3px;
  transition: opacity 0.15s;
}

.bar:hover { opacity: 0.8; }

.bar.signup {
  background: rgba(74, 222, 128, 0.6);
}

.bar-label {
  font-size: 0.55rem;
  color: #3d4a6b;
  white-space: nowrap;
}

/* Bottom row */
.bottom-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.list-card {
  background: #13131f;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
  padding: 20px;
}

.list-title {
  margin: 0 0 14px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #94a3c4;
}

.empty {
  font-size: 0.82rem;
  color: #3d4a6b;
}

.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}

.mini-table th {
  text-align: left;
  padding: 6px 8px;
  color: #3d4a6b;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.mini-table td {
  padding: 8px 8px;
  color: #c4cde8;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.mini-table tr:last-child td { border-bottom: none; }

.muted { color: #6b7a99 !important; }

.link {
  color: #a5b4fc;
  text-decoration: none;
  font-weight: 600;
}

.link:hover { text-decoration: underline; }

.action-badge {
  display: inline-block;
  padding: 2px 8px;
  background: rgba(99, 102, 241, 0.1);
  border: 1px solid rgba(99, 102, 241, 0.2);
  border-radius: 5px;
  font-size: 0.72rem;
  color: #a5b4fc;
  font-weight: 600;
}

@media (max-width: 900px) {
  .charts-row, .bottom-row { grid-template-columns: 1fr; }
}
</style>
