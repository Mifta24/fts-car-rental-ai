<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RentalUser extends Pivot
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_STAFF = 'staff';

    public const ROLE_VIEWER = 'viewer';

    /** Roles from most to least privileged. */
    public const ROLES = [self::ROLE_OWNER, self::ROLE_STAFF, self::ROLE_VIEWER];

    protected $table = 'rental_users';
}
