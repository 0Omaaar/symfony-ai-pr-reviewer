<script setup lang="ts">
interface Props {
  content: string;
  position?: "top" | "bottom" | "left" | "right";
}
withDefaults(defineProps<Props>(), { position: "top" });
</script>

<template>
  <span class="tooltip-wrap">
    <slot />
    <span :class="['tooltip', `tooltip--${position}`]" role="tooltip">
      {{ content }}
    </span>
  </span>
</template>

<style scoped>
.tooltip-wrap {
  position: relative;
  display: inline-flex;
}

.tooltip {
  position: absolute;
  z-index: 50;
  padding: 4px 8px;
  background: var(--bg-surface);
  color: var(--text-primary);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  line-height: 1.4;
  white-space: nowrap;
  box-shadow: var(--shadow-md);
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--transition-fast);
}

.tooltip-wrap:hover .tooltip,
.tooltip-wrap:focus-within .tooltip {
  opacity: 1;
}

.tooltip--top    { bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); }
.tooltip--bottom { top: calc(100% + 6px); left: 50%; transform: translateX(-50%); }
.tooltip--left   { right: calc(100% + 6px); top: 50%; transform: translateY(-50%); }
.tooltip--right  { left: calc(100% + 6px); top: 50%; transform: translateY(-50%); }
</style>
