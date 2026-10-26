<?php

namespace App\Services;

use App\Data\BriefItem;
use App\Data\BriefUsage;
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
            .'%s%s%s'
            .'</div>',
            $headline,
            $range,
            $items,
            $unclear,
            $this->usage($brief->usage),
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

    private function usage(?BriefUsage $usage): string
    {
        if ($usage === null) {
            return '';
        }

        $text = sprintf(
            '%s tokens in, %s out',
            number_format($usage->inputTokens),
            number_format($usage->outputTokens),
        );

        $cost = $usage->estimatedCost();

        if ($cost !== null) {
            $text .= match (true) {
                $cost < 0.0001 => ' — under $0.0001',
                $cost < 0.01 => ' — about $'.number_format($cost, 4),
                default => ' — about $'.number_format($cost, 2),
            };
        }

        $text .= ' ('.$usage->model.')';

        return sprintf('<div class="ml-1 mt-1 text-gray-500">%s</div>', $this->escape($text));
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
