<?php

namespace App\Services;

use App\Contracts\BriefWriter;
use App\Data\BriefItem;
use App\Data\ClientBrief;

final class FakeBriefWriter implements BriefWriter
{
    public ?string $received = null;

    public bool $deepRequested = false;

    public function __construct(private readonly ?ClientBrief $brief = null) {}

    public function write(string $digest, bool $deep = false): ClientBrief
    {
        $this->received = $digest;
        $this->deepRequested = $deep;

        return $this->brief ?? new ClientBrief(
            headline: 'Three changes shipped this week',
            items: [
                new BriefItem(
                    title: 'Commit history can now be filtered by date and author',
                    detail: 'The tool reads a project\'s history for any date range, either for one person or for the whole team.',
                    area: 'Reporting',
                    certain: true,
                ),
                new BriefItem(
                    title: 'Groundwork for the weekly client summary',
                    detail: 'Internal plumbing that the summary will be built on. No visible change yet.',
                    area: 'Reporting',
                    certain: false,
                ),
            ],
            unclear: ['command'],
        );
    }
}
