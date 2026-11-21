"""LLM extractor using an Azure OpenAI / Foundry deployment (e.g. gpt-6-luna).

Uses the Responses API with a strict JSON-schema output format, so the reply is
JSON shaped like our contract. Pydantic validates it again and the Laravel side
sanitizes it a third time: LLM output is untrusted.

Call the *resource* endpoint (…/openai/v1/), not the Foundry project endpoint:
gpt-6-luna has returned 500s on the project route (Azure/azure-sdk-for-python#49169).
"""

from __future__ import annotations

import copy
import json
import os
from typing import Any

from openai import OpenAI
from pydantic import ValidationError

from app.extractors.common import SYSTEM_PROMPT, ExtractionError, truncate
from app.schemas import ExtractionResponse, ResumeFields

DEFAULT_DEPLOYMENT = "gpt-6-luna"
MAX_OUTPUT_TOKENS = 4096


def strict_schema(schema: dict[str, Any]) -> dict[str, Any]:
    """Adapt a Pydantic JSON schema to strict structured outputs.

    Strict mode needs every object to list all of its properties as required and
    to forbid extra keys, and it does not accept `default`. Optional fields stay
    optional by being nullable (Pydantic already emits `anyOf: [..., null]`).
    """
    schema = copy.deepcopy(schema)

    def visit(node: Any) -> None:
        if isinstance(node, dict):
            node.pop("default", None)
            if node.get("type") == "object" and "properties" in node:
                node["required"] = list(node["properties"])
                node["additionalProperties"] = False
            for value in node.values():
                visit(value)
        elif isinstance(node, list):
            for item in node:
                visit(item)

    visit(schema)
    return schema


def response_schema() -> dict[str, Any]:
    schema = ResumeFields.model_json_schema()
    schema["properties"]["warnings"] = {"type": "array", "items": {"type": "string"}}
    return strict_schema(schema)


def normalize_base_url(endpoint: str) -> str:
    """Accept either the bare resource URL or one already ending in /openai/v1."""
    endpoint = endpoint.rstrip("/")
    if not endpoint.endswith("/openai/v1"):
        endpoint += "/openai/v1"
    return endpoint + "/"


class AzureOpenAIExtractor:
    def __init__(self, client: OpenAI, deployment: str) -> None:
        self.client = client
        self.deployment = deployment
        self.name = f"azure-openai:{deployment}"

    @classmethod
    def from_env(cls) -> "AzureOpenAIExtractor":
        endpoint = os.getenv("AZURE_OPENAI_ENDPOINT")
        api_key = os.getenv("AZURE_OPENAI_API_KEY")
        if not endpoint or not api_key:
            raise RuntimeError(
                "EXTRACTOR_PROVIDER=azure_openai requires AZURE_OPENAI_ENDPOINT and AZURE_OPENAI_API_KEY"
            )
        client = OpenAI(api_key=api_key, base_url=normalize_base_url(endpoint))
        return cls(client, os.getenv("AZURE_OPENAI_DEPLOYMENT", DEFAULT_DEPLOYMENT))

    def extract(self, text: str) -> ExtractionResponse:
        text, warnings = truncate(text)

        response = self.client.responses.create(
            model=self.deployment,
            instructions=SYSTEM_PROMPT,
            input=f"<resume>\n{text}\n</resume>",
            max_output_tokens=MAX_OUTPUT_TOKENS,
            text={
                "format": {
                    "type": "json_schema",
                    "name": "record_resume",
                    "description": "抽出した履歴書の内容",
                    "schema": response_schema(),
                    "strict": True,
                }
            },
        )

        if getattr(response, "status", None) != "completed":
            raise ExtractionError(f"モデルの応答が完了しませんでした（status={getattr(response, 'status', None)}）。")

        raw = getattr(response, "output_text", "") or ""
        try:
            payload = json.loads(raw)
        except json.JSONDecodeError as exc:
            # A refusal also lands here: it carries no output_text.
            raise ExtractionError("モデルが構造化データを返しませんでした。") from exc
        if not isinstance(payload, dict):
            raise ExtractionError("モデルが構造化データを返しませんでした。")

        model_warnings = payload.pop("warnings", []) or []

        try:
            fields = ResumeFields.model_validate(payload)
        except ValidationError as exc:
            raise ExtractionError(f"モデルの出力がスキーマに一致しません: {exc.error_count()}件のエラー") from exc

        return ExtractionResponse(
            extractor=self.name,
            fields=fields,
            warnings=warnings + [w for w in model_warnings if isinstance(w, str)],
        )
