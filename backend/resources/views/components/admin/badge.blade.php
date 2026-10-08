@props(['value', 'tone' => null])
@php
$map = [
    'active' => 'success', 'success' => 'success', 'actioned' => 'success', 'online' => 'success', 'enabled' => 'success',
    'suspended' => 'danger', 'disabled' => 'danger', 'revoked' => 'danger', 'failure' => 'danger', 'critical' => 'danger', 'dismissed_officer' => 'danger', 'urgent' => 'danger',
    'locked' => 'warning', 'warning' => 'warning', 'open' => 'warning', 'pending' => 'warning', 'denied' => 'warning', 'retired' => 'warning', 'reviewing' => 'info',
    'info' => 'info', 'dismissed' => '', 'inactive' => '', 'offline' => '', 'away' => 'warning',
];
$tone = $tone ?? ($map[strtolower((string) $value)] ?? '');
@endphp
<span {{ $attributes->merge(['class' => 'badge'.($tone ? ' badge-'.$tone : '')]) }}>{{ $slot->isEmpty() ? ucfirst(str_replace('_', ' ', (string) $value)) : $slot }}</span>
