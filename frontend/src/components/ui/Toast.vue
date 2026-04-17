<script setup lang="ts">
import { useToast } from "@/composables/useToast";

const { toasts, dismiss } = useToast();
</script>

<template>
  <Teleport to="body">
    <div class="toast-stack" aria-live="polite" aria-atomic="true">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          :class="['toast', `toast--${t.type}`]"
          role="status"
        >
          <span class="toast__icon" aria-hidden="true">
            <svg v-if="t.type === 'success'" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
            <svg v-else-if="t.type === 'warning'" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            <svg v-else-if="t.type === 'error'" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
          </span>
          <div class="toast__body">
            <p v-if="t.title" class="toast__title">{{ t.title }}</p>
            <p class="toast__msg">{{ t.message }}</p>
          </div>
          <button
            type="button"
            class="toast__close"
            aria-label="Dismiss"
            @click="dismiss(t.id)"
          >
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-stack {
  position: fixed;
  right: 16px;
  bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 200;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: start;
  gap: 10px;
  min-width: 280px;
  max-width: 380px;
  padding: 10px 12px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  color: var(--text-primary);
}

.toast__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: var(--radius-sm);
}

.toast--success .toast__icon { background: var(--success-bg); color: var(--success); }
.toast--warning .toast__icon { background: var(--warning-bg); color: var(--warning); }
.toast--error   .toast__icon { background: var(--error-bg);   color: var(--error); }
.toast--info    .toast__icon { background: var(--info-bg);    color: var(--info); }

.toast__body { min-width: 0; }

.toast__title {
  margin: 0 0 2px;
  font-size: 0.8125rem;
  font-weight: 600;
}

.toast__msg {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.toast__close {
  background: transparent;
  border: 0;
  color: var(--text-muted);
  cursor: pointer;
  padding: 2px;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
}
.toast__close:hover { color: var(--text-primary); background: var(--btn-ghost-hover); }

.toast-enter-active, .toast-leave-active {
  transition: transform var(--transition-base), opacity var(--transition-base);
}
.toast-enter-from { transform: translateX(16px); opacity: 0; }
.toast-leave-to   { transform: translateX(16px); opacity: 0; }
</style>
