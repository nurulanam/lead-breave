<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

it('creates every role with its permissions', function (Role $role) {
    $user = userWithRole($role);

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))
            ->toBe(in_array($permission, $role->permissions(), true));
    }
})->with(Role::cases());

it('lets only admins manage users', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))
        ->get('/admin/users')
        ->assertStatus($status);
})->with([
    [Role::Admin, 200],
    [Role::Researcher, 403],
    [Role::Sender, 403],
]);

it('creates a user with roles from the panel', function () {
    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Researcher',
            'email' => 'new@example.com',
            'password' => 'a-long-password-123',
            'roles' => [RoleModel::findByName(Role::Researcher->value)->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'new@example.com')->first()->hasRole(Role::Researcher->value))->toBeTrue();
});

it('stops admins deleting themselves', function () {
    $admin = userWithRole(Role::Admin);
    $other = userWithRole(Role::Sender);

    expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $other))->toBeTrue();

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$admin, $other]);
});

it('limits Horizon to system-tool users', function (Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBe($allowed);
})->with([
    [Role::Admin, true],
    [Role::Researcher, false],
    [Role::Sender, false],
]);
