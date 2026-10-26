# shiplog

Turn a week of git history into a status update you can send a client.

`shiplog` reads the commits in a date range, sends them to Claude along with the
code that actually changed, and prints a short brief written for a human who
does not read diffs.

```
 Shiplog

 Claude now writes client-ready briefs from your git history
 Oct 19, 2026 – Oct 26, 2026

 ▪ Commit history collection for a date range
    The brief command now reads the repository's commit history between a
    configurable start and end date, filtered to the current author by default
    with an --all-authors switch to include everyone.

 ▪ Setup command with persisted preferences and credential check
    A new setup command asks for audience, tone and a maximum item count, and
    saves them to a per-user config file with locked-down permissions.

 ▪ Commit digest and terminal rendering (unverified)
    Commits are condensed into a compact digest for the model and the resulting
    brief is rendered in the terminal. We could not verify the finished scope of
    this work from the commit itself.

 Too vague to describe — tighten these commit messages:
    command

 41,992 tokens in, 1,977 out — about $0.52 (claude-fable-5-1)
```

## Requirements

- PHP 8.3 or newer
- git
- An Anthropic API credential

## Install

### With Composer

```bash
composer global require kachkolasa/shiplog
```

Composer puts the `shiplog` command in its global bin directory. If your shell
cannot find it afterwards, that directory is not on your `PATH`:

```bash
composer global config bin-dir --absolute   # prints the directory
export PATH="$PATH:$(composer global config bin-dir --absolute)"
```

### As a standalone binary

Download `shiplog` and `shiplog.sha256` from the
[latest release](https://github.com/kachkolasa/shiplog/releases/latest), then:

```bash
shasum -a 256 -c shiplog.sha256
chmod +x shiplog
mv shiplog /usr/local/bin/
```

Self-contained, but it still needs a PHP runtime — a phar is not a static
binary. Nothing else is installed, which is the main advantage over Composer:
no transitive dependencies land in your global bin.

### Then

```bash
shiplog setup
cd ~/some/project
shiplog brief
```

## Credentials

**shiplog never stores your API key.** It reads whichever credential the
Anthropic SDK already resolves, so there is only ever one copy of your key on
the machine. Set one up either way:

```bash
ant auth login                      # stores a profile the SDK reads automatically
export ANTHROPIC_API_KEY=sk-ant-... # or put this in your shell profile
```

`shiplog setup` reports which source it found, by name, and never prints the
value.

## Usage

```bash
shiplog brief                              # the last 7 days, your commits
shiplog brief --since="last monday"
shiplog brief --since=2026-10-01 --until=2026-10-31
shiplog brief --all-authors                # everyone, not just you
shiplog brief --audience=technical         # override the saved settings
shiplog brief --surface                    # skip the diffs: much cheaper
```

Run it from inside the repository you want a brief for.

### Options

| Option | Default | What it does |
|---|---|---|
| `--since` | `7 days ago` | Start of the range. Anything `strtotime` understands. |
| `--until` | `now` | End of the range. A bare date is extended to end-of-day. |
| `--all-authors` | off | Include everyone's commits, not just yours. |
| `--deep` | **on** | Send the code diffs so claims can be checked against what changed. |
| `--surface` | off | Skip the diffs. Much cheaper and faster, but claims cannot be checked. |
| `--audience` | from config | `technical` or `non-technical`. |
| `--tone` | from config | `first-person` ("We added…") or `neutral` ("… is now available"). |
| `--max-items` | from config | 1–20. Work over the cap is merged, never dropped. |

## What gets sent

In the default deep mode, shiplog sends **the actual code diffs** for the
commits in range. That is the point: it is what lets the brief contradict a
commit message that overstates what was built.

Excluded from both the summary and the diffs:

```
composer.lock   package-lock.json   yarn.lock   pnpm-lock.yaml
vendor/         node_modules/
```

Commit hashes, file paths and line counts are never repeated in the output.

If you cannot send client code to a third party, use `--surface`, or set
`deep: false` in your config — see the accuracy note below before you do.

## Cost

shiplog spends your money on every run and prints what it spent.

| Mode | This repo, one week | Why |
|---|---|---|
| `--surface` | a fraction of a cent | ~180 tokens: subjects, directories and line counts |
| default (deep) | about $0.52 | ~42,000 tokens: the above plus the diffs |

**This scales with how much you commit.** A quiet week costs less, a busy one
more. Diffs are included newest-first up to a size budget; commits past the
budget are marked as having no diff, and anything written about them is flagged
as unverified rather than silently guessed at.

## Configuration

`shiplog setup` writes `~/.config/shiplog/config.json` (or
`$XDG_CONFIG_HOME/shiplog/`), mode `0600`:

```json
{
    "audience": "non-technical",
    "tone": "first-person",
    "max_items": 6,
    "deep": true
}
```

```bash
shiplog setup                      # interactive
shiplog setup --audience=technical --tone=neutral --max-items=4 --deep=true
shiplog setup:info                 # show the current configuration
shiplog setup:purge                # delete it
```

No credential is ever written to this file.

## Accuracy, and its limits

Two markers in the output exist because a brief that reads well but is wrong is
worse than no brief at all:

- **`(unverified)`** — the item could not be checked against the code. Either it
  came from a commit whose diff did not fit the budget, or the commit message
  claims something the diff does not show.
- **"Too vague to describe"** — the commit message carried no usable
  information. shiplog lists the subject rather than inventing a description.

A one-word commit message produces nothing useful, and in `--surface` mode a
commit message that *overstates* what was built will be repeated as fact,
because there is nothing to contradict it. Deep mode exists for exactly that
case. Read the brief before you send it.

## Development

```bash
git clone https://github.com/kachkolasa/shiplog.git
cd shiplog && composer install
./shiplog brief
```

```bash
vendor/bin/pest          # 95 tests, no network, no API key needed
vendor/bin/pint          # code style
```

To compile the standalone binary yourself:

```bash
./shiplog app:build --build-version=v0.1.2 --env=development
```

`--env=development` is required: `config/app.php` sets the environment to
production so that an installed copy does not list the framework's scaffolding
commands, and `app:build` is one of the commands that hides.

The one class that calls the API sits behind the `BriefWriter` interface, and
the test suite binds a fake in its place — so the suite never makes a request
and never needs a credential.

## Notes

- The model is currently fixed at `claude-fable-5-1` and is not yet configurable.
- Merge commits are excluded.
