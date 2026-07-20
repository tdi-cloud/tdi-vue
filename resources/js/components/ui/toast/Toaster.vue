<script setup lang="ts">
import { isVNode } from "vue"
import { CheckCircle2, XCircle, Sparkles } from "lucide-vue-next"
import { Toast, ToastClose, ToastDescription, ToastProvider, ToastTitle, ToastViewport } from "."
import { useToast } from "./use-toast"

const { toasts } = useToast()

const iconFor = (variant?: string | null) => {
  if (variant === "success") return CheckCircle2
  if (variant === "destructive") return XCircle
  return Sparkles
}
</script>

<template>
  <ToastProvider>
    <Toast v-for="toast in toasts" :key="toast.id" v-bind="toast">

      <span class="toast-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white shadow-sm ring-4 ring-white/40 dark:ring-black/20">
        <component :is="iconFor(toast.variant)" class="h-5 w-5" />
      </span>

      <div class="grid gap-1 min-w-0 pt-0.5">
        <ToastTitle v-if="toast.title">
          {{ toast.title }}
        </ToastTitle>

        <template v-if="toast.description">
          <ToastDescription v-if="isVNode(toast.description)">
            <component :is="toast.description" />
          </ToastDescription>
          <ToastDescription v-else>
            {{ toast.description }}
          </ToastDescription>
        </template>
      </div>
      <ToastClose />
      <component :is="toast.action" />

    </Toast>
    <ToastViewport />
  </ToastProvider>
</template>
