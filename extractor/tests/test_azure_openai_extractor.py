"""AzureOpenAIExtractor with a stubbed client: checks the parsing/validation, not the model."""

from __future__ import annotations

import json
from types import SimpleNamespace

import pytest

from app.extractors import build_extractor
from app.extractors.azure_openai import (
    AzureOpenAIExtractor,
    normalize_base_url,
    response_schema,
)
from app.extractors.common import MAX_INPUT_CHARS, ExtractionError


class StubResponses:
    def __init__(self, output_text: str, status: str = "completed"):
        self.output_text = output_text
        self.status = status
        self.calls = []

    def create(self, **kwargs):
        self.calls.append(kwargs)
        return SimpleNamespace(status=self.status, output_text=self.output_text)


def make(payload, status: str = "completed") -> tuple[AzureOpenAIExtractor, StubResponses]:
    text = payload if isinstance(payload, str) else json.dumps(payload, ensure_ascii=False)
    responses = StubResponses(text, status)
    return AzureOpenAIExtractor(SimpleNamespace(responses=responses), "test-deployment"), responses


def test_parses_structured_output_into_the_contract():
    extractor, responses = make({
        "name": "架空 太郎",
        "name_kana": None,
        "email": "kakuu@example.com",
        "phone": None,
        "summary": None,
        "skills": ["PHP"],
        "work_histories": [{
            "company": "A社", "title": None, "started_on": "2020-04",
            "ended_on": None, "description": None,
        }],
        "warnings": ["ふりがなが記載されていません"],
    })

    result = extractor.extract("履歴書テキスト")

    assert result.extractor == "azure-openai:test-deployment"
    assert result.fields.name == "架空 太郎"
    assert result.fields.work_histories[0].company == "A社"
    assert result.warnings == ["ふりがなが記載されていません"]
    call = responses.calls[0]
    assert call["model"] == "test-deployment"
    assert call["reasoning"] == {"effort": "low"}
    assert call["text"]["format"]["type"] == "json_schema"
    assert call["text"]["format"]["strict"] is True


def test_schema_is_strict_compatible():
    schema = response_schema()

    def objects(node):
        if isinstance(node, dict):
            if node.get("type") == "object" and "properties" in node:
                yield node
            for value in node.values():
                yield from objects(value)
        elif isinstance(node, list):
            for item in node:
                yield from objects(item)

    found = list(objects(schema))
    assert len(found) >= 2  # ResumeFields + WorkHistory
    for obj in found:
        assert obj["additionalProperties"] is False
        assert set(obj["required"]) == set(obj["properties"])
    assert "default" not in json.dumps(schema)
    assert "warnings" in schema["required"]


def test_rejects_output_that_does_not_match_the_schema():
    extractor, _ = make({"work_histories": [{"title": "会社名がない"}]})

    with pytest.raises(ExtractionError):
        extractor.extract("text")


def test_rejects_non_json_output_such_as_a_refusal():
    extractor, _ = make("")

    with pytest.raises(ExtractionError):
        extractor.extract("text")


def test_rejects_an_incomplete_response():
    extractor, _ = make({}, status="incomplete")

    with pytest.raises(ExtractionError):
        extractor.extract("text")


def test_truncates_very_long_documents_and_says_so():
    extractor, responses = make({})

    result = extractor.extract("あ" * (MAX_INPUT_CHARS + 100))

    assert any("先頭部分" in w for w in result.warnings)
    assert len(responses.calls[0]["input"]) < MAX_INPUT_CHARS + 100


@pytest.mark.parametrize("endpoint", [
    "https://example.cognitiveservices.azure.com",
    "https://example.cognitiveservices.azure.com/",
    "https://example.cognitiveservices.azure.com/openai/v1",
    "https://example.cognitiveservices.azure.com/openai/v1/",
])
def test_normalizes_the_endpoint(endpoint):
    assert normalize_base_url(endpoint) == "https://example.cognitiveservices.azure.com/openai/v1/"


def test_provider_switch_requires_credentials(monkeypatch):
    monkeypatch.setenv("EXTRACTOR_PROVIDER", "azure_openai")
    monkeypatch.delenv("AZURE_OPENAI_ENDPOINT", raising=False)
    monkeypatch.delenv("AZURE_OPENAI_API_KEY", raising=False)

    with pytest.raises(RuntimeError, match="AZURE_OPENAI_ENDPOINT"):
        build_extractor()


def test_provider_switch_builds_the_azure_extractor(monkeypatch):
    monkeypatch.setenv("EXTRACTOR_PROVIDER", "azure_openai")
    monkeypatch.setenv("AZURE_OPENAI_ENDPOINT", "https://example.cognitiveservices.azure.com/")
    monkeypatch.setenv("AZURE_OPENAI_API_KEY", "test-key")
    monkeypatch.setenv("AZURE_OPENAI_DEPLOYMENT", "my-luna")

    extractor = build_extractor()

    assert extractor.name == "azure-openai:my-luna"
