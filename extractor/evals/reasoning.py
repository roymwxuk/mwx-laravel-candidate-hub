"""Compare reasoning efforts: how much time each one costs, and what it does to accuracy.

    cd extractor
    export AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_API_KEY=... AZURE_OPENAI_DEPLOYMENT=gpt-6-luna
    python -m evals.reasoning --efforts low,medium --runs 5

Runs the real extractor (same prompt, schema and validation as production); only
`reasoning.effort` is overridden. Makes billed API calls.

Efforts alternate in random order within each round, so a slow minute on Azure
hits all of them rather than whichever one happened to be running. SDK retries
are off: a retried call would look like a slow one.

See evals/README.md for how to add cases.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import random
import re
import statistics
import time
import unicodedata
from collections import Counter
from dataclasses import asdict, dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Any, Callable

from app.extractors.azure_openai import DEFAULT_DEPLOYMENT, AzureOpenAIExtractor, normalize_base_url
from app.pdf import clean, extract_text

HERE = Path(__file__).parent
CASES_DIR = HERE / "cases"
RESULTS_DIR = HERE / "results"
REQUEST_TIMEOUT = 120


# --- cases -----------------------------------------------------------------


@dataclass
class Case:
    name: str
    text: str
    expected: dict[str, Any]


def load_cases(directory: Path) -> list[Case]:
    """One case per `<name>.expected.json`, next to `<name>.txt` or `<name>.pdf`.

    "_source" (a path relative to the JSON file) points at a document elsewhere.
    """
    cases = []
    for path in sorted(directory.glob("*.expected.json")):
        expected = json.loads(path.read_text(encoding="utf-8"))
        name = path.name.removesuffix(".expected.json")
        source = expected.pop("_source", None)
        candidates = [directory / source] if source else [directory / f"{name}.txt", directory / f"{name}.pdf"]
        document = next((c for c in candidates if c.exists()), None)
        if document is None:
            raise FileNotFoundError(f"no document for {path.name}")
        data = document.read_bytes()
        text = extract_text(data) if document.suffix == ".pdf" else clean(data.decode("utf-8"))
        cases.append(Case(name, text, expected))
    return cases


# --- scoring ---------------------------------------------------------------


def norm(value: Any) -> Any:
    """Ignore width, case and spacing differences: they are not extraction errors."""
    if isinstance(value, str):
        return re.sub(r"\s+", "", unicodedata.normalize("NFKC", value)).lower()
    return value


def same(key: str, expected: Any, actual: Any) -> bool:
    if key == "phone" and isinstance(expected, str) and isinstance(actual, str):
        return re.sub(r"\D", "", norm(expected)) == re.sub(r"\D", "", norm(actual))
    return norm(expected) == norm(actual)


def score(expected: dict[str, Any], actual: dict[str, Any]) -> dict[str, bool]:
    """Check name → passed. Only keys present in `expected` are scored; null means "must be null"."""
    checks: dict[str, bool] = {}
    for key, want in expected.items():
        got = actual.get(key)
        if key == "work_histories":
            got = got or []
            checks["work_histories.count"] = len(got) == len(want)
            by_company = {norm(g["company"]): g for g in got}
            for entry in want:
                found = by_company.get(norm(entry["company"]))
                for sub, value in entry.items():
                    name = f"work_histories[{entry['company']}].{sub}"
                    checks[name] = found is not None and same(sub, value, found.get(sub))
        elif isinstance(want, list):
            # Wording varies ("英語（TOEIC 780点）"): each expected item must appear inside some entry.
            entries = [norm(g) for g in got or []]
            if not want:
                checks[key] = not entries
            for item in want:
                checks[f"{key}[{item}]"] = any(norm(item) in entry for entry in entries)
        else:
            checks[key] = same(key, want, got)
    return checks


# --- measuring -------------------------------------------------------------


class MeasuringResponses:
    """Stands in for `client.responses`: adds the effort, records time and token usage."""

    def __init__(self, responses: Any, effort: str) -> None:
        self.responses, self.effort = responses, effort
        self.seconds: float | None = None
        self.usage: Any = None

    def create(self, **kwargs: Any) -> Any:
        # Overrides any effort the extractor itself sets.
        kwargs["reasoning"] = {**(kwargs.get("reasoning") or {}), "effort": self.effort}
        start = time.perf_counter()
        try:
            response = self.responses.create(**kwargs)
        finally:
            self.seconds = time.perf_counter() - start
        self.usage = getattr(response, "usage", None)
        return response


class MeasuringClient:
    def __init__(self, responses: MeasuringResponses) -> None:
        self.responses = responses


@dataclass
class Run:
    case: str
    effort: str
    round: int
    seconds: float | None = None
    output_tokens: int | None = None
    reasoning_tokens: int | None = None
    error: str | None = None
    passed: int = 0
    total: int = 0
    failed_checks: list[str] = field(default_factory=list)


def run_once(client: Any, deployment: str, case: Case, effort: str, round_no: int) -> Run:
    responses = MeasuringResponses(client.responses, effort)
    run = Run(case.name, effort, round_no)
    actual: dict[str, Any] = {}
    try:
        result = AzureOpenAIExtractor(MeasuringClient(responses), deployment).extract(case.text)
        actual = result.fields.model_dump()
    except Exception as exc:
        # Type and HTTP status only: the message may echo resume content.
        status = getattr(exc, "status_code", None)
        run.error = type(exc).__name__ + (f" {status}" if status else "")

    run.seconds = responses.seconds
    run.output_tokens = getattr(responses.usage, "output_tokens", None)
    run.reasoning_tokens = getattr(getattr(responses.usage, "output_tokens_details", None), "reasoning_tokens", None)

    checks = score(case.expected, actual)
    run.total = len(checks)
    if run.error is None:  # a failed run gets no credit for fields that should be null
        run.passed = sum(checks.values())
        run.failed_checks = [name for name, ok in checks.items() if not ok]
    return run


def run_all(
    client: Any,
    deployment: str,
    cases: list[Case],
    efforts: list[str],
    runs: int,
    rng: random.Random,
    log: Callable[[str], None] = print,
) -> list[Run]:
    for effort in efforts:  # warm-up, not recorded: connection setup, first use of the schema
        warm = run_once(client, deployment, cases[0], effort, 0)
        if warm.error:
            log(f"warm-up {effort}: {warm.error}")

    results = []
    for round_no in range(1, runs + 1):
        plan = [(case, effort) for case in cases for effort in efforts]
        rng.shuffle(plan)
        for case, effort in plan:
            run = run_once(client, deployment, case, effort, round_no)
            results.append(run)
            log(
                f"[{round_no}/{runs}] {case.name:<16} {effort:<7} {run.seconds or 0:6.2f}s "
                f"reasoning={run.reasoning_tokens} {run.passed}/{run.total} {run.error or ''}".rstrip()
            )
    return results


# --- reporting -------------------------------------------------------------


def percentile(values: list[float], q: float) -> float:
    """Nearest-rank percentile."""
    ordered = sorted(values)
    return ordered[max(0, math.ceil(len(ordered) * q / 100) - 1)]


def median_of(runs: list[Run], attribute: str) -> float | None:
    values = [v for r in runs if r.error is None and (v := getattr(r, attribute)) is not None]
    return statistics.median(values) if values else None


def fmt(value: float | None, pattern: str) -> str:
    return "-" if value is None else format(value, pattern)


def report(results: list[Run], efforts: list[str]) -> str:
    by = {e: [r for r in results if r.effort == e] for e in efforts}
    lines = [
        "| effort | runs | errors | median s | p95 s | output tok | reasoning tok | accuracy |",
        "|---|---|---|---|---|---|---|---|",
    ]
    for effort, runs in by.items():
        times = [r.seconds for r in runs if r.error is None and r.seconds is not None]
        total = sum(r.total for r in runs)
        accuracy = sum(r.passed for r in runs) / total if total else None
        lines.append(
            f"| {effort} | {len(runs)} | {sum(r.error is not None for r in runs)} "
            f"| {fmt(median_of(runs, 'seconds'), '.2f')} | {fmt(percentile(times, 95) if times else None, '.2f')} "
            f"| {fmt(median_of(runs, 'output_tokens'), '.0f')} | {fmt(median_of(runs, 'reasoning_tokens'), '.0f')} "
            f"| {fmt(accuracy, '.1%')} |"
        )

    lines += ["", "Median seconds per case:", "", "| case | " + " | ".join(efforts) + " |"]
    lines.append("|---|" + "---|" * len(efforts))
    for case in sorted({r.case for r in results}):
        cells = [fmt(median_of([r for r in by[e] if r.case == case], "seconds"), ".2f") for e in efforts]
        lines.append(f"| {case} | " + " | ".join(cells) + " |")

    for effort, runs in by.items():
        missed = Counter((r.case, name) for r in runs for name in r.failed_checks)
        errors = Counter(r.error for r in runs if r.error)
        if missed or errors:
            lines += ["", f"{effort}:"]
            lines += [f"- error: {name} ×{n}" for name, n in errors.most_common()]
            lines += [f"- missed: {case} / {name} ×{n}" for (case, name), n in missed.most_common()]
    return "\n".join(lines)


def main() -> None:
    parser = argparse.ArgumentParser(description="Compare reasoning efforts on the resume extractor.")
    parser.add_argument("--efforts", default="low,medium", help="comma separated (default: low,medium)")
    parser.add_argument("--runs", type=int, default=5, help="runs per case and effort (default: 5)")
    parser.add_argument("--cases", type=Path, default=CASES_DIR, help="directory of cases")
    parser.add_argument("--seed", type=int, default=0, help="order of the calls")
    args = parser.parse_args()

    endpoint, api_key = os.getenv("AZURE_OPENAI_ENDPOINT"), os.getenv("AZURE_OPENAI_API_KEY")
    if not endpoint or not api_key:
        parser.error("set AZURE_OPENAI_ENDPOINT and AZURE_OPENAI_API_KEY")
    deployment = os.getenv("AZURE_OPENAI_DEPLOYMENT", DEFAULT_DEPLOYMENT)
    efforts = [e.strip() for e in args.efforts.split(",") if e.strip()]
    cases = load_cases(args.cases)
    if not cases or not efforts or args.runs < 1:
        parser.error("need at least one case, one effort and one run")

    from openai import OpenAI

    client = OpenAI(api_key=api_key, base_url=normalize_base_url(endpoint), timeout=REQUEST_TIMEOUT, max_retries=0)
    calls = len(efforts) * (len(cases) * args.runs + 1)
    print(f"{deployment}: {len(cases)} cases × {len(efforts)} efforts × {args.runs} runs (+ warm-up) = {calls} calls\n")

    results = run_all(client, deployment, cases, efforts, args.runs, random.Random(args.seed))

    RESULTS_DIR.mkdir(exist_ok=True)
    out = RESULTS_DIR / f"reasoning-{datetime.now():%Y%m%d-%H%M%S}.jsonl"
    out.write_text("".join(json.dumps(asdict(r), ensure_ascii=False) + "\n" for r in results), encoding="utf-8")
    print("\n" + report(results, efforts) + f"\n\nRaw runs: {out}")


if __name__ == "__main__":
    main()
