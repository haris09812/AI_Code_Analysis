<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case PENDING    = 'pending';
    case CLONING    = 'cloning';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
}
