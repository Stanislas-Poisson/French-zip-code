<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentType: string
{
    /**
     * Department of metropolitan France or overseas department.
     */
    case Department = 'department';

    /**
     * Overseas collectivity (COM), which has no region.
     */
    case OverseasCollectivity = 'overseas_collectivity';
}
