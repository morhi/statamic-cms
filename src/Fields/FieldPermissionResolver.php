<?php

namespace Statamic\Fields;

use Statamic\Contracts\Auth\User;
use Statamic\Support\Arr;

class FieldPermissionResolver
{
    public static function resolve(Field $field, ?User $user): array
    {
        $permissions = Arr::get($field->config(), 'permissions');

        if (empty($permissions)) {
            return ['can_view' => true, 'can_edit' => true];
        }

        if (! $user) {
            return ['can_view' => false, 'can_edit' => false];
        }

        if ($user->isSuper()) {
            return ['can_view' => true, 'can_edit' => true];
        }

        $viewConfig = Arr::get($permissions, 'view', []);
        $editConfig = Arr::get($permissions, 'edit', []);

        $canView = static::checkPermissionSet($viewConfig, $user);
        $canEdit = $canView && static::checkPermissionSet($editConfig, $user);

        return ['can_view' => $canView, 'can_edit' => $canEdit];
    }

    protected static function checkPermissionSet(array $permissionSet, User $user): bool
    {
        if (empty($permissionSet)) {
            return true;
        }

        $roles = Arr::get($permissionSet, 'roles', []);
        $groups = Arr::get($permissionSet, 'groups', []);
        $users = Arr::get($permissionSet, 'users', []);

        if (empty($roles) && empty($groups) && empty($users)) {
            return true;
        }

        if (! empty($roles) && static::userHasAnyRole($user, $roles)) {
            return true;
        }

        if (! empty($groups) && static::userInAnyGroup($user, $groups)) {
            return true;
        }

        if (! empty($users) && in_array($user->id(), $users)) {
            return true;
        }

        return false;
    }

    protected static function userHasAnyRole(User $user, array $roles): bool
    {
        return $user->roles()->pluck('handle')->intersect($roles)->isNotEmpty();
    }

    protected static function userInAnyGroup(User $user, array $groups): bool
    {
        return $user->groups()->pluck('handle')->intersect($groups)->isNotEmpty();
    }
}
