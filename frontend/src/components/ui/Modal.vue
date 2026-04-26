<script setup lang="ts">
import { onMounted, onUnmounted, watch } from "vue";

interface Props {
  open: boolean;
  title?: string;
  size?: "sm" | "md" | "lg";
  closeOnBackdrop?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
  title: "",
  size: "md",
  closeOnBackdrop: true,
});

const emit = defineEmits<{ (e: "close"): void }>();

function onKey(e: KeyboardEvent) {
  if (e.key === "Escape" && props.open) emit("close");
}

onMounted(() => window.addEventListener("keydown", onKey));
onUnmounted(() => window.removeEventListener("keydown", onKey));

watch(
  () => props.open,
  (v) => {
    if (typeof document !== "undefined") {
      document.body.style.overflow = v ? "hidden" : "";
    }
  }
);
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="modal-backdrop" @click="closeOnBackdrop && emit('close')">
        <div
          :class="['modal', `modal--${size}`]"
          role="dialog"
          aria-modal="true"
          :aria-label="title || undefined"
          @click.stop
        >
          <header v-if="title || $slots.header" class="modal__header">
            <slot name="header">
              <h3 class="modal__title">{{ title }}</h3>
            </slot>
            <button
              type="button"
              class="modal__close"
              aria-label="Close dialog"
              @click="emit('close')"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                <path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
              </svg>
            </button>
          </header>

          <div class="modal__body"><slot /></div>

          <footer v-if="$slots.footer" class="modal__footer">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: color-mix(in srgb, var(--bg-base) 80%, transparent);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  z-index: 100;
  backdrop-filter: blur(4px);
}

.modal {
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xl);
  width: 100%;
  max-height: calc(100vh - 32px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal--sm { max-width: 400px; }
.modal--md { max-width: 560px; }
.modal--lg { max-width: 800px; }

.modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-default);
}

.modal__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: transparent;
  border: 0;
  color: var(--text-secondary);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background-color var(--transition-base), color var(--transition-base);
}
.modal__close:hover { background: var(--btn-ghost-hover); color: var(--text-primary); }

.modal__body {
  padding: 20px;
  overflow-y: auto;
  color: var(--text-primary);
}

.modal__footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border-default);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.modal-enter-active, .modal-leave-active {
  transition: opacity var(--transition-base);
}
.modal-enter-active .modal, .modal-leave-active .modal {
  transition: transform var(--transition-base), opacity var(--transition-base);
}
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal {
  transform: translateY(8px) scale(0.98);
  opacity: 0;
}
</style>
