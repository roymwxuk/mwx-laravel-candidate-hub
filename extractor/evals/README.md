# Reasoning effort eval

Times `reasoning.effort` settings against each other on the real extractor, and checks
that the faster one still reads resumes correctly. **Makes billed API calls.**

```sh
cd extractor
export AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_API_KEY=... AZURE_OPENAI_DEPLOYMENT=gpt-6-luna
python -m evals.reasoning --efforts low --runs 1           # 7 calls: does Azure accept the setting?
python -m evals.reasoning --efforts low,medium --runs 5    # 62 calls: the comparison
python -m evals.reasoning --efforts none,low,medium        # 93 calls: also try no reasoning
```

`medium` is the model's documented default, so it stands in for today's behaviour.

## Reading the result

- **median s / p95 s**: seconds in the Azure call only (no PDF, queue or polling time). Failed runs are
  excluded. p95 is the nearest rank, so with few runs it is close to the maximum.
- **accuracy**: share of checks passed over all runs. A failed run scores 0, so a fast setting cannot win
  by erroring.
- **missed**: which fields went wrong, and how often. Wrong in every run is a real regression (or a wrong
  expectation); wrong once is more likely noise.

Pick the lowest effort whose accuracy matches `medium`.

## Adding cases

Put `<name>.txt` or `<name>.pdf` in `cases/` with `<name>.expected.json`. Only the keys listed are scored,
and `null` means the field must stay empty. For lists, each expected item must appear inside some returned
entry. `summary`, `title` and `description` are free text: leave them out.

Use fictional resumes. Real ones are personal data and are sent to Azure: keep them outside the repo and
pass `--cases <directory>`. Results hold check names only, but those include company names and list items
from the expected file.
