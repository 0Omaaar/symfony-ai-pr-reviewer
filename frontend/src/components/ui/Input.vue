<script setup lang="ts">
interface Props {
  modelValue?: string;
  placeholder?: string;
  error?: string;
  disabled?: boolean;
  type?: string;
  id?: string;
}
withDefaults(defineProps<Props>(), {
  modelValue: "",
  placeholder: "",
  error: "",
  disabled: false,
  type: "text",
  id: undefined,
});

defineEmits<{ (e: "update:modelValue", value: string): void }>();
</script>

<template>
  <div class="input-wrap">
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="['input', { 'input--error': !!error }]"
      :aria-invalid="!!error"
      @input="(e) => $emit('update:modelValue', (e.target as HTMLInputElement).value)"
    />
    <p v-if="error" class="input-error">{{ error }}</p>
  </div>
</template>

<style scoped>
.input-wrap { display: flex; flex-direction: column; gap: 4px; width: 100%; }

.input {
  width: 100%;
  padding: 8px 12px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: var(--radius-md);
  color: var(--input-text);
  font-family: var(--font-ui);
  font-size: 0.875rem;
  line-height: 1.4;
  transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.input::placeholder { color: var(--input-placeholder); }

.input:focus {
  outline: none;
  border-color: var(--input-border-focus);
  box-shadow: 0 0 0 3px var(--input-ring);
}

.input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.input--error {
  border-color: var(--error);
}
.input--error:focus {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--error) 25%, transparent);
}

.input-error {
  margin: 0;
  font-size: 0.75rem;
  color: var(--error);
}
</style>
