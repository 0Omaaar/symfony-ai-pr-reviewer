<script setup lang="ts">
interface Props {
  modelValue: boolean;
  disabled?: boolean;
  loading?: boolean;
  ariaLabel?: string;
}
const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  loading: false,
  ariaLabel: "Toggle",
});

const emit = defineEmits<{ (e: "update:modelValue", v: boolean): void }>();

function onClick() {
  if (props.disabled || props.loading) return;
  emit("update:modelValue", !props.modelValue);
}
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :aria-label="ariaLabel"
    :disabled="disabled || loading"
    :class="['toggle', { 'is-on': modelValue, 'is-loading': loading }]"
    @click="onClick"
  >
    <span class="toggle__thumb" />
  </button>
</template>

<style scoped>
.toggle {
  position: relative;
  width: 36px;
  height: 20px;
  border-radius: var(--radius-full);
  background: var(--bg-overlay);
  border: 1px solid var(--border-default);
  padding: 0;
  cursor: pointer;
  transition: background-color var(--transition-base), border-color var(--transition-base);
}

.toggle:disabled { cursor: not-allowed; opacity: 0.6; }

.toggle.is-on {
  background: var(--accent);
  border-color: var(--accent);
}

.toggle__thumb {
  position: absolute;
  top: 1px;
  left: 1px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--text-inverse);
  transition: transform var(--transition-base);
}

.toggle.is-on .toggle__thumb {
  transform: translateX(16px);
  background: var(--accent-foreground);
}

.toggle.is-loading .toggle__thumb {
  animation: toggle-pulse 1s ease-in-out infinite;
}

@keyframes toggle-pulse {
  0%, 100% { opacity: 1; }
  50%      { opacity: 0.5; }
}
</style>
