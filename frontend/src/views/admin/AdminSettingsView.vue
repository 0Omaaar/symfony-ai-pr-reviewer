<template>
  <div class="page">
    <div v-if="loading" class="loading">Loading settings…</div>
    <div v-else-if="error" class="error-banner">{{ error }}</div>

    <template v-else>
      <!-- Feature Flags -->
      <section class="section">
        <h2 class="section-title">Feature Flags</h2>
        <p class="section-desc">Toggle platform-wide features. Changes take effect immediately.</p>

        <div class="card">
          <div v-for="(meta, key) in availableKeys" :key="key" class="toggle-row">
            <div>
              <p class="toggle-label">{{ flagLabel(key) }}</p>
              <p class="toggle-desc">{{ meta.description }}</p>
            </div>
            <button
              class="toggle-btn"
              :class="localSettings[key] ? 'toggle-on' : 'toggle-off'"
              @click="toggleFlag(key)"
            >
              {{ localSettings[key] ? "Enabled" : "Disabled" }}
            </button>
          </div>
        </div>

        <button class="save-btn" :disabled="saving" @click="saveSettings">
          {{ saving ? "Saving…" : "Save Changes" }}
        </button>

        <p v-if="saveSuccess" class="success-msg">Settings saved successfully.</p>
        <p v-if="saveError" class="error-msg">{{ saveError }}</p>
      </section>

      <!-- Danger Zone -->
      <section class="section">
        <h2 class="section-title danger-title">Danger Zone</h2>
        <p class="section-desc">Irreversible operations. Proceed with extreme caution.</p>

        <div class="card danger-card">
          <div class="danger-row">
            <div>
              <p class="danger-action-title">Clear All Webhook Events</p>
              <p class="danger-action-desc">Permanently deletes all processed webhook delivery records. Cannot be undone.</p>
            </div>
            <button class="danger-btn" @click="showClearWebhooks = true">Clear Webhook Events</button>
          </div>
        </div>
      </section>
    </template>

    <!-- Confirm clear webhooks modal -->
    <div v-if="showClearWebhooks" class="modal-overlay" @click.self="showClearWebhooks = false">
      <div class="modal">
        <h3 class="modal-title">Clear Webhook Events?</h3>
        <p class="modal-text">
          This will permanently delete all processed webhook delivery records.
          Type <strong>DELETE</strong> to confirm.
        </p>
        <input v-model="confirmText" class="confirm-input" type="text" placeholder="Type DELETE" />
        <div class="modal-actions">
          <button class="modal-btn cancel" @click="showClearWebhooks = false">Cancel</button>
          <button class="modal-btn danger" :disabled="confirmText !== 'DELETE' || clearing" @click="doClearWebhooks">
            {{ clearing ? "Clearing…" : "Confirm" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { fetchAdminSettings, updateAdminSettings, clearWebhookEvents } from "@/api/admin";

const loading = ref(true);
const error = ref("");
const saving = ref(false);
const saveSuccess = ref(false);
const saveError = ref("");
const localSettings = ref<Record<string, boolean>>({});
const availableKeys = ref<Record<string, { type: string; description: string }>>({});
const showClearWebhooks = ref(false);
const confirmText = ref("");
const clearing = ref(false);

onMounted(async () => {
  try {
    const data: any = await fetchAdminSettings();
    localSettings.value = { ...data.settings };
    availableKeys.value = data.available_keys;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load settings";
  } finally {
    loading.value = false;
  }
});

function flagLabel(key: string): string {
  return key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function toggleFlag(key: string) {
  localSettings.value[key] = !localSettings.value[key];
}

async function saveSettings() {
  saving.value = true;
  saveSuccess.value = false;
  saveError.value = "";
  try {
    await updateAdminSettings(localSettings.value);
    saveSuccess.value = true;
    setTimeout(() => (saveSuccess.value = false), 3000);
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : "Failed to save";
  } finally {
    saving.value = false;
  }
}

async function doClearWebhooks() {
  if (confirmText.value !== "DELETE") return;
  clearing.value = true;
  try {
    const result: any = await clearWebhookEvents();
    alert(`Deleted ${result.deleted} webhook event records.`);
    showClearWebhooks.value = false;
    confirmText.value = "";
  } catch {
    alert("Failed to clear webhook events.");
  } finally {
    clearing.value = false;
  }
}
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 28px; color: #e2e8f0; max-width: 720px; }

.loading { color: #6b7a99; }
.error-banner { padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; color: #f87171; font-size: 0.85rem; }

.section { display: flex; flex-direction: column; gap: 14px; }
.section-title { margin: 0; font-size: 1rem; font-weight: 800; color: #e2e8f0; }
.section-title.danger-title { color: #f87171; }
.section-desc { margin: 0; font-size: 0.83rem; color: #6b7a99; }

.card { background: #13131f; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
.danger-card { border-color: rgba(239,68,68,0.15); }

.toggle-row {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.toggle-row:last-child { border-bottom: none; }
.toggle-label { margin: 0 0 3px; font-size: 0.88rem; font-weight: 700; color: #c4cde8; }
.toggle-desc { margin: 0; font-size: 0.78rem; color: #6b7a99; }

.toggle-btn {
  padding: 6px 16px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; font-family: inherit;
  cursor: pointer; white-space: nowrap; transition: background 0.12s; border: 1px solid transparent;
}
.toggle-on { background: rgba(74,222,128,0.12); border-color: rgba(74,222,128,0.25); color: #4ade80; }
.toggle-off { background: rgba(107,122,153,0.1); border-color: rgba(107,122,153,0.2); color: #6b7a99; }
.toggle-on:hover { background: rgba(74,222,128,0.2); }
.toggle-off:hover { background: rgba(107,122,153,0.16); }

.save-btn {
  align-self: flex-start; padding: 9px 22px; background: linear-gradient(135deg, #4f46e5, #6366f1);
  border: none; border-radius: 10px; color: #fff; font-size: 0.88rem; font-weight: 700;
  font-family: inherit; cursor: pointer; transition: opacity 0.12s;
}
.save-btn:disabled { opacity: 0.5; cursor: wait; }

.success-msg { margin: 0; font-size: 0.82rem; color: #4ade80; font-weight: 600; }
.error-msg { margin: 0; font-size: 0.82rem; color: #f87171; font-weight: 600; }

.danger-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; padding: 18px 20px; flex-wrap: wrap;
}
.danger-action-title { margin: 0 0 4px; font-size: 0.88rem; font-weight: 700; color: #f87171; }
.danger-action-desc { margin: 0; font-size: 0.78rem; color: #6b7a99; }

.danger-btn {
  padding: 8px 18px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
  border-radius: 9px; color: #f87171; font-size: 0.82rem; font-weight: 700;
  font-family: inherit; cursor: pointer; white-space: nowrap;
}
.danger-btn:hover { background: rgba(239,68,68,0.18); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: grid; place-items: center; z-index: 100; }
.modal { background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 28px; max-width: 420px; width: calc(100% - 32px); }
.modal-title { margin: 0 0 12px; font-size: 1.1rem; font-weight: 800; color: #f0f4ff; }
.modal-text { margin: 0 0 16px; font-size: 0.88rem; color: #94a3c4; line-height: 1.6; }
.modal-text strong { color: #e2e8f0; }

.confirm-input {
  width: 100%; height: 40px; padding: 0 14px; margin-bottom: 18px;
  background: #0f0f1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px;
  color: #e2e8f0; font-size: 0.88rem; font-family: inherit; outline: none; box-sizing: border-box;
}

.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.modal-btn { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; font-family: inherit; cursor: pointer; border: 1px solid transparent; }
.modal-btn.cancel { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #94a3c4; }
.modal-btn.danger { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.35); color: #f87171; }
.modal-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
