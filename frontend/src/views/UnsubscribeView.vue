<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();

const status = computed(() => route.query.status as string | undefined);
const isSuccess = computed(() => status.value === "success");
const isInvalid = computed(() => status.value === "invalid" || (status.value !== undefined && !isSuccess.value));
</script>

<template>
  <div class="unsubscribe-page">
    <div class="unsubscribe-card">
      <div v-if="isSuccess" class="state success-state">
        <span class="state-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
          </svg>
        </span>
        <h1 class="state-title">Unsubscribed</h1>
        <p class="state-body">You have been successfully unsubscribed from PR alert emails. You won't receive any more notifications.</p>
        <p class="state-hint">You can re-enable email notifications at any time from your account settings.</p>
        <button class="btn btn-primary" @click="router.push({ name: 'settings' })">
          Go to Settings
        </button>
      </div>

      <div v-else-if="isInvalid" class="state invalid-state">
        <span class="state-icon invalid-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
        </span>
        <h1 class="state-title">Invalid Link</h1>
        <p class="state-body">This unsubscribe link is invalid or has already been used. Your notification preferences have not been changed.</p>
        <button class="btn btn-secondary" @click="router.push({ name: 'dashboard' })">
          Go to Dashboard
        </button>
      </div>

      <div v-else class="state neutral-state">
        <span class="state-icon neutral-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
        </span>
        <h1 class="state-title">Unsubscribe</h1>
        <p class="state-body">Use the unsubscribe link from your email to manage your notification preferences.</p>
        <button class="btn btn-secondary" @click="router.push({ name: 'dashboard' })">
          Go to Dashboard
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.unsubscribe-page {
  min-height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
}

.unsubscribe-card {
  width: 100%;
  max-width: 440px;
  border-radius: var(--radius-card);
  border: 1px solid var(--line);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  padding: 40px 36px;
}

.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 14px;
}

.state-icon {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--merged-bg);
  color: var(--merged-ink);
  border: 1px solid var(--merged-line);
}

.invalid-icon {
  background: #fff1f2;
  color: #be123c;
  border-color: #fecdd3;
}

.neutral-icon {
  background: var(--surface-raised);
  color: var(--ink-soft);
  border-color: var(--line);
}

.state-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--ink-strong);
  letter-spacing: -0.02em;
}

.state-body {
  margin: 0;
  font-size: 0.9rem;
  color: var(--ink-body);
  line-height: 1.65;
}

.state-hint {
  margin: 0;
  font-size: 0.82rem;
  color: var(--ink-soft);
  line-height: 1.55;
}

.btn {
  margin-top: 6px;
  border-radius: var(--radius-inner);
  padding: 10px 22px;
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  font-family: var(--font-sans);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  border: 1px solid transparent;
}

.btn:hover { transform: translateY(-1px); }

.btn-primary {
  background: var(--accent-light);
  border-color: var(--accent-mid);
  color: var(--accent-hover);
  box-shadow: 0 4px 12px rgba(13,126,164,0.2);
}

.btn-primary:hover { box-shadow: 0 8px 20px rgba(13,126,164,0.32); }

.btn-secondary {
  background: var(--surface-soft);
  border-color: var(--line-strong);
  color: var(--ink-body);
}

.btn-secondary:hover { background: var(--surface-raised); }

@media (max-width: 480px) {
  .unsubscribe-card { padding: 28px 22px; }
}
</style>
