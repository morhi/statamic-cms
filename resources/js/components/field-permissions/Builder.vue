<template>
    <div class="w-full">
        <p class="mb-6 text-sm text-gray-700 dark:text-dark-175">
            {{ __('messages.field_permissions_instructions') }}
        </p>

        <Field
            :label="__('Viewable By')"
            :instructions="__('messages.field_permissions_viewable_by_instructions')"
        >
            <div class="mb-4 flex flex-col gap-y-4">
                <div v-if="rolesOptions.length">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('Roles') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="rolesOptions"
                        :model-value="view.roles"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select roles...')"
                        @update:modelValue="updateViewRoles"
                    />
                </div>
                <div v-if="groupsOptions.length">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('User Groups') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="groupsOptions"
                        :model-value="view.groups"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select user groups...')"
                        @update:modelValue="updateViewGroups"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('Users') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="usersOptions"
                        :model-value="view.users"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select users...')"
                        @update:modelValue="updateViewUsers"
                    />
                </div>
            </div>
        </Field>

        <Field
            :label="__('Editable By')"
            :instructions="__('messages.field_permissions_editable_by_instructions')"
        >
            <div class="mb-4 flex flex-col gap-y-4">
                <div v-if="rolesOptions.length">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('Roles') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="rolesOptions"
                        :model-value="edit.roles"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select roles...')"
                        @update:modelValue="updateEditRoles"
                    />
                </div>
                <div v-if="groupsOptions.length">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('User Groups') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="groupsOptions"
                        :model-value="edit.groups"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select user groups...')"
                        @update:modelValue="updateEditGroups"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-dark-175">{{ __('Users') }}</label>
                    <Combobox
                        searchable
                        multiple
                        :options="usersOptions"
                        :model-value="edit.users"
                        option-label="title"
                        option-value="id"
                        :placeholder="__('Select users...')"
                        @update:modelValue="updateEditUsers"
                    />
                </div>
            </div>
        </Field>
    </div>
</template>

<script>
import { Field, Combobox } from '@/components/ui';

export default {
    components: {
        Field,
        Combobox,
    },

    props: {
        config: {
            required: true,
        },
    },

    emits: ['updated'],

    data() {
        return {
            view: {
                roles: [],
                groups: [],
                users: [],
            },
            edit: {
                roles: [],
                groups: [],
                users: [],
            },
            rolesOptions: [],
            groupsOptions: [],
            usersOptions: [],
            loading: true,
        };
    },

    computed: {
        saveablePermissions() {
            let permissions = {};

            let view = this.cleanSet(this.view);
            let edit = this.cleanSet(this.edit);

            if (Object.keys(view).length || Object.keys(edit).length) {
                permissions.view = view;
                permissions.edit = edit;
            }

            return permissions;
        },
    },

    watch: {
        saveablePermissions: {
            deep: true,
            handler(permissions) {
                this.$emit('updated', permissions);
            },
        },
    },

    created() {
        this.loadOptions();
        this.getInitialPermissions();
    },

    methods: {
        loadOptions() {
            this.$axios.get(cp_url('fields/permission-options')).then((response) => {
                this.rolesOptions = response.data.roles;
                this.groupsOptions = response.data.groups;
                this.usersOptions = response.data.users;
                this.loading = false;
            });
        },

        getInitialPermissions() {
            let permissions = this.config.permissions;

            if (!permissions) {
                return;
            }

            if (permissions.view) {
                this.view.roles = permissions.view.roles || [];
                this.view.groups = permissions.view.groups || [];
                this.view.users = permissions.view.users || [];
            }

            if (permissions.edit) {
                this.edit.roles = permissions.edit.roles || [];
                this.edit.groups = permissions.edit.groups || [];
                this.edit.users = permissions.edit.users || [];
            }
        },

        updateViewRoles(value) { this.view.roles = value; },
        updateViewGroups(value) { this.view.groups = value; },
        updateViewUsers(value) { this.view.users = value; },
        updateEditRoles(value) { this.edit.roles = value; },
        updateEditGroups(value) { this.edit.groups = value; },
        updateEditUsers(value) { this.edit.users = value; },

        cleanSet(set) {
            let cleaned = {};

            if (set.roles && set.roles.length) cleaned.roles = set.roles;
            if (set.groups && set.groups.length) cleaned.groups = set.groups;
            if (set.users && set.users.length) cleaned.users = set.users;

            return cleaned;
        },
    },
};
</script>
