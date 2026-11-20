"""ClaudeExtractor with a stubbed client: checks the parsing/validation, not the model."""

from __future__ import annotations

from types import SimpleNamespace

import pytest

from app.extractors.claude import MAX_INPUT_CHARS, ClaudeExtractor, ExtractionError


class StubMessages:
    def __init__(self, content):
        self.content = content
        self.calls = []

    def create(self, **kwargs):
        self.calls.append(kwargs)
        return SimpleNamespace(content=self.content)


def make(content) -> tuple[ClaudeExtractor, StubMessages]:
    messages = StubMessages(content)
    return ClaudeExtractor(SimpleNamespace(messages=messages), "test-model"), messages


def tool_use(payload):
    return SimpleNamespace(type="tool_use", input=payload)


def test_parses_forced_tool_call_into_the_contract():
    extractor, messages = make([tool_use({
        "name": "架空 太郎",
        "email": "kakuu@example.com",
        "skills": ["PHP"],
        "work_histories": [{"company": "A社", "started_on": "2020-04"}],
        "warnings": ["ふりがなが記載されていません"],
    })])

    result = extractor.extract("履歴書テキスト")

    assert result.extractor == "claude:test-model"
    assert result.fields.name == "架空 太郎"
    assert result.fields.work_histories[0].company == "A社"
    assert result.warnings == ["ふりがなが記載されていません"]
    call = messages.calls[0]
    assert call["tool_choice"] == {"type": "tool", "name": "record_resume"}
    assert "work_histories" in call["tools"][0]["input_schema"]["properties"]


def test_rejects_output_that_does_not_match_the_schema():
    extractor, _ = make([tool_use({"work_histories": [{"title": "会社名がない"}]})])

    with pytest.raises(ExtractionError):
        extractor.extract("text")


def test_rejects_a_reply_without_a_tool_call():
    extractor, _ = make([SimpleNamespace(type="text", text="すみません")])

    with pytest.raises(ExtractionError):
        extractor.extract("text")


def test_truncates_very_long_documents_and_says_so():
    extractor, messages = make([tool_use({})])

    result = extractor.extract("あ" * (MAX_INPUT_CHARS + 100))

    assert any("先頭部分" in w for w in result.warnings)
    assert len(messages.calls[0]["messages"][0]["content"]) < MAX_INPUT_CHARS + 100
