<?php

namespace Tests\Fields;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Statamic\Fields\Field;
use Statamic\Fields\FieldPermissionResolver;
use Tests\FakesRoles;
use Tests\FakesUserGroups;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class FieldPermissionResolverTest extends TestCase
{
    use FakesRoles, FakesUserGroups, PreventSavingStacheItemsToDisk;

    #[Test]
    public function field_without_permissions_is_unrestricted()
    {
        $field = new Field('test', ['type' => 'text']);
        $user = tap(User::make())->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function field_with_empty_permissions_is_unrestricted()
    {
        $field = new Field('test', ['type' => 'text', 'permissions' => []]);
        $user = tap(User::make())->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function field_with_empty_permission_sets_is_unrestricted()
    {
        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => [], 'groups' => [], 'users' => []],
                'edit' => ['roles' => [], 'groups' => [], 'users' => []],
            ],
        ]);
        $user = tap(User::make())->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function super_user_bypasses_all_permissions()
    {
        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => ['admin']],
                'edit' => ['roles' => ['admin']],
            ],
        ]);

        $user = tap(User::make()->makeSuper())->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function null_user_cannot_view_or_edit_restricted_fields()
    {
        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => ['admin']],
            ],
        ]);

        $result = FieldPermissionResolver::resolve($field, null);

        $this->assertFalse($result['can_view']);
        $this->assertFalse($result['can_edit']);
    }

    #[Test]
    public function user_with_matching_role_can_view()
    {
        $this->setTestRoles(['editor' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => ['editor']],
            ],
        ]);

        $user = tap(User::make()->assignRole('editor'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function user_without_matching_role_cannot_view()
    {
        $this->setTestRoles(['editor' => [], 'writer' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => ['editor']],
            ],
        ]);

        $user = tap(User::make()->assignRole('writer'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertFalse($result['can_view']);
        $this->assertFalse($result['can_edit']);
    }

    #[Test]
    public function user_can_view_but_not_edit_when_only_view_role_matches()
    {
        $this->setTestRoles(['editor' => [], 'admin' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['roles' => ['editor', 'admin']],
                'edit' => ['roles' => ['admin']],
            ],
        ]);

        $user = tap(User::make()->assignRole('editor'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertFalse($result['can_edit']);
    }

    #[Test]
    public function user_with_matching_group_can_view()
    {
        $this->setTestUserGroups(['marketing' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['groups' => ['marketing']],
            ],
        ]);

        $user = tap(User::make()->set('groups', ['marketing']))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function user_with_matching_id_can_view()
    {
        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['users' => ['user-123']],
            ],
        ]);

        $user = tap(User::make()->id('user-123'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }

    #[Test]
    public function user_without_matching_id_cannot_view()
    {
        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['users' => ['user-123']],
            ],
        ]);

        $user = tap(User::make()->id('user-456'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        $this->assertFalse($result['can_view']);
        $this->assertFalse($result['can_edit']);
    }

    #[Test]
    public function cannot_edit_implies_cannot_view_is_false()
    {
        // When the user can't view, they also can't edit (even if edit config would allow them).
        $this->setTestRoles(['admin' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => ['users' => ['user-123']],
                'edit' => ['roles' => ['admin']],
            ],
        ]);

        $user = tap(User::make()->id('user-456')->assignRole('admin'))->save();

        $result = FieldPermissionResolver::resolve($field, $user);

        // User doesn't have view access, so can't edit either
        $this->assertFalse($result['can_view']);
        $this->assertFalse($result['can_edit']);
    }

    #[Test]
    public function any_matching_criterion_grants_access()
    {
        $this->setTestRoles(['editor' => []]);
        $this->setTestUserGroups(['team' => []]);

        $field = new Field('test', [
            'type' => 'text',
            'permissions' => [
                'view' => [
                    'roles' => ['admin'],
                    'groups' => ['management'],
                    'users' => ['user-123'],
                ],
            ],
        ]);

        // User matches by ID
        $user = tap(User::make()->id('user-123'))->save();
        $result = FieldPermissionResolver::resolve($field, $user);
        $this->assertTrue($result['can_view']);
    }

    #[Test]
    public function null_user_with_unrestricted_field_has_access()
    {
        $field = new Field('test', ['type' => 'text']);

        $result = FieldPermissionResolver::resolve($field, null);

        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_edit']);
    }
}
