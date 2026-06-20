import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToast } from '@/components/ui/toast/use-toast';

export function useFlash() {
    const page = usePage();
    const { toast } = useToast();

    watch(
        () => page.props.flash,
        (flash: any) => {
            if (flash?.success) {
                toast({
                    title: 'Success',
                    description: flash.success,
                });
            }
            if (flash?.error) {
                toast({
                    title: 'Error',
                    description: flash.error,
                    variant: 'destructive',
                });
            }
        },
        { deep: true }
    );
}