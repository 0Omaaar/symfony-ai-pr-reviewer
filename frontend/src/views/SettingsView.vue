<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { fetchMe, clearCachedAuth } from "@/api/auth";
import { deleteAccount, updateNotifications } from "@/api/account";

const router = useRouter();

const emailNotificationsEnabled = ref(true);
const isLoadingNotif = ref(false);
const notifSuccess = ref("");
const notifError = ref("");

const showDeleteConfirm = ref(false);
const isDeletingAccount = ref(false);
const deleteError = ref("");

onMounted(async () => {
  try {
    const me = await fetchMe();
    if (me?.user?.emailNotificationsEnabled !== undefined) {
      emailNotificationsEnabled.value = me.user.emailNotificationsEnabled;
    }
  } catch {
    // user data unavailable, keep default
  }
});

async function toggleNotifications() {
  isLoadingNotif.value = true;
  notifSuccess.value = "";
  notifError.value = "";

  try {
    const result = await updateNotifications(!emailNotificationsEnabled.value);
    emailNotificationsEnabled.value = result.email_notifications_enabled;
    notifSuccess.value = "Preferences saved.";
  } catch (e) {
    notifError.value = e instanceof Error ? e.message : "Failed to update preferences.";
  } finally {
    isLoadingNotif.value = false;
  }
}

async function confirmDeleteAccount() {
  isDeletingAccount.value = true;
  deleteError.value = "";

  try {
    await deleteAccount();
    clearCachedAuth();
    await router.replace({ name: "login" });
  } catch (e) {
    deleteError.value = e instanceof Error ? e.message : "Failed to delete account.";
    isDeletingAccount.value = false;
    showDeleteConfirm.value = false;
  }
}
</script>

<template>
  <div class="settings-page">
    <header class="page-header">
      <h1 class="page-title">Account Settings</h1>
      <p class="page-subtitle">Manage your notifications and account data.</p>
    </header>

    <!-- Notifications section -->
    <section class="settings-card">
      <div class="card-header">
        <span class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
          </svg>
        </span>
        <div>
          <h2 class="card-title">Email Notifications</h2>
          <p class="card-desc">Receive email alerts when pull requests are opened or updated in your repositories.</p>
        </div>
      </div>

      <div class="card-body">
        <label class="toggle-row">
          <span class="toggle-label">PR alert emails</span>
          <button
            class="toggle-switch"
            :class="{ 'is-on': emailNotificationsEnabled }"
            :disabled="isLoadingNotif"
            @click="toggleNotifications"
            :aria-pressed="emailNotificationsEnabled"
            aria-label="Toggle email notifications"
          >
            <span class="toggle-thumb" />
          </button>
        </label>

        <p v-if="notifSuccess" class="feedback success">{{ notifSuccess }}</p>
        <p v-if="notifError" class="feedback error">{{ notifError }}</p>
      </div>
    </section>

    <!-- Danger zone section -->
    <section class="settings-card danger-card">
      <div class="card-header">
        <span class="card-icon danger-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
        </span>
        <div>
          <h2 class="card-title danger-title">Danger Zone</h2>
          <p class="card-desc">Permanently delete your account and all associated data. This action cannot be undone.</p>
        </div>
      </div>

      <div class="card-body">
        <button
          v-if="!showDeleteConfirm"
          class="btn btn-danger"
          @click="showDeleteConfirm = true"
        >
          Delete my account
        </button>

        <div v-else class="confirm-block">
          <p class="confirm-text">Are you sure? This will permanently delete your account, all linked GitHub installations, and all associated data.</p>
          <div class="confirm-actions">
            <button
              class="btn btn-danger"
              :disabled="isDeletingAccount"
              @click="confirmDeleteAccount"
            >
              {{ isDeletingAccount ? "Deleting..." : "Yes, delete my account" }}
            </button>
            <button
              class="btn btn-cancel"
              :disabled="isDeletingAccount"
              @click="showDeleteConfirm = false"
            >
              Cancel
            </button>
          </div>
          <p v-if="deleteError" class="feedback error">{{ deleteError }}</p>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.settings-page {
  max-width: 680px;
  margin: 0 auto;
  padding-bottom: 32px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-header { margin-bottom: 4px; }

.page-title {
  margin: 0 0 6px;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--ink-strong);
  letter-spacing: -0.02em;
}

.page-subtitle {
  margin: 0;
  font-size: 0.88rem;
  color: var(--ink-soft);
}

/* ─── Card ───────────────────────────────────────────────────── */
.settings-card {
  border-radius: var(--radius-card);
  border: 1px solid var(--line);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 18px 22px 14px;
  border-bottom: 1px solid var(--line);
}

.card-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--accent-light);
  color: var(--accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--accent-mid);
}

.card-title {
  margin: 0 0 4px;
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--ink-strong);
}

.card-desc {
  margin: 0;
  font-size: 0.84rem;
  color: var(--ink-soft);
  line-height: 1.55;
}

.card-body { padding: 16px 22px 20px; }

/* ─── Toggle ─────────────────────────────────────────────────── */
.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
}

.toggle-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--ink-body);
}

.toggle-switch {
  position: relative;
  width: 44px;
  height: 24px;
  border-radius: var(--radius-pill);
  border: none;
  background: var(--line-strong);
  cursor: pointer;
  transition: background 0.2s ease;
  padding: 0;
  flex-shrink: 0;
}

.toggle-switch.is-on { background: var(--accent); }
.toggle-switch:disabled { opacity: 0.55; cursor: not-allowed; }

.toggle-thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  transition: left 0.2s ease;
}

.toggle-switch.is-on .toggle-thumb { left: 23px; }

/* ─── Feedback ───────────────────────────────────────────────── */
.feedback { margin: 12px 0 0; font-size: 0.84rem; font-weight: 600; }
.feedback.success { color: var(--merged-ink); }
.feedback.error   { color: #b91c1c; }

/* ─── Danger zone ────────────────────────────────────────────── */
.danger-card { border-color: #fecdd3; }
.danger-icon { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.danger-title { color: #be123c; }

/* ─── Buttons ────────────────────────────────────────────────── */
.btn {
  border-radius: var(--radius-inner);
  padding: 9px 18px;
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  font-family: var(--font-sans);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  border: 1px solid transparent;
}

.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }

.btn-danger {
  background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
  border-color: #fda4af;
  color: #881337;
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px -10px rgba(159,18,57,0.4);
}

.btn-cancel {
  background: var(--surface-soft);
  border-color: var(--line-strong);
  color: var(--ink-body);
}

.btn-cancel:hover:not(:disabled) {
  background: var(--surface-raised);
  transform: translateY(-1px);
}

.confirm-block { display: flex; flex-direction: column; gap: 12px; }

.confirm-text {
  margin: 0;
  font-size: 0.86rem;
  color: var(--ink-soft);
  line-height: 1.6;
}

.confirm-actions { display: flex; gap: 10px; flex-wrap: wrap; }
</style>
