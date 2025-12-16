<?php

namespace App\Models;

/**
 * @deprecated Use EntityCounter instead. This class is kept for backwards compatibility.
 *
 * ClassCounter is now an alias for EntityCounter with the table renamed to entity_counters
 * and using polymorphic reference_type/reference_id columns.
 */
class ClassCounter extends EntityCounter
{
    // All functionality moved to EntityCounter
}
