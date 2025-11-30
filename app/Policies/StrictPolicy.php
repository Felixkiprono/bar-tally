<?php

namespace App\Policies;

use App\Models\User;

class StrictPolicy
{
    protected array $allowedRoles = [
        User::ROLE_TENANT_ADMIN,
        User::ROLE_SUPER_ADMIN,
    ];

    public function viewAny(User $user): bool
    {
        return $user->role && in_array($user->role, $this->allowedRoles);
    }

    public function view(User $user, $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, $model): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, $model): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $this->viewAny($user);
    }
}
