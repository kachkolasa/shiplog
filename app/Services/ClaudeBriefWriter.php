<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\Model;
use Anthropic\Messages\StopReason;
use App\Contracts\BriefWriter;
use App\Data\ClientBrief;
use Illuminate\Support\Collection;

class ClaudeBriefWriter implements BriefWriter
{
    private const string RULES = <<<'TXT'
        You turn a developer's git history into a short status update for a non-technical client.

        Rules:
        - Describe outcomes, not mechanics. "Clients can now filter invoices by date"
          not "added a scope to the Invoice model".
        - Never mention file paths, commit hashes, branch names, or line counts.
        - Group related commits into one item. Ten commits on one feature is one item.
        - Set certain=false for any item you inferred from file paths rather than from
          what the commit message actually stated.
        - If a commit's message is too vague to describe, put its subject line in
          `unclear`. Do not invent a plausible description. An omission is recoverable;
          a confident wrong claim to a client is not.
    TXT;

    public function __construct(private readonly Client $client) {}

    public function write(string $digest): ClientBrief
    {
        $message = $this->client->messages->create(
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => $digest]],
            model: Model::CLAUDE_OPUS_5_5,
            outputConfig: [
                'effort' => 'medium',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'headline' => ['type' => 'string'],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'detail' => ['type' => 'string'],
                                        'area' => ['type' => 'string'],
                                        'certain' => ['type' => 'boolean'],
                                    ],
                                    'required' => ['title', 'detail', 'area', 'certain'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'unclear' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['headline', 'items', 'unclear'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            system: [
                [
                    'type' => 'text',
                    'text' => self::RULES,
                    'cacheControl' => ['type' => 'ephemeral'],
                ],
            ],
        );

        if ($message->stopReason !== StopReason::END_TURN->value) {
            $this->resolveStopReason(StopReason::tryFrom($message->stopReason));
        }

        if (! is_array($message?->content)) {
            throw new \RuntimeException('Anthropic API response does not match the expected results.');
        }

        $text = Collection::make($message->content)
            ->filter(function ($item) {
                return $item->type === 'text';
            })->first();

        if (! $text->text) {
            throw new \RuntimeException('Anthropic API response does not match the expected results.');
        }

        return $this->format($text->text);
    }

    private function format(string $text): ClientBrief
    {
        $content = json_decode($text, true);

        return ClientBrief::fromArray($content);
    }

    private function resolveStopReason(StopReason $reason): void
    {
        $error = match ($reason) {
            StopReason::MAX_TOKENS => 'Reached the max tokens',
            StopReason::REFUSAL => 'The model refused to answer',
            StopReason::MODEL_CONTEXT_WINDOW_EXCEEDED => 'The model context window was exceeded',
            StopReason::STOP_SEQUENCE => 'The model stopped due to a stop sequence',
            StopReason::TOOL_USE => 'The model stopped due to a tool use',
            StopReason::PAUSE_TURN => 'The model paused the turn',
            default => 'Unknown stop reason',
        };

        throw new \RuntimeException("Anthropic API response stopped unexpectedly: {$error}");
    }
}
