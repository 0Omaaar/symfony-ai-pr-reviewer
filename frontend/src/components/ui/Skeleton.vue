<script setup lang="ts">
interface Props {
  width?: string | number;
  height?: string | number;
  rounded?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
  width: "100%",
  height: "1rem",
  rounded: false,
});

function toCssSize(v: string | number) {
  return typeof v === "number" ? `${v}px` : v;
}
</script>

<template>
  <span
    class="skeleton"
    :class="{ 'skeleton--rounded': rounded }"
    :style="{ width: toCssSize(props.width), height: toCssSize(props.height) }"
    aria-hidden="true"
  />
</template>

<style scoped>
.skeleton {
  display: inline-block;
  background: linear-gradient(
    90deg,
    var(--bg-elevated) 0%,
    var(--bg-overlay) 50%,
    var(--bg-elevated) 100%
  );
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
  border-radius: var(--radius-sm);
}

.skeleton--rounded { border-radius: var(--radius-full); }

@keyframes skeleton-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
