import { ref, readonly } from "vue";

export type ThemePreference = "dark" | "light" | "system";
export type ResolvedTheme = "dark" | "light";

const STORAGE_KEY = "autopMR-theme";

const preference = ref<ThemePreference>("dark");
const resolved = ref<ResolvedTheme>("dark");
let initialized = false;
let mediaListener: ((e: MediaQueryListEvent) => void) | null = null;

function getSystemTheme(): ResolvedTheme {
  if (typeof window === "undefined" || !window.matchMedia) return "dark";
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

function applyTheme(theme: ResolvedTheme, withTransition = false) {
  if (typeof document === "undefined") return;
  const root = document.documentElement;
  if (withTransition) {
    root.classList.add("theme-switching");
    window.setTimeout(() => root.classList.remove("theme-switching"), 300);
  }
  root.setAttribute("data-theme", theme);
  resolved.value = theme;
}

function readStoredPreference(): ThemePreference {
  if (typeof localStorage === "undefined") return "dark";
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved === "dark" || saved === "light" || saved === "system") return saved;
  return "dark";
}

export function setThemePreference(next: ThemePreference) {
  preference.value = next;
  try { localStorage.setItem(STORAGE_KEY, next); } catch { /* quota/denied */ }
  const effective = next === "system" ? getSystemTheme() : next;
  applyTheme(effective, true);
}

export function toggleTheme() {
  setThemePreference(resolved.value === "dark" ? "light" : "dark");
}

export function initTheme() {
  if (initialized) return;
  initialized = true;

  const saved = readStoredPreference();
  preference.value = saved;
  const effective = saved === "system" ? getSystemTheme() : saved;
  applyTheme(effective, false);

  if (typeof window !== "undefined" && window.matchMedia) {
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    mediaListener = (e) => {
      if (preference.value === "system") {
        applyTheme(e.matches ? "dark" : "light", true);
      }
    };
    mq.addEventListener("change", mediaListener);
  }
}

export function useTheme() {
  return {
    preference: readonly(preference),
    resolved: readonly(resolved),
    setPreference: setThemePreference,
    toggle: toggleTheme,
    init: initTheme,
  };
}
