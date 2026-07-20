<script setup lang="ts">
import type { ToastCloseProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import { ToastClose } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ToastCloseProps & {
  class?: HTMLAttributes["class"]
}>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <ToastClose v-bind="delegatedProps" :class="cn('absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100 group-[.destructive]:text-red-400 group-[.destructive]:hover:text-red-700 group-[.destructive]:focus:ring-red-400 group-[.success]:text-emerald-400 group-[.success]:hover:text-emerald-700 group-[.success]:focus:ring-emerald-400', props.class)">
    <X class="h-4 w-4" />
  </ToastClose>
</template>
