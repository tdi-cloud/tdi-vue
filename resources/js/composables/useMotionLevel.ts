import { useLiteMode } from '@/composables/useLiteMode';
import { isLowPowerDevice, type MotionLevel } from '@/lib/positionDirectory';
import { useMediaQuery, usePreferredReducedMotion } from '@vueuse/core';
import { computed, type ComputedRef } from 'vue';

/**
 * How much motion the navy/gold "pd" visuals (the Employees page, and the
 * ambient background where it's reused, e.g. the dashboard) should show:
 * - still: the user asked for reduced motion
 * - calm:  lite mode, phones and low-power devices
 * - full:  everything else
 */
export function useMotionLevel(): ComputedRef<MotionLevel> {
    const { isLiteMode } = useLiteMode();
    const reducedMotion = usePreferredReducedMotion();
    const isSmallScreen = useMediaQuery('(max-width: 639px)');
    const isLowPower = isLowPowerDevice();

    return computed<MotionLevel>(() => {
        if (reducedMotion.value === 'reduce') {
            return 'still';
        }
        return isLiteMode.value || isSmallScreen.value || isLowPower ? 'calm' : 'full';
    });
}
