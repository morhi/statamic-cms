<script setup>
import { ref, computed, provide, inject, onMounted, onBeforeUnmount, useTemplateRef, getCurrentInstance } from 'vue';
import { FieldConditionsBuilder, FIELD_CONDITIONS_KEYS } from '../field-conditions/FieldConditions.js';
import { FieldPermissionsBuilder } from '../field-permissions/FieldPermissions.js';
import FieldValidationBuilder from '../field-validation/Builder.vue';
import { Panel, PanelHeader, Heading, Button, Tabs, TabList, TabTrigger, TabContent, CardPanel, Icon, StackHeader, StackContent } from '@/components/ui';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    id: [String, Number],
    config: Object,
    overrides: { type: Array, default: () => [] },
    type: String,
    root: Boolean,
    fields: Array,
    suggestableConditionFields: Array,
    isInsideSet: Boolean,
});

const emit = defineEmits(['committed', 'closed']);

const { proxy } = getCurrentInstance();

const isInsideConfigFields = inject('isInsideConfigFields', false);
const injectedCommitParentField = inject('commitParentField', () => {});

const values = ref(null);
const meta = ref(null);
const originValues = ref(null);
const originMeta = ref(null);
const error = ref(null);
const errors = ref({});
const editedFields = ref(clone(props.overrides));
const activeTab = ref('settings');
const fieldtype = ref(null);
const loading = ref(true);
const blueprint = ref(null);
const isSaving = ref(false);
const container = useTemplateRef('container');
let saveBinding = null;

function getFieldValue(handle) {
    return values.value[handle];
}

function updateField(handle, value, setStoreValue = null) {
    values.value[handle] = value;
    markFieldEdited(handle);

    if (setStoreValue) {
        setStoreValue(handle, value);
    }
}

function commit(params = {}) {
    let { shouldCommitParent, shouldSaveRoot } = params;

    clearErrors();

    proxy.$axios
        .post(cp_url('fields/update'), {
            id: props.id,
            type: props.type,
            values: values.value,
            fields: props.fields,
            isInsideSet: props.isInsideSet,
        })
        .then((response) => {
            container.value?.clearDirtyState();
            emit('committed', response.data, editedFields.value);

            if (shouldCommitParent && injectedCommitParentField) {
                injectedCommitParentField(params);
                close();
                return;
            }

            if (shouldSaveRoot) {
                saveRootForm();
            }

            close();
        })
        .catch((e) => handleAxiosError(e));
}

provide('isInsideConfigFields', true);
provide('updateFieldSettingsValue', updateField);
provide('getFieldSettingsValue', getFieldValue);
provide('commitParentField', commit);

const adjustedBlueprint = computed(() => {
    let bp = blueprint.value;

    bp.tabs = [bp.tabs[0]];

    bp.tabs[0].sections.forEach((section, sectionIndex) => {
        section.fields.forEach((field, fieldIndex) => {
            bp.tabs[0].sections[sectionIndex].fields[fieldIndex].localizable = true;
        });
    });

    return bp;
});

const selectedWidth = computed(() => {
    const width = props.config.width || 100;
    const found = props.widths?.find((w) => w.value === width);
    return found?.text;
});

const fieldtypeConfig = computed(() => fieldtype.value.config);

const canBeLocalized = computed(() => {
    return props.root && Object.keys(Statamic.$config.get('locales')).length > 1 && fieldtype.value.canBeLocalized;
});

const canBeValidated = computed(() => fieldtype.value.canBeValidated);
const canHaveDefault = computed(() => fieldtype.value.canHaveDefault);

const filteredFieldtypeConfig = computed(() => {
    if (props.type === 'grid') {
        return fieldtypeConfig.value.filter((config) => config.handle !== 'fields');
    }

    if (['replicator', 'bard'].includes(props.type)) {
        return fieldtypeConfig.value.filter((config) => config.handle !== 'sets');
    }

    return fieldtypeConfig.value;
});

const hasExtras = computed(() => filteredFieldtypeConfig.value.length > 0);

const isNestedField = computed(() => props.isInsideSet || isInsideConfigFields);

const isPro = computed(() => usePage().props._statamic?.isPro);

function updateFieldConditions(conditions) {
    let newValues = {};

    Object.entries(values.value).forEach(([key, value]) => {
        if (!FIELD_CONDITIONS_KEYS.includes(key)) {
            newValues[key] = value;
        }
    });

    values.value = { ...newValues, ...conditions };

    if (Object.keys(conditions).length > 0) {
        markFieldEdited(Object.keys(conditions)[0]);
    }
}

function updateAlwaysSave(alwaysSave) {
    values.value.always_save = alwaysSave;
    markFieldEdited('always_save');
}

function updateFieldPermissions(permissions) {
    if (Object.keys(permissions).length) {
        values.value = { ...values.value, permissions };
    } else {
        let newValues = { ...values.value };
        delete newValues.permissions;
        values.value = newValues;
    }

    markFieldEdited('permissions');
}

