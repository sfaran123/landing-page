<?php

namespace AuraTech\SmartDashboard\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesUser
{
    /** The authenticated user as the plain array DashboardService expects. */
    protected function sdUser(Request $request): array
    {
        $u = $request->user();
        return [
            'id' => $u?->getKey(),
            'role_id' => $u->role_id ?? null,
            'name' => $u->name ?? '',
            'can' => $u ? fn (string $permission) => $u->can($permission) : null,
        ];
    }
}
