<?php

namespace App\Services;

final class BriefPrompt
{
    private const string BASE = <<<'TXT'
        You turn a developer's git history into a short status update for a client.

        Rules:
        - Describe outcomes, not mechanics. "Clients can now filter invoices by date"
          not "added a scope to the Invoice model".
        - Never mention file paths, commit hashes, branch names, or line counts.
        - Group related commits into one item. Ten commits on one feature is one item.
        - The headline is one short line of at most ten words, naming the single
          most important change. It is not a summary of the items below it.
        - `certain` is your confidence that an item is true, not that you read it
          somewhere. Set it to false when you worked out what happened from the
          file areas rather than the commit message, when a message claims a
          capability but you cannot tell whether it was finished, or when you
          added any detail the message did not state. A message that plainly
          describes a finished change is certain=true.
        - If a commit's message is too vague to describe, put its subject line in
          `unclear`. Do not invent a plausible description. An omission is recoverable;
          a confident wrong claim to a client is not.
        TXT;

    private const string EVIDENCE = <<<'TXT'
        Evidence: each commit below is followed by its code diff. The diff is the
        evidence and the commit message is only a claim. Where the two disagree the
        diff is right: do not repeat a capability the message claims if the diff
        does not show it being built, and describe what the diff actually does
        instead. A commit marked "(no diff included for this commit)" has nothing
        behind it, so any item drawn from it is certain=false.
        TXT;

    private const int MIN_ITEMS = 1;

    private const int MAX_ITEMS = 20;

    public function __construct(private readonly Settings $settings) {}

    public function build(bool $deep = false): string
    {
        return implode("\n\n", array_filter([
            self::BASE,
            $deep ? self::EVIDENCE : null,
            $this->audience(),
            $this->tone(),
            $this->length(),
        ]));
    }

    private function audience(): string
    {
        return match ($this->settings->get('audience')) {
            'technical' => 'Audience: the reader is technical, a CTO or an engineering lead. '
                .'You may name areas of the product and assume they understand software terms. '
                .'Still describe what changed for users, not how it was built.',
            default => 'Audience: the reader is not technical, a founder or a client contact. '
                .'Use no jargon and no library, tool or component names. '
                .'Say what they can now do.',
        };
    }

    private function tone(): string
    {
        return match ($this->settings->get('tone')) {
            'neutral' => 'Tone: write neutrally, without saying who did the work. '
                .'"Date filtering is now available."',
            default => 'Tone: write as the team reporting on its own work. '
                .'"We added date filtering."',
        };
    }

    private function length(): string
    {
        $configured = $this->settings->get('max_items');
        $max = is_numeric($configured)
            ? (int) $configured
            : Settings::DEFAULTS['max_items'];
        $max = max(self::MIN_ITEMS, min(self::MAX_ITEMS, $max));

        return "Length: produce at most {$max} items. If there is more work than that, "
            .'merge the smallest changes into the item they relate to most closely '
            .'rather than leaving them out.';
    }
}
