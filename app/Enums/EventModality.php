<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Modality of a commune event (MOD column of the INSEE COG movements file).
 */
enum EventModality: int
{
    case AssociatedMerger         = 33;
    case AssociatedToDelegated    = 70;
    case AssociatedToSimpleMerger = 34;
    case CodeChangeDepartment     = 41;
    case CodeChangeSeat           = 50;
    case Creation                 = 20;
    case DelegatedCommuneDeletion = 35;
    case DelegatedCreation        = 72;
    case DelegatedReinstatement   = 71;
    case Deletion                 = 30;
    case NameChange               = 10;
    case NewCommuneCreation       = 32;
    case Reinstatement            = 21;
    case SimpleMerger             = 31;
}
