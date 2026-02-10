<script setup>
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue';
import { Field, Combobox, Panel, PanelHeader, Heading, Subheading } from '@/components/ui';

const props = defineProps({
    config: { type: Object, required: true },
});

const emit = defineEmits(['updated']);

const { proxy } = getCurrentInstance();

const view = ref({ roles: [], groups: [], users: [] });
const edit = ref({ roles: [], groups: [], users: [] });
const rolesOptions = ref([]);
const groupsOptions = ref([]);
const usersOptions = ref([]);
const loading = ref(true);

function cleanSet(set) {
    const cleaned = {};
    if (set.roles?.length) cleaned.roles = set.roles;
    if (set.groups?.length) cleaned.groups = set.groups;
    if (set.users?.length) cleaned.users = set.users;
    return cleaned;
}

const saveablePermissions = computed(() => {
    const viewSet = cleanSet(view.value);
    const editSet = cleanSet(edit.value);

    if (Object.keys(viewSet).length || Object.keys(editSet).length) {
        return { view: viewSet, edit: editSet };
    }

    return {};
});

watch(saveablePermissions, (permissions) => {
    emit('updated', permissions);
}, { deep: true });

function loadOptions() {
    proxy.$axios.get(cp_url('fields/permission-options')).then((response) => {
        rolesOptions.value = response.data.roles;
        groupsOptions.value = response.data.groups;
        usersOptions.value = response.data.users;
        loading.value = false;
    });
}

function initPermissions() {
    const permissions = props.config.permissions;
    if (!permissions) return;

    if (permissions.view) {
        view.value.roles = permissions.view.roles || [];
        view.value.groups = permissions.view.groups || [];
        view.value.users = permissions.view.users || [];
    }

    if (permissions.edit) {
        edit.value.roles = permissions.edit.roles || [];
        edit.value.groups = permissions.edit.groups || [];
        edit.value.users = permissions.edit.users || [];
    }
}

onMounted(() => {
    loadOptions();
    initPermissions();
});
</script>

<template>
    <div class="w-full">
        <p class="mb-6 text-sm text-gray-700 dark:text-dark-175">
            {{ __('messages.field_permissions_instructions') }}
        </p>

        <Panel>
            <PanelHeader>
                <div>
                    <Heading v-text="__('Viewable By')" />
                    <Subheading v-text="__('messages.field_permissions_viewable_by_instructions')" />
                </div>
            </PanelHeader>
            <div class="flex flex-col gap-y-4 p-4">
                <Field v-if="rolesOptions.length" :label="__('Roles')">
                    <Combobox
                        searchable
                        multiple
                        :options="rolesOptions"
                        :model-value="view.roles"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select roles...')"
                        @update:modelValue="view.roles = $event"
                    />
                </Field>
                <Field v-if="groupsOptions.length" :label="__('User Groups')">
                    <Combobox
                        searchable
                        multiple
                        :options="groupsOptions"
                        :model-value="view.groups"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select user groups...')"
                        @update:modelValue="view.groups = $event"
                    />
                </Field>
                <Field :label="__('Users')">
                    <Combobox
                        searchable
                        multiple
                        :options="usersOptions"
                        :model-value="view.users"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select users...')"
                        @update:modelValue="view.users = $event"
                    />
                </Field>
            </div>
        </Panel>

        <Panel>
            <PanelHeader>
                <div>
                    <Heading v-text="__('Editable By')" />
                    <Subheading v-text="__('messages.field_permissions_editable_by_instructions')" />
                </div>
            </PanelHeader>
            <div class="flex flex-col gap-y-4 p-4">
                <Field v-if="rolesOptions.length" :label="__('Roles')">
                    <Combobox
                        searchable
                        multiple
                        :options="rolesOptions"
                        :model-value="edit.roles"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select roles...')"
                        @update:modelValue="edit.roles = $event"
                    />
                </Field>
                <Field v-if="groupsOptions.length" :label="__('User Groups')">
                    <Combobox
                        searchable
                        multiple
                        :options="groupsOptions"
                        :model-value="edit.groups"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select user groups...')"
                        @update:modelValue="edit.groups = $event"
                    />
                </Field>
                <Field :label="__('Users')">
                    <Combobox
                        searchable
                        multiple
                        :options="usersOptions"
                        :model-value="edit.users"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select users...')"
                        @update:modelValue="edit.users = $event"
                    />
                </Field>
            </div>
        </Panel>
    </div>
</template>
