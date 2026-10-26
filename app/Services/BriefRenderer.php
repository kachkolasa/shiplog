<?php

namespace App\Services;

use App\Data\BriefItem;
use App\Data\ClientBrief;
use Illuminate\Support\Carbon;

final class BriefRenderer
{
    public function render(ClientBrief $brief, Carbon $since, Carbon $until): string
    {
        $headline = $this->escape($brief->headline);
        $range = $this->escape(sprintf(
            '%s – %s',
            $since->toFormattedDateString(),
            $until->toFormattedDateString(),
        ));

        $items = collect($brief->items)
            ->map(fn (BriefItem $item) => $this->item($item))
            ->implode('');

        $unclear = $this->unclear($brief->unclear);

        return sprintf(
            '<div class="mb-1">'
            .'<div class="mt-1 px-1 bg-blue-300 text-black">Shiplog</div>'
            .'<div class="ml-1 mt-1 font-bold">%s</div>'
            .'<div class="ml-1 text-gray-500">%s</div>'
            .'%s%s'
            .'</div>',
            $headline,
            $range,
            $items,
            $unclear,
        );
    }

    private function item(BriefItem $item): string
    {
        $colour = $item->certain ? 'text-green-400' : 'text-yellow-400';
        $flag = $item->certain
            ? ''
            : ' <span class="text-yellow-400">(unverified)</span>';

        return sprintf(
            '<div class="ml-1 mt-1">'
            .'<span class="%s">▪</span> <span class="font-bold">%s</span>%s'
            .'<div class="ml-3 text-gray-500">%s</div>'
            .'</div>',
            $colour,
            $this->escape($item->title),
            $flag,
            $this->escape($item->detail),
        );
    }

    /**
     * @param  array<int, string>  $subjects
     */
    private function unclear(array $subjects): string
    {
        if ($subjects === []) {
            return '';
        }

        $lines = collect($subjects)
            ->map(fn (string $subject) => sprintf(
                '<div class="ml-3 text-gray-500">%s</div>',
                $this->escape($subject),
            ))
            ->implode('');

        return '<div class="ml-1 mt-1">'
            .'<span class="text-yellow-400">Too vague to describe — tighten these commit messages:</span>'
            .$lines
            .'</div>';
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
