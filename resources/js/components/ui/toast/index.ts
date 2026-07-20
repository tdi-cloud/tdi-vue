import type { ToastRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"

export { default as Toast } from "./Toast.vue"
export { default as ToastAction } from "./ToastAction.vue"
export { default as ToastClose } from "./ToastClose.vue"
export { default as ToastDescription } from "./ToastDescription.vue"
export { default as Toaster } from "./Toaster.vue"
export { default as ToastProvider } from "./ToastProvider.vue"
export { default as ToastTitle } from "./ToastTitle.vue"
export { default as ToastViewport } from "./ToastViewport.vue"
export { toast, useToast } from "./use-toast"

import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export const toastVariants = cva(
  "group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-2xl border p-4 pr-9 shadow-xl backdrop-blur-sm transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[--reka-toast-swipe-end-x] data-[swipe=move]:translate-x-[--reka-toast-swipe-move-x] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full",
  {
    variants: {
      variant: {
        default:
                    "border-border bg-background text-foreground [&_.toast-icon]:bg-gradient-to-br [&_.toast-icon]:from-indigo-500 [&_.toast-icon]:to-blue-600",
        success:
                    "success group border-emerald-200 bg-gradient-to-br from-emerald-50 to-white text-emerald-900 dark:border-emerald-900/60 dark:from-emerald-950/50 dark:to-background dark:text-emerald-100 [&_.toast-icon]:bg-gradient-to-br [&_.toast-icon]:from-emerald-400 [&_.toast-icon]:to-teal-600",
        destructive:
                    "destructive group border-red-200 bg-gradient-to-br from-red-50 to-white text-red-900 dark:border-red-900/60 dark:from-red-950/50 dark:to-background dark:text-red-100 [&_.toast-icon]:bg-gradient-to-br [&_.toast-icon]:from-rose-500 [&_.toast-icon]:to-red-600",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

type ToastVariants = VariantProps<typeof toastVariants>

export interface ToastProps extends ToastRootProps {
  class?: HTMLAttributes["class"]
  variant?: ToastVariants["variant"]
  onOpenChange?: ((value: boolean) => void) | undefined
}
