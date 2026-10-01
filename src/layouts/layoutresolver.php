<?php

namespace AuraTech\SmartDashboard\Layouts;

/** Which layout does this user see?  user → role → business type → built-in preset. */
class LayoutResolver
{
    public function __construct(private LayoutStore $store) {}

    public function resolve(?int $userId, ?int $roleId, string $businessType): array
    {
        $chain = [];
        if ($userId) $chain[] = ['user', (string) $userId];
        if ($roleId) $chain[] = ['role', (string) $roleId];
        $chain[] = ['business_type', $businessType];
        foreach ($chain as [$scope, $id]) {
            if ($l = $this->store->get($scope, $id)) {
                return $l + ['source' => $scope, 'source_id' => $id];
            }
        }
        return Presets::for($businessType) + ['source' => 'preset', 'source_id' => $businessType];
    }
}
