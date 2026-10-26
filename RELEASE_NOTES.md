# v0.1.0 — first release

Turn a week of git history into a status update you can send a client.

`shiplog` reads the commits in a date range, sends them to Claude along with the
code that actually changed, and prints a short brief written for someone who
does not read diffs.

```
 Shiplog

 Claude now writes client-ready briefs from your git history
 Oct 19, 2026 – Oct 26, 2026

 ▪ Commit history collection for a date range
    The brief command now reads the repository's commit history between a
    configurable start and end date, filtered to the current author by default
    with an --all-authors switch to include everyone.

 ▪ Commit digest and terminal rendering (unverified)
    Commits are condensed into a compact digest for the model and the brief is
    rendered in the terminal. We could not verify the finished scope of this
    work from the commit itself.

 Too vague to describe — tighten these commit messages:
    command

 41,992 tokens in, 1,977 out — about $0.52 (claude-fable-5-1)
```

## Install

Download `shiplog` from this release, or:

```bash
git clone https://github.com/kachkolasa/shiplog.git
cd shiplog && composer install
```

Then:

```bash
shiplog setup
cd ~/some/project
shiplog brief
```

Requires **PHP 8.3+**, git, and an Anthropic API credential. The downloaded
binary is a PHAR — self-contained, but it still needs a PHP runtime.

## What's in it

**`shiplog brief`** — a client-ready summary of a date range.

- `--since` / `--until` take anything `strtotime` understands, defaulting to the
  last seven days. A bare date in `--until` is extended to end-of-day.
- `--all-authors` includes everyone's commits instead of just yours.
- `--audience`, `--tone` and `--max-items` override your saved settings for a
  single run, so one week can produce a technical brief for a CTO and a plain
  one for a founder.

**Diffs are sent by default.** The commit message is treated as a claim and the
diff as evidence; where they disagree, the diff wins. This is what stops a
commit message that overstates what was built from being repeated as fact to a
client. `--surface` skips the diffs for a much cheaper, unverified summary.

Diffs are included newest-first within a size budget. Commits that do not fit
are marked as having no diff, and anything written about them is flagged
`(unverified)` rather than quietly guessed at. Lockfiles, `vendor/` and
`node_modules/` are excluded from both the summary and the diffs.

**`shiplog setup`** — stores audience, tone, item cap and deep mode in
`~/.config/shiplog/config.json` at mode `0600`. `setup:info` shows the current
configuration and `setup:purge` deletes it.

**Credentials are never stored.** shiplog reads whichever credential the
Anthropic SDK already resolves — `ANTHROPIC_API_KEY`, `ANTHROPIC_AUTH_TOKEN`, or
an `ant auth login` profile. It reports which source it found, by name, and
never prints or copies the value.

**Every run reports what it cost**, in tokens and estimated dollars.

## Before you run it on a client's repository

In the default mode this sends your source diffs to a third-party API. That is
deliberate and it is what makes the output trustworthy, but check you are
permitted to before pointing it at someone else's code. `--surface` sends only
commit subjects, directory names and line counts.

## Known limitations

- The model is fixed at `claude-fable-5-1` and is not yet configurable.
- The brief is only as good as your commit messages. A one-word message produces
  nothing useful, and in `--surface` mode an overstated message is repeated as
  fact, because there is nothing to contradict it.
- Cost scales with how much you commit. A week of heavy work on this repository
  cost about $0.52; a quiet week costs cents.
- Merge commits are excluded.
- `setup:purge` deletes your configuration without asking for confirmation.
- Passing `--deep` and `--surface` together silently favours `--deep`.

## Verifying the download

Download `shiplog.sha256` alongside the binary, then:

```bash
shasum -a 256 -c shiplog.sha256
```
