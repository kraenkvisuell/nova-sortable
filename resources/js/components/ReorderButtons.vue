<template>
    <div class="flex items-center">
        <slot name="checkbox" />

        <div class="flex items-center ml-4" v-tooltip="reorderDisabledTooltip" v-if="canSeeReorderButtons">
            <template v-if="resource.has_dropdown">
                <select 
                    v-model="currentPosition"
                    v-on:change="$emit('positionChanged', currentPosition)"
                    class="form-control form-select"
                >
                    <option
                        v-for="(position, positionIndex) in resource.all_positions"
                        :key="position"
                        :value="position"
                    >
                        {{ parseInt(positionIndex) + 1 }}
                    </option>
                </select>
            </template>

            <template v-else>
                <div 
                    class="flex flex-col"
                >
                    <chevron-up-icon
                        @click="!reorderDisabled && $emit('moveToStart')"
                        :custom-class="{
                            'cursor-pointer text-70 hover:text-80': !reorderDisabled,
                            'cursor-default text-50': reorderDisabled,
                        }"
                        v-tooltip="moveToStartTooltip"
                    />

                    <chevron-down-icon
                        @click="!reorderDisabled && $emit('moveToEnd')"
                        :custom-class="{
                            'cursor-pointer text-70 hover:text-80': !reorderDisabled,
                            'cursor-default text-50': reorderDisabled,
                        }"
                        v-tooltip="moveToEndTooltip"
                    />
                </div>

                <burger-icon
                    style="min-width: 22px; width: 32px"
                    :custom-class="{
                        'handle cursor-move text-70 hover:text-80': !reorderDisabled,
                        'text-50 cursor-default': reorderDisabled,
                    }"
                />
            </template>
            
        </div>
    </div>
</template>

<script>
import ChevronUpIcon from '../icons/ChevronUpIcon';
import ChevronDownIcon from '../icons/ChevronDownIcon';
import BurgerIcon from '../icons/BurgerIcon';
import { canSortResource } from '../tool';

export default {
    components: { ChevronUpIcon, ChevronDownIcon, BurgerIcon },
    props: ['resource', 'viaResourceId', 'relationshipType', 'viaRelationship', 'resourceName'],
    data: function() {
        return {
            currentPosition: 0,
        }
    },
    computed: {
        tooltipClasses() {
            return ['bg-white', 'px-3', 'py-2', 'rounded', 'border', 'border-50', 'shadow', 'text-sm', 'leading-normal'];
        },

        canSeeReorderButtons() {
            return canSortResource(this.resource, this.relationshipType);
        },

        // Returns reason string why reordering is disabled
        reorderDisabled() {
            if (this.resource.sort_not_allowed) {
                return 'notAllowed';
            }

            if (!!this.$route.query[this.orderByParameter]) {
                return 'activeSort';
            }

            return false;
        },

        orderByParameter() {
            return this.viaRelationship ? this.viaRelationship + '_order' : this.resourceName + '_order';
        },

        reorderDisabledTooltip() {
            return this.reorderDisabled
                ? {
                        content: this.__(`novaSortable.reorderingDisabledTooltip.${this.reorderDisabled}`),
                        classes: this.tooltipClasses,
                        offset: 5,
                        boundariesElement: document,
                    }
                : void 0;
        },

        moveToStartTooltip() {
            return !this.reorderDisabled
                ? {
                        content: this.__('novaSortable.moveToStart'),
                        classes: this.tooltipClasses,
                        offset: 5,
                    }
                : void 0;
        },

        moveToEndTooltip() {
            return !this.reorderDisabled
                ? {
                        content: this.__('novaSortable.moveToEnd'),
                        classes: this.tooltipClasses,
                        offset: 5,
                    }
                : void 0;
        },
    },
    created() {
        this.currentPosition = this.resource.position
    }
};
</script>
