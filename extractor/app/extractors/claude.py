"""LLM extractor using Claude tool use.

The model is forced to call a single tool whose input schema *is* our response
schema, so we get JSON that already matches the contract. Pydantic then
validates it again; the Laravel side sanitizes it a third time. Defense in depth,
because LLM output is untrusted input.
"""

from __future__ import annotations

import os

from anthropic import Anthropic
from pydantic import ValidationError

from app.extractors.common import MAX_INPUT_CHARS, SYSTEM_PROMPT, ExtractionError, truncate
from app.schemas import ExtractionResponse, ResumeFields

__all__ = ["MAX_INPUT_CHARS", "ClaudeExtractor", "ExtractionError"]


class ClaudeExtractor:
    def __init__(self, client: Anthropic, model: str) -> None:
        self.client = client
        self.model = model
        self.name = f"claude:{model}"

    @classmethod
    def from_env(cls) -> "ClaudeExtractor":
        if not os.getenv("ANTHROPIC_API_KEY"):
            raise RuntimeError("EXTRACTOR_PROVIDER=claude requires ANTHROPIC_API_KEY")
        return cls(Anthropic(), os.getenv("ANTHROPIC_MODEL", "claude-haiku-5-5"))

    def extract(self, text: str) -> ExtractionResponse:
        text, warnings = truncate(text)

        tool_schema = ResumeFields.model_json_schema()
        tool_schema["properties"]["warnings"] = {"type": "array", "items": {"type": "string"}}

        message = self.client.messages.create(
            model=self.model,
            max_tokens=4096,
            system=SYSTEM_PROMPT,
            tools=[{
                "name": "record_resume",
                "description": "抽出した履歴書の内容を記録する",
                "input_schema": tool_schema,
            }],
            tool_choice={"type": "tool", "name": "record_resume"},
            messages=[{"role": "user", "content": f"<resume>\n{text}\n</resume>"}],
        )

        tool_input = next((b.input for b in message.content if b.type == "tool_use"), None)
        if not isinstance(tool_input, dict):
            raise ExtractionError("モデルが構造化データを返しませんでした。")

        model_warnings = tool_input.pop("warnings", []) or []

        try:
            fields = ResumeFields.model_validate(tool_input)
        except ValidationError as exc:
            raise ExtractionError(f"モデルの出力がスキーマに一致しません: {exc.error_count()}件のエラー") from exc

        return ExtractionResponse(
            extractor=self.name,
            fields=fields,
            warnings=warnings + [w for w in model_warnings if isinstance(w, str)],
        )
