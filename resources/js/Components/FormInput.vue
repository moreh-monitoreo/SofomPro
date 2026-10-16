<template>
    <div>
        <label
            v-if="label"
            :for="id"
            class="block text-sm font-medium text-gray-700"
        >
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <input
            :id="id"
            :type="type"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :class="[
                'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                'focus:border-blue-500 focus:ring-blue-500',
                'placeholder:text-slate-400',
                error ? 'border-red-300' : 'border-gray-300',
                disabled ? 'cursor-not-allowed bg-gray-100' : '',
                customClass,
            ]"
        />

        <p v-if="error" class="mt-1 text-sm text-red-600">
            {{ error }}
        </p>

        <p v-if="helpText" class="mt-1 text-sm text-gray-500">
            {{ helpText }}
        </p>
    </div>
</template>

<script>
export default {
    name: 'FormInput',

    props: {
        modelValue: {
            type: [String, Number],
            default: '',
        },
        label: {
            type: String,
            default: '',
        },
        type: {
            type: String,
            default: 'text',
        },
        placeholder: {
            type: String,
            default: '',
        },
        required: {
            type: Boolean,
            default: false,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        error: {
            type: String,
            default: '',
        },
        helpText: {
            type: String,
            default: '',
        },
        id: {
            type: String,
            default() {
                return `input-${Math.random().toString(36).substr(2, 9)}`;
            },
        },
        customClass: {
            type: String,
            default: '',
        },
    },

    emits: ['update:modelValue'],
};
</script>
