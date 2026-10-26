<?php

namespace App\Contracts;

use App\Data\BriefOptions;
use App\Data\ClientBrief;

interface BriefWriter
{
    public function write(string $digest, BriefOptions $options): ClientBrief;
}
