<script setup lang="ts">
interface Props {
  variant?: "primary" | "secondary" | "ghost" | "danger";
  size?: "sm" | "md" | "lg";
  loading?: boolean;
  disabled?: boolean;
  type?: "button" | "submit" | "reset";
}
const props = withDefaults(defineProps<Props>(), {
  variant: "primary",
  size: "md",
  loading: false,
  disabled: false,
  type: "button",
});

defineEmits<{ (e: "click", ev: MouseEvent): void }>();
</script>

<template>
  <button
    :type="props.type"
    :class="['btn', `btn--${variant}`, `btn--${size}`, { 'is-loading': loading }]"
    :disabled="disabled || loading"
    @click="(ev) => !loading && !disabled && $emit('click', ev)"
  >
    <span v-if="loading" class="btn__spinner" aria-hidden="true" />
    <span :class="['btn__content', { 'is-hidden': loading }]"><slot /></span>
  </button>
</template>

<style scoped>
.btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-ui);
  font-weight: 500;
  cursor: pointer;
  transition: background-color var(--transition-base), border-color var(--transition-base), color var(--transition-base);
  white-space: nowrap;
}

.btn:disabled,
.btn.is-loading { cursor: not-allowed; opacity: 0.6; }

.btn--sm { padding: 6px 10px; font-size: 0.75rem; }
.btn--md { padding: 8px 14px; font-size: 0.8125rem; }
.btn--lg { padding: 10px 18px; font-size: 0.875rem; }

.btn--primary {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-text);
}
.btn--primary:hover:not(:disabled) { background: var(--btn-primary-hover); }

.btn--secondary {
  background: var(--btn-secondary-bg);
  color: var(--btn-secondary-text);
  border-color: var(--border-default);
}
.btn--secondary:hover:not(:disabled) { background: var(--btn-secondary-hover); }

.btn--ghost {
  background: transparent;
  color: var(--btn-ghost-text);
}
.btn--ghost:hover:not(:disabled) {
  background: var(--btn-ghost-hover);
  color: var(--text-primary);
}

.btn--danger {
  background: var(--btn-danger-bg);
  color: var(--btn-danger-text);
}
.btn--danger:hover:not(:disabled) { background: var(--btn-danger-hover); }

.btn__content.is-hidden { visibility: hidden; }

.btn__spinner {
  position: absolute;
  width: 14px;
  height: 14px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: btn-spin 0.7s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }
</style>
