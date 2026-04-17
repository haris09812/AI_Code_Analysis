<?php

namespace App\Enums;

enum FileClassification: string
{
    case AI        = 'ai';
    case HUMAN     = 'human';
    case UNCERTAIN = 'uncertain';
}
