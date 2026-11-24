"""The reasoning eval, with a stubbed client: checks scoring and measuring, not the model."""

from __future__ import annotations

import json
import random
from types import SimpleNamespace

import pytest

from evals.reasoning import CASES_DIR, Case, load_cases, percentile, report, run_all, run_once, score

USAGE = SimpleNamespace(output_tokens=300, output_tokens_details=SimpleNamespace(reasoning_tokens=200))


class StubResponses:
    def __init__(self, payload=None, error: Exception | None = None):
        self.payload, self.error, self.calls = payload or {}, error, []

    def create(self, **kwargs):
        self.calls.append(kwargs)
        if self.error:
            raise self.error
        return SimpleNamespace(status="completed", output_text=json.dumps(self.payload), usage=USAGE)


def client(**kwargs):
    return SimpleNamespace(responses=StubResponses(**kwargs))


def test_bundled_cases_load_and_score_full_marks_against_themselves():
    cases = load_cases(CASES_DIR)

    assert len(cases) == 6
    for case in cases:
        assert case.text
        assert all(score(case.expected, case.expected).values()), case.name


def test_scoring_ignores_spacing_and_width_but_not_content():
    assert score({"name": "架空 太郎"}, {"name": "架空太郎"}) == {"name": True}
    assert score({"address": "見本1-2-3"}, {"address": "見本１－２－３"}) == {"address": True}
    assert score({"phone": "090-1234-5678"}, {"phone": "09012345678"}) == {"phone": True}
    assert score({"name": "架空 太郎"}, {"name": "架空 次郎"}) == {"name": False}


def test_null_means_the_field_must_stay_empty():
    assert score({"gender": None}, {"gender": "female"}) == {"gender": False}
    assert score({"languages": []}, {"languages": ["英語"]}) == {"languages": False}


def test_work_histories_match_by_company_in_any_order():
    want = {"work_histories": [{"company": "A社", "ended_on": None}, {"company": "B社", "ended_on": "2020-01"}]}

    checks = score(want, {"work_histories": [{"company": "B社", "ended_on": "2020-02"}]})

    assert checks["work_histories.count"] is False
    assert checks["work_histories[A社].ended_on"] is False  # missing company, not a correct null
    assert checks["work_histories[B社].company"] is True
    assert checks["work_histories[B社].ended_on"] is False


def test_sends_the_effort_on_the_production_request_and_records_usage():
    c = client(payload={"name": "架空 太郎"})

    run = run_once(c, "luna", Case("x", "text", {"name": "架空 太郎"}), "high", 1)

    call = c.responses.calls[0]
    assert call["reasoning"] == {"effort": "high"}  # overrides the production setting
    assert call["text"]["format"]["strict"] is True
    assert (run.output_tokens, run.reasoning_tokens, run.passed, run.total) == (300, 200, 1, 1)
    assert run.seconds is not None and run.error is None


def test_a_failed_run_scores_zero_and_does_not_leak_the_message():
    run = run_once(client(error=RuntimeError("架空 太郎")), "luna", Case("x", "t", {"gender": None}), "low", 1)

    assert (run.passed, run.total, run.error) == (0, 1, "RuntimeError")


def test_every_effort_gets_the_same_number_of_runs_plus_an_unrecorded_warm_up():
    c = client(payload={"name": "a"})
    cases = [Case("x", "t", {"name": "a"}), Case("y", "t", {"name": "b"})]

    results = run_all(c, "luna", cases, ["low", "medium"], 3, random.Random(0), log=lambda _: None)

    assert len(results) == 12
    assert len(c.responses.calls) == 14
    assert sum(r.effort == "low" for r in results) == 6
    text = report(results, ["low", "medium"])
    assert "| low | 6 | 0 |" in text and "50.0%" in text
    assert "missed: y / name ×3" in text


def test_report_survives_an_effort_that_always_fails():
    results = run_all(
        client(error=RuntimeError()), "luna", [Case("x", "t", {"name": "a"})], ["none"], 2,
        random.Random(0), log=lambda _: None,
    )

    assert "| none | 2 | 2 | - | - |" in report(results, ["none"])


@pytest.mark.parametrize("q, expected", [(50, 2.0), (95, 4.0), (100, 4.0)])
def test_percentile_is_nearest_rank(q, expected):
    assert percentile([4.0, 1.0, 3.0, 2.0], q) == expected
