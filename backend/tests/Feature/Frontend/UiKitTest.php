<?php

it('renders the UI kit for a Super Admin', function () {
    $this->actingAs(superAdmin(), 'admin')
        ->get('/admin/ui-kit')
        ->assertOk()
        ->assertSee(__('app.ui_kit.title'))
        ->assertSee('data-confirm-dialog', false)
        ->assertSee('id="ui-kit-modal"', false);
});

it('forbids the UI kit to an admin without the Super Admin role', function () {
    $this->actingAs(adminWithPermissions('settings.manage', 'admin_users.view', 'roles.view'), 'admin')
        ->get('/admin/ui-kit')
        ->assertForbidden();
});

it('forbids the UI kit to an HR Manager', function () {
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')
        ->get('/admin/ui-kit')
        ->assertForbidden();
});

it('sends a guest to the admin login instead of the UI kit', function () {
    $this->get('/admin/ui-kit')->assertRedirect(route('admin.login'));
});
