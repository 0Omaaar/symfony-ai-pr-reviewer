<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { fetchMe, clearCachedAuth } from "@/api/auth";
import { deleteAccount, updateNotifications, removeInstallation, getNotificationPreferences, updateNotificationPreferences } from "@/api/account";

const router = useRouter();

const emailNotificationsEnabled = ref(true);
const isLoadingNotif = ref(false);
const isLoadingPrefs = ref(true);
const notifSuccess = ref("");
const notifError = ref("");

// Notification preferences
type EventPrefs = { opened: boolean; closed: boolean; synchronize: boolean; ready_for_review: boolean; converted_to_draft: boolean };
type RepoPrefs = { mode: 'all' | 'specific'; allowed: string[] };
type NotifPrefs = { events: EventPrefs; repos: RepoPrefs };

const defaultPrefs = (): NotifPrefs => ({
  events: { opened: true, closed: true, synchronize: true, ready_for_review: true, converted_to_draft: true },
  repos: { mode: 'all', allowed: [] },
});

const notifPrefs = ref<NotifPrefs>(defaultPrefs());
const isSavingPrefs = ref(false);
const prefsSuccess = ref("");
const prefsError = ref("");
const newRepoInput = ref("");

async function savePreferences() {
  isSavingPrefs.value = true;
  prefsSuccess.value = "";
  prefsError.value = "";
  try {
    const result = await updateNotificationPreferences(notifPrefs.value);
    notifPrefs.value = result.notification_preferences;
    prefsSuccess.value = "Preferences saved.";
  } catch (e) {
    prefsError.value = e instanceof Error ? e.message : "Failed to save preferences.";
  } finally {
    isSavingPrefs.value = false;
  }
}

function addRepo() {
  const repo = newRepoInput.value.trim();
  if (!repo) return;
  if (!notifPrefs.value.repos.allowed.includes(repo)) {
    notifPrefs.value.repos.allowed = [...notifPrefs.value.repos.allowed, repo];
  }
  newRepoInput.value = "";
}

function removeRepo(repo: string) {
  notifPrefs.value.repos.allowed = notifPrefs.value.repos.allowed.filter(r => r !== repo);
}

const showDeleteConfirm = ref(false);
const isDeletingAccount = ref(false);
const deleteError = ref("");

type Installation = { installation_id: number; account_login: string | null; account_type: string | null };
const installations = ref<Installation[]>([]);
const removingId = ref<number | null>(null);
const installError = ref("");
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

onMounted(async () => {
  try {
    const [me, prefsResult] = await Promise.all([fetchMe(), getNotificationPreferences()]);
    if (me?.user?.emailNotificationsEnabled !== undefined) {
      emailNotificationsEnabled.value = me.user.emailNotificationsEnabled;
    }
    if (Array.isArray(me?.installations)) {
      installations.value = me.installations as Installation[];
    }
    if (prefsResult?.notification_preferences) {
      notifPrefs.value = { ...defaultPrefs(), ...prefsResult.notification_preferences };
    }
  } catch {
    notifError.value = "Could not load your preferences. Please refresh.";
  } finally {
    isLoadingPrefs.value = false;
  }
});

