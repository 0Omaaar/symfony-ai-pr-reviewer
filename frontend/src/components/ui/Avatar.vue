<script setup lang="ts">
import { computed, ref } from "vue";

interface Props {
  src?: string;
  login?: string;
  size?: "sm" | "md" | "lg";
  ring?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
  src: "",
  login: "",
  size: "md",
  ring: false,
});

const imgFailed = ref(false);

const initial = computed(() => (props.login ? props.login[0]?.toUpperCase() ?? "?" : "?"));
const showImage = computed(() => !!props.src && !imgFailed.value);
</script>

<template>
  <span :class="['avatar', `avatar--${size}`, { 'avatar--ring': ring }]">
    <img
      v-if="showImage"
      :src="src"
      :alt="login || 'avatar'"
      class="avatar__img"
      @error="imgFailed = true"
    />
    <span v-else class="avatar__fallback" aria-hidden="true">{{ initial }}</span>
  </span>
</template>

<style scoped>
.avatar {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-weight: 600;
  overflow: hidden;
  flex-shrink: 0;
}

.avatar--sm { width: 24px; height: 24px; font-size: 0.75rem; }
.avatar--md { width: 36px; height: 36px; font-size: 0.9375rem; }
.avatar--lg { width: 56px; height: 56px; font-size: 1.25rem; }

.avatar--ring {
  box-shadow: 0 0 0 2px var(--accent), 0 0 0 4px var(--bg-base);
}

.avatar__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatar__fallback {
  width: 100%; height: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-bg);
  color: var(--accent-light);
}
</style>
