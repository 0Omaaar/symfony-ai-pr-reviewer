import { ref } from "vue";

export type ToastType = "success" | "warning" | "error" | "info";

export interface Toast {
  id: number;
  type: ToastType;
  title?: string;
  message: string;
  duration: number;
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

function push(type: ToastType, message: string, opts: { title?: string; duration?: number } = {}) {
  const id = nextId++;
  const duration = opts.duration ?? 4000;
  const toast: Toast = { id, type, message, title: opts.title, duration };
  toasts.value = [...toasts.value, toast];
  if (duration > 0) {
    window.setTimeout(() => dismiss(id), duration);
  }
  return id;
}

function dismiss(id: number) {
  toasts.value = toasts.value.filter((t) => t.id !== id);
}

export function useToast() {
  return {
    toasts,
    dismiss,
    success: (message: string, opts?: { title?: string; duration?: number }) => push("success", message, opts),
    warning: (message: string, opts?: { title?: string; duration?: number }) => push("warning", message, opts),
    error:   (message: string, opts?: { title?: string; duration?: number }) => push("error",   message, opts),
    info:    (message: string, opts?: { title?: string; duration?: number }) => push("info",    message, opts),
  };
}