async function handleRemoveInstallation(installationId: number) {
  removingId.value = installationId;
  installError.value = "";
  try {
    await removeInstallation(installationId);
    installations.value = installations.value.filter(i => i.installation_id !== installationId);
  } catch (e) {
    installError.value = e instanceof Error ? e.message : "Failed to remove installation.";
  } finally {
    removingId.value = null;
  }
}

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
            :disabled="isLoadingNotif || isLoadingPrefs"
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

    <!-- Notification Preferences section -->
    <section class="settings-card">
      <div class="card-header">
        <span class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 8v2h8v-2h-8zM3 11v2h8v-2H3zm10-6v2h8V5h-8zm0 8v2h8v-2h-8z"/>
          </svg>
        </span>
        <div>
          <h2 class="card-title">Notification Filters</h2>
          <p class="card-desc">Choose which PR events and repositories trigger email alerts.</p>
        </div>
      </div>

      <div class="card-body prefs-body">
        <fieldset class="prefs-fieldset" :disabled="isLoadingPrefs">
          <legend class="prefs-legend">PR Event Types</legend>
          <div class="prefs-checks">
            <label class="pref-check" v-for="(label, key) in { opened: 'Opened / Reopened', closed: 'Closed', synchronize: 'Code updated (push)', ready_for_review: 'Ready for review', converted_to_draft: 'Converted to draft' }" :key="key">
              <input type="checkbox" v-model="notifPrefs.events[key as keyof typeof notifPrefs.events]" />
              <span>{{ label }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="prefs-fieldset" :disabled="isLoadingPrefs">
          <legend class="prefs-legend">Repository Filter</legend>
          <div class="repo-mode">
            <label class="pref-radio">
              <input type="radio" v-model="notifPrefs.repos.mode" value="all" />
              <span>All repositories</span>
            </label>
            <label class="pref-radio">
              <input type="radio" v-model="notifPrefs.repos.mode" value="specific" />
              <span>Specific repositories only</span>
            </label>
          </div>

          <div v-if="notifPrefs.repos.mode === 'specific'" class="repo-filter">
            <div class="repo-add-row">
              <input
                v-model="newRepoInput"
                class="repo-input"
                type="text"
                placeholder="owner/repo-name"
                @keydown.enter.prevent="addRepo"
              />
              <button class="btn btn-add-repo" type="button" @click="addRepo">Add</button>
            </div>
            <ul v-if="notifPrefs.repos.allowed.length > 0" class="repo-allowed-list">
              <li v-for="repo in notifPrefs.repos.allowed" :key="repo" class="repo-allowed-item">
                <code class="repo-name">{{ repo }}</code>
                <button class="btn-remove-repo" type="button" @click="removeRepo(repo)" aria-label="Remove repo">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
              </li>
            </ul>
            <p v-else class="repo-empty-warn">No repositories added — no notifications will fire.</p>
          </div>
        </fieldset>

        <div class="prefs-actions">
          <button class="btn btn-save" :disabled="isSavingPrefs || isLoadingPrefs" @click="savePreferences">
            {{ isSavingPrefs ? "Saving…" : "Save preferences" }}
          </button>
        </div>
        <p v-if="prefsSuccess" class="feedback success">{{ prefsSuccess }}</p>
        <p v-if="prefsError" class="feedback error">{{ prefsError }}</p>
      </div>
    </section>

    <!-- GitHub App Installations section -->
    <section class="settings-card">
      <div class="card-header">
        <span class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M12 .5a12 12 0 0 0-3.79 23.39c.6.1.82-.26.82-.58v-2.04c-3.34.73-4.04-1.62-4.04-1.62-.55-1.4-1.34-1.77-1.34-1.77-1.1-.76.08-.75.08-.75 1.2.08 1.84 1.25 1.84 1.25 1.08 1.85 2.83 1.32 3.52 1.01.1-.79.42-1.32.77-1.62-2.67-.3-5.47-1.34-5.47-5.94 0-1.31.47-2.39 1.24-3.24-.12-.3-.54-1.53.12-3.18 0 0 1.02-.33 3.33 1.24A11.5 11.5 0 0 1 12 6.32a11.5 11.5 0 0 1 3.03.41c2.3-1.56 3.32-1.24 3.32-1.24.66 1.65.24 2.88.12 3.18.77.85 1.24 1.93 1.24 3.24 0 4.61-2.8 5.63-5.48 5.94.43.38.82 1.12.82 2.26v3.35c0 .32.21.69.82.58A12 12 0 0 0 12 .5Z"/>
          </svg>
        </span>
        <div>
          <h2 class="card-title">GitHub App Installations</h2>
          <p class="card-desc">Manage the GitHub accounts and organizations connected to autoPMR.</p>
        </div>
      </div>

      <div class="card-body">
        <ul v-if="installations.length > 0" class="install-list">
          <li v-for="inst in installations" :key="inst.installation_id" class="install-item">
            <div class="install-info">
              <span class="install-name">{{ inst.account_login ?? "Unknown account" }}</span>
              <span class="install-type">{{ inst.account_type ?? "Unknown type" }}</span>
            </div>
            <button
              class="btn btn-remove"
              :disabled="removingId === inst.installation_id"
              @click="handleRemoveInstallation(inst.installation_id)"
            >
              {{ removingId === inst.installation_id ? "Removing…" : "Remove" }}
            </button>
          </li>
        </ul>
        <p v-else class="install-empty">No GitHub App installations connected yet.</p>

        <p v-if="installError" class="feedback error">{{ installError }}</p>

        <a :href="`${apiBaseUrl}/connect/github/app/install`" class="btn btn-install">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true">
            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
          </svg>
          Install GitHub App
        </a>
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

/* ─── Installations ──────────────────────────────────────────── */
.install-list {
  list-style: none;
  margin: 0 0 14px;
  padding: 0;
  display: grid;
  gap: 6px;
}

.install-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  background: var(--surface-soft);
}

