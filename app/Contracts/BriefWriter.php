<?php

namespace App\Contracts;

use App\Data\ClientBrief;

interface BriefWriter
{
    public function write(string $digest): ClientBrief;
}
