<?php

namespace App\Support;

class StaffPermissions
{
    public const ALL = ['dashboard', 'guests', 'stays.force_departure', 'rooms.view', 'rooms.manage', 'settings', 'housekeeping', 'housekeeping.inspect', 'maintenance', 'maintenance.inspect', 'food_beverage', 'reports', 'users', 'security', 'locks.unlock', 'backups.manage'];

    public const TEMPLATES = ['super_admin' => ['*'], 'manager' => ['dashboard', 'guests', 'stays.force_departure', 'rooms.view', 'rooms.manage', 'settings', 'housekeeping', 'housekeeping.inspect', 'maintenance', 'maintenance.inspect', 'food_beverage', 'reports', 'locks.unlock'], 'front_desk' => ['dashboard', 'guests', 'rooms.view'], 'housekeeping' => ['dashboard', 'rooms.view', 'rooms.manage', 'housekeeping', 'locks.unlock'], 'maintenance' => ['dashboard', 'rooms.view', 'maintenance']];
}