.install-info { display: flex; flex-direction: column; gap: 2px; }

.install-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--ink-strong);
}

.install-type {
  font-size: 0.76rem;
  color: var(--ink-faint);
  font-weight: 600;
}

.install-empty {
  margin: 0 0 14px;
  font-size: 0.86rem;
  color: var(--ink-faint);
}

.btn-remove {
  border: 1px solid var(--line-strong);
  background: var(--surface);
  color: var(--ink-soft);
  font-size: 0.78rem;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: var(--radius-inner);
  cursor: pointer;
  font-family: var(--font-sans);
  transition: color 0.12s ease, border-color 0.12s ease;
  flex-shrink: 0;
}

.btn-remove:hover:not(:disabled) {
  color: #b91c1c;
  border-color: #fca5a5;
}

.btn-remove:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-install {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  border: 1px solid var(--accent-mid);
  background: var(--accent-light);
  color: var(--accent-hover);
  font-size: 0.84rem;
  font-weight: 700;
  padding: 8px 14px;
  border-radius: var(--radius-inner);
  font-family: var(--font-sans);
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.btn-install:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(13,126,164,0.18);
}

.confirm-block { display: flex; flex-direction: column; gap: 12px; }

.confirm-text {
  margin: 0;
  font-size: 0.86rem;
  color: var(--ink-soft);
  line-height: 1.6;
}

.confirm-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* ─── Preferences ────────────────────────────────────────────── */
.prefs-body { display: flex; flex-direction: column; gap: 20px; }

.prefs-fieldset {
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  padding: 14px 16px;
  margin: 0;
}

.prefs-fieldset:disabled { opacity: 0.6; pointer-events: none; }

.prefs-legend {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0 6px;
}

.prefs-checks { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }

.pref-check, .pref-radio {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--ink-body);
  cursor: pointer;
}

.pref-check input[type="checkbox"],
.pref-radio input[type="radio"] {
  width: 16px;
  height: 16px;
  accent-color: var(--accent);
  cursor: pointer;
  flex-shrink: 0;
}

.repo-mode { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }

.repo-filter { margin-top: 14px; display: flex; flex-direction: column; gap: 10px; }

.repo-add-row { display: flex; gap: 8px; }

.repo-input {
  flex: 1;
  padding: 7px 12px;
  font-size: 0.86rem;
  font-family: var(--font-sans);
  border: 1px solid var(--line-strong);
  border-radius: var(--radius-inner);
  background: var(--surface-soft);
  color: var(--ink-body);
  outline: none;
}

.repo-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-light); }

.btn-add-repo {
  border: 1px solid var(--accent-mid);
  background: var(--accent-light);
  color: var(--accent-hover);
  font-size: 0.84rem;
  font-weight: 700;
  padding: 7px 14px;
  border-radius: var(--radius-inner);
  cursor: pointer;
  font-family: var(--font-sans);
  white-space: nowrap;
}

.btn-add-repo:hover { background: var(--accent-mid); }

.repo-allowed-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.repo-allowed-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border: 1px solid var(--line);
  border-radius: var(--radius-inner);
  background: var(--surface-soft);
}

.repo-name {
  font-size: 0.84rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: var(--ink-body);
}

.btn-remove-repo {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ink-faint);
  display: flex;
  align-items: center;
  padding: 2px;
  border-radius: 4px;
}

.btn-remove-repo:hover { color: #b91c1c; }

.repo-empty-warn {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #b45309;
}

.prefs-actions { display: flex; }

.btn-save {
  background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent-mid) 100%);
  border: 1px solid var(--accent-mid);
  color: var(--accent-hover);
  font-size: 0.86rem;
  font-weight: 700;
  padding: 9px 20px;
  border-radius: var(--radius-inner);
  cursor: pointer;
  font-family: var(--font-sans);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px -8px rgba(13,126,164,0.35);
}

.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
