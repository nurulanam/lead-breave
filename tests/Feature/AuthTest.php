<?php

use App\Enums\Role;

it('redirects the home page to the panel', function () {
    $this->get('/')->assertRedirect('/admin');
});

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('lets users with a role into the panel', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->get('/admin')
        ->assertOk();
})->with(Role::cases());

it('blocks users without a role', function () {
    $this->actingAs(userWithRole(null))
        ->get('/admin')
        ->assertForbidden();
});