function markFieldEdited(handle) {
    if (editedFields.value.indexOf(handle) === -1) {
        editedFields.value.push(handle);
    }
}

function commitAndSave() {
    commit({ shouldSaveRoot: true });
}

function commitAndCloseAll() {
    commit({ shouldCommitParent: true });
}

function commitAndSaveAll() {
    commit({ shouldCommitParent: true, shouldSaveRoot: true });
}

function saveRootForm() {
    proxy.$events.$emit('root-form-save');
}

function handleSaveShortcut() {
    isNestedField.value ? commitAndSaveAll() : commitAndSave();
}

function handleAxiosError(e) {
    if (e.response && e.response.status === 422) {
        const { message, errors: responseErrors } = e.response.data;
        error.value = message;
        errors.value = responseErrors;
        proxy.$toast.error(message);
    } else {
        proxy.$toast.error(__('Something went wrong'));
    }
}

function clearErrors() {
    error.value = null;
    errors.value = {};
}

function close() {
    emit('closed');
}

function load() {
    const field = props.fields.find(f => f.handle === props.config.handle);

    proxy.$axios
        .post(cp_url('fields/edit'), {
            type: props.type,
            reference: field?.type === 'reference' ? field.field_reference : false,
            values: props.config,
        })
        .then((response) => {
            loading.value = false;
            fieldtype.value = response.data.fieldtype;
            blueprint.value = response.data.blueprint;
            values.value = response.data.values;
            meta.value = { ...response.data.meta };
            originValues.value = response.data.originValues;
            originMeta.value = response.data.originMeta;
        });
}

onMounted(() => {
    load();

    saveBinding = proxy.$keys.bindGlobal(['mod+s'], (e) => {
        if (proxy.$el && proxy.$el.offsetParent !== null) {
            e.preventDefault();
            e.stopPropagation();
            handleSaveShortcut();
        }
    });
});

onBeforeUnmount(() => {
    if (saveBinding) {
        saveBinding.destroy();
    }
});
</script>

<template>
    <div v-if="loading" class="absolute inset-0 z-200 flex items-center justify-center text-center">
        <Icon name="loading" />
    </div>

    <StackHeader v-if="!loading" :title="__(values.display) || __(config.display) || config.handle" :icon="fieldtype.icon">
        <template #actions>
            <Button variant="default" @click.prevent="commit" :text="__('Apply')" />
            <Button v-if="!isNestedField" variant="primary" @click.prevent="commitAndSave" icon="save" :text="__('Apply & Save')" />
            <Button v-if="isNestedField" variant="default" @click.prevent="commitAndCloseAll" :text="__('Apply & Close All')" />
            <Button v-if="isNestedField" variant="primary" @click.prevent="commitAndSaveAll" icon="save" :text="__('Save & Close All')" />
        </template>
    </StackHeader>

    <StackContent>
        <section v-if="!loading" class="isolate">
            <Tabs v-model:modelValue="activeTab">
                <TabList class="mb-6">
                    <TabTrigger name="settings" :text="__('Settings')" />
                    <TabTrigger name="conditions" :text="__('Conditions')" />
                    <TabTrigger name="validation" :text="__('Validation')" />
                    <TabTrigger v-if="isPro" name="permissions" :text="__('Permissions')" />
                </TabList>

                <div>
                    <TabContent name="settings">
                        <ui-publish-container
                            ref="container"
                            :blueprint="adjustedBlueprint"
                            :meta="meta"
                            :errors="errors"
                            v-model="values"
                            v-model:modified-fields="editedFields"
                            :origin-values="originValues"
                            :origin-meta="originMeta"
                            as-config
                        />
                    </TabContent>

                    <TabContent name="conditions">
                        <Panel>
                            <PanelHeader :title="__('Conditions')" />
                            <div class="p-4">
                                <FieldConditionsBuilder
                                    :config="values"
                                    :suggestable-fields="suggestableConditionFields"
                                    @updated="updateFieldConditions"
                                    @updated-always-save="updateAlwaysSave"
                                />
                            </div>
                        </Panel>
                    </TabContent>

                    <TabContent name="validation">
                        <Panel>
                            <PanelHeader :title="__('Validation')" />
                            <div class="p-4">
                                <FieldValidationBuilder :config="values" @updated="updateField('validate', $event)" />
                            </div>
                        </Panel>
                    </TabContent>

                    <TabContent v-if="isPro" name="permissions">
                        <Panel>
                            <PanelHeader :title="__('Permissions')" />
                            <div class="p-4">
                                <FieldPermissionsBuilder
                                    :config="values"
                                    @updated="updateFieldPermissions"
                                />
                            </div>
                        </Panel>
                    </TabContent>
                </div>
            </Tabs>
        </section>
    </StackContent>
</template>
