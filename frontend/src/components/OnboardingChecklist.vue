<script setup lang="ts">
import { onMounted, onUnmounted, computed, ref } from "vue";
import { useRouter } from "vue-router";
import { useOnboarding } from "@/composables/useOnboarding";
import { activateSubscription, getSubscriptions } from "@/api/subscriptions";

const router = useRouter();
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const {
  state,
  isVisible,
  isExpanded,
  isComplete,
  completedCount,
  totalSteps,
  steps,
  fetchState,
  dismiss,
  startPolling,
  stopPolling,
  toggleExpanded,
} = useOnboarding();

const showDismissConfirm = ref(false);
const showCelebration = ref(false);

// Mini repo picker for step 3
const repos = ref<{ id: number; fullName: string; defaultBranch: string | null; installationId: number | null }[]>([]);
const selectedRepo = ref("");
const selectedBranch = ref("");
const isActivating = ref(false);
const activateError = ref("");

async function loadRepos() {
  try {
    const res = await fetch(`${apiBaseUrl}/api/github/repositories`, { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json();
    if (Array.isArray(data.repositories)) {
      repos.value = data.repositories.map((r: any) => ({
        id: r.id,
        fullName: r.full_name ?? r.name ?? "",
        defaultBranch: r.default_branch ?? null,
        installationId: r.installation_id ?? null,
      }));
    }
  } catch { /* silent */ }
}

const selectedRepoObj = computed(() => repos.value.find((r) => r.fullName === selectedRepo.value));

async function activateBranch() {
  const repo = selectedRepoObj.value;
  if (!repo) return;
  const branch = selectedBranch.value || repo.defaultBranch || "main";
  isActivating.value = true;
  activateError.value = "";
  try {
    await activateSubscription(repo.fullName, String(repo.id), String(repo.installationId ?? ""), branch);
    await fetchState();
  } catch (e) {
    activateError.value = e instanceof Error ? e.message : "Failed to activate";
  } finally {
    isActivating.value = false;
  }
}

function confirmDismiss() {
  showDismissConfirm.value = true;
}

async function handleDismiss() {
  await dismiss();
  showDismissConfirm.value = false;
}

function goToDashboard() {
  showCelebration.value = false;
  void router.push({ name: "team-dashboard" });
}

function installGithubApp() {
  window.location.href = `${apiBaseUrl}/connect/github/app/install`;
}

function goToSettings() {
  void router.push({ name: "settings" });
}

onMounted(async () => {
  await fetchState();
  startPolling();
  await loadRepos();
});

onUnmounted(() => {
  stopPolling();
});

// Watch for completion
const wasComplete = ref(false);
import { watch } from "vue";
watch(isComplete, (val) => {
  if (val && !wasComplete.value) {
    showCelebration.value = true;
    wasComplete.value = true;
  }
});
</script>

<template>
  <Teleport to="body">
    <div v-if="isVisible && state" class="onboarding-widget">
      <!-- Celebration overlay -->
      <div v-if="showCelebration" class="celebration-card">
        <div class="celebration-icon">&#127881;</div>
        <h3 class="celebration-title">You're all set!</h3>
        <p class="celebration-text">Your first AI review is on the way. Check the Team Dashboard to see results.</p>
        <div class="celebration-actions">
          <button class="btn btn-primary" @click="goToDashboard">Go to Dashboard</button>
          <button class="btn btn-ghost" @click="showCelebration = false">Dismiss</button>
        </div>
      </div>

      <!-- Collapsed pill -->
      <button v-else-if="!isExpanded" class="onboarding-pill" @click="toggleExpanded">
        <span class="pill-check">&#10003;</span>
        <span class="pill-text">{{ completedCount }}/{{ totalSteps }} steps complete</span>
        <span class="pill-arrow">&#9650;</span>
      </button>

      <!-- Expanded card -->
      <div v-else class="onboarding-card">
        <div class="card-header">
          <div class="header-left">
            <h3 class="card-title">Getting started with autoPMR</h3>
            <p class="card-subtitle">Complete these steps to get your first AI review</p>
          </div>
          <button class="close-btn" @click="confirmDismiss" title="Dismiss checklist">&times;</button>
        </div>

        <!-- Dismiss confirm -->
        <div v-if="showDismissConfirm" class="dismiss-confirm">
          <p>Are you sure? You can reopen this from your profile settings.</p>
          <div class="dismiss-actions">
            <button class="btn btn-danger-sm" @click="handleDismiss">Yes, dismiss</button>
            <button class="btn btn-ghost-sm" @click="showDismissConfirm = false">Cancel</button>
          </div>
        </div>

        <template v-else>
          <!-- Progress bar -->
          <div class="progress-bar-wrap">
            <div class="progress-bar" :style="{ width: `${(completedCount / totalSteps) * 100}%` }"></div>
          </div>
          <p class="progress-label">{{ completedCount }} of {{ totalSteps }} steps</p>

          <!-- Steps -->
          <div class="steps-list">
            <div v-for="step in steps" :key="step.id" class="step-item" :class="{ 'is-complete': step.isComplete }">
              <span class="step-icon">
                <span v-if="step.isComplete" class="check-done">&#10003;</span>
                <span v-else class="check-pending">&#9675;</span>
              </span>
              <div class="step-content">
                <span class="step-label">{{ step.label }}</span>

                <!-- Step 1: GitHub connected (always done) -->
                <template v-if="step.id === 'github_connected' && step.isComplete">
                  <span class="step-done-text">Connected</span>
                </template>

                <!-- Step 2: Install GitHub App -->
                <template v-if="step.id === 'app_installed' && !step.isComplete">
                  <button class="btn btn-sm" @click="installGithubApp">Install GitHub App</button>
                </template>

                <!-- Step 3: Activate a branch -->
                <template v-if="step.id === 'branch_activated' && !step.isComplete">
                  <div class="mini-picker">
                    <select v-model="selectedRepo" class="mini-select">
                      <option value="">Select a repo</option>
                      <option v-for="r in repos" :key="r.fullName" :value="r.fullName">{{ r.fullName }}</option>
                    </select>
                    <input
                      v-if="selectedRepo"
                      v-model="selectedBranch"
                      class="mini-input"
                      :placeholder="selectedRepoObj?.defaultBranch || 'main'"
                    />
                    <button
                      class="btn btn-sm"
                      :disabled="!selectedRepo || isActivating"
                      @click="activateBranch"
                    >
                      {{ isActivating ? "Activating..." : "Activate" }}
                    </button>
                    <p v-if="activateError" class="step-error">{{ activateError }}</p>
                  </div>
                </template>

                <!-- Step 4: Preferences -->
                <template v-if="step.id === 'preferences_set' && !step.isComplete">
                  <button class="btn btn-sm" @click="goToSettings">Set preferences</button>
                </template>

                <!-- Step 5: First review -->
                <template v-if="step.id === 'first_review_received' && !step.isComplete">
                  <span class="step-waiting">Waiting for your first PR event...</span>
                </template>
              </div>
            </div>
          </div>

          <!-- Collapse -->
          <button class="collapse-btn" @click="toggleExpanded">Collapse &#9660;</button>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.onboarding-widget {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 9999;
  font-family: var(--font-sans, "Manrope", sans-serif);
}

/* ─── Pill ─────────────────────────────────────────── */
.onboarding-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 24px;
  border: 1px solid var(--accent-mid, #7dccf0);
  background: linear-gradient(135deg, #0f1e3b, #142848);
  color: #d8eaf8;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  transition: transform 0.15s ease;
}

.onboarding-pill:hover {
  transform: translateY(-2px);
}

.pill-check { color: #10b981; }
.pill-arrow { font-size: 0.6rem; opacity: 0.6; }

/* ─── Card ─────────────────────────────────────────── */
.onboarding-card {
  width: 380px;
  max-height: 80vh;
  overflow-y: auto;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: linear-gradient(175deg, #0f1e3b 0%, #142848 100%);
  color: #d8eaf8;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 14px;
}

.header-left { flex: 1; }

.card-title {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 800;
  color: #f0f6ff;
}

.card-subtitle {
  margin: 0;
  font-size: 0.78rem;
  color: #7da4c9;
}

.close-btn {
  background: none;
  border: none;
  color: #7da4c9;
  font-size: 1.3rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.close-btn:hover { color: #fca5a5; }

/* ─── Progress ─────────────────────────────────────── */
.progress-bar-wrap {
  height: 6px;
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.1);
  overflow: hidden;
  margin-bottom: 6px;
}

.progress-bar {
  height: 100%;
  border-radius: 3px;
  background: linear-gradient(90deg, #10b981, #0d90c5);
  transition: width 0.4s ease;
}

.progress-label {
  margin: 0 0 16px;
  font-size: 0.74rem;
  color: #7da4c9;
  font-weight: 600;
}

/* ─── Steps ────────────────────────────────────────── */
.steps-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.step-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.step-icon {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}

.check-done { color: #10b981; font-weight: 700; }
.check-pending { color: #4d6a8f; font-size: 0.9rem; }

.step-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.step-label {
  font-size: 0.84rem;
  font-weight: 700;
  color: #d8eaf8;
}

.step-item.is-complete .step-label {
  color: #7da4c9;
}

.step-done-text {
  font-size: 0.74rem;
  color: #10b981;
  font-weight: 600;
}

.step-waiting {
  font-size: 0.74rem;
  color: #7da4c9;
  font-style: italic;
}

.step-error {
  margin: 2px 0 0;
  font-size: 0.74rem;
  color: #fca5a5;
}

/* ─── Mini picker ──────────────────────────────────── */
.mini-picker {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.mini-select, .mini-input {
  padding: 6px 10px;
  font-size: 0.78rem;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.07);
  color: #d8eaf8;
  font-family: inherit;
  outline: none;
}

.mini-select:focus, .mini-input:focus {
  border-color: #0d90c5;
}

.mini-select option {
  background: #142848;
  color: #d8eaf8;
}

/* ─── Buttons ──────────────────────────────────────── */
.btn {
  border-radius: 8px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  border: none;
  transition: transform 0.1s ease, opacity 0.15s ease;
}

.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-sm {
  padding: 6px 12px;
  font-size: 0.76rem;
  background: rgba(13, 144, 197, 0.25);
  border: 1px solid rgba(13, 144, 197, 0.4);
  color: #7dccf0;
}

.btn-sm:hover:not(:disabled) {
  background: rgba(13, 144, 197, 0.35);
}

.btn-primary {
  padding: 10px 20px;
  font-size: 0.84rem;
  background: linear-gradient(135deg, #10b981, #0d90c5);
  color: #fff;
}

.btn-primary:hover { transform: translateY(-1px); }

.btn-ghost {
  padding: 10px 20px;
  font-size: 0.84rem;
  background: transparent;
  color: #7da4c9;
}

.btn-ghost:hover { color: #d8eaf8; }

.btn-danger-sm {
  padding: 6px 12px;
  font-size: 0.76rem;
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #fca5a5;
}

.btn-ghost-sm {
  padding: 6px 12px;
  font-size: 0.76rem;
  background: transparent;
  color: #7da4c9;
  border: none;
}

/* ─── Dismiss confirm ──────────────────────────────── */
.dismiss-confirm {
  padding: 12px 0;
}

.dismiss-confirm p {
  margin: 0 0 10px;
  font-size: 0.82rem;
  color: #7da4c9;
}

.dismiss-actions {
  display: flex;
  gap: 8px;
}

/* ─── Collapse ─────────────────────────────────────── */
.collapse-btn {
  display: block;
  width: 100%;
  margin-top: 14px;
  padding: 8px;
  border: none;
  background: transparent;
  color: #4d6a8f;
  font-size: 0.74rem;
  font-weight: 600;
  cursor: pointer;
  text-align: center;
}

.collapse-btn:hover { color: #7da4c9; }

/* ─── Celebration ──────────────────────────────────── */
.celebration-card {
  width: 340px;
  border-radius: 16px;
  border: 1px solid rgba(16, 185, 129, 0.3);
  background: linear-gradient(175deg, #0f1e3b 0%, #142848 100%);
  color: #d8eaf8;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
  padding: 24px;
  text-align: center;
}

.celebration-icon { font-size: 2.5rem; margin-bottom: 10px; }

.celebration-title {
  margin: 0 0 8px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #10b981;
}

.celebration-text {
  margin: 0 0 16px;
  font-size: 0.84rem;
  color: #7da4c9;
  line-height: 1.5;
}

.celebration-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* ─── Responsive ───────────────────────────────────── */
@media (max-width: 480px) {
  .onboarding-card { width: calc(100vw - 40px); }
  .celebration-card { width: calc(100vw - 40px); }
}
</style>
