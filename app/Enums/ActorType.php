<?php

namespace App\Enums;

enum ActorType: string
{
    /** An authenticated Radcal staff member. actor_id points at a user. */
    case Admin = 'admin';

    /** A customer acting inside an exchange session. No user row. */
    case Customer = 'customer';

    /** The scheduler / a console command (e.g. the purge job). */
    case System = 'system';
}
