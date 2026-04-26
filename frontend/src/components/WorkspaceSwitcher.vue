<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getWorkspaces, type Workspace } from "@/api/workspaces";

const route = useRoute();
const router = useRouter();

const workspaces = ref<Workspace[]>([]);
const isLoading = ref(false);

const selectedId = computed(() => {
  const v = route.query.workspaceId;
  return typeof v === "string" && v !== "" ? Number(v) : null;
});

const selectedLabel = computed(() => {
  if (selectedId.value === null) return "All repositories";
  const ws = workspaces.value.find((w) => w.id === selectedId.value);
  return ws ? ws.name : "All repositories";
});

async function load() {
  isLoading.value = true;
  try {
    const res = await getWorkspaces();
    workspaces.value = res.data;
  } catch {
    workspaces.value = [];
  } finally {
    isLoading.value = false;
  }
}

function select(id: number | null) {
  const query = { ...route.query };
  if (id === null) {
    delete query.workspaceId;
  } else {
    query.workspaceId = String(id);
  }
  void router.replace({ query });
}

onMounted(load);
</script>

<template>
  <div class="ws-switcher">
    <label class="ws-label">Scope</label>
    <div class="ws-select-wrap">
      <select
        class="ws-select"
        :value="selectedId ?? ''"
        @change="select(($event.target as HTMLSelectElement).value === '' ? null : Number(($event.target as HTMLSelectElement).value))"
        :disabled="isLoading"
      >
        <option value="">All repositories</option>
        <option v-for="ws in workspaces" :key="ws.id" :value="ws.id">
          {{ ws.name }}
        </option>
      </select>
      <span class="ws-caret" aria-hidden="true">▾</span>
    </div>
    <span v-if="selectedId !== null" class="ws-active-badge">{{ selectedLabel }}</span>
  </div>
</template>

<style scoped>
.ws-switcher {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ws-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.ws-select-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.ws-select {
  appearance: none;
  border: 1px solid var(--line-strong);
  border-radius: var(--radius-inner);
  background: var(--surface);
  color: var(--ink-body);
  font-size: 0.84rem;
  font-weight: 600;
  font-family: var(--font-sans);
  padding: 6px 28px 6px 10px;
  cursor: pointer;
  outline: none;
  transition: border-color 0.15s ease;
  min-width: 160px;
}

.ws-select:focus {
  border-color: var(--accent-mid);
  box-shadow: 0 0 0 3px var(--input-ring);
}

.ws-select:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ws-caret {
  position: absolute;
  right: 8px;
  font-size: 0.7rem;
  color: var(--ink-faint);
  pointer-events: none;
}

.ws-active-badge {
  display: inline-flex;
  align-items: center;
  border-radius: var(--radius-pill);
  padding: 3px 9px;
  font-size: 0.72rem;
  font-weight: 700;
  background: var(--accent-light);
  color: var(--accent-hover);
  border: 1px solid var(--accent-mid);
  white-space: nowrap;
}
</style>
