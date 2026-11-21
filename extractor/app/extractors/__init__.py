from __future__ import annotations

import os
from typing import Protocol

from app.schemas import ExtractionResponse


class Extractor(Protocol):
    name: str

    def extract(self, text: str) -> ExtractionResponse: ...


def build_extractor() -> Extractor:
    """Pick the backend from EXTRACTOR_PROVIDER (mock | azure_openai)."""
    provider = os.getenv("EXTRACTOR_PROVIDER", "mock").lower()

    if provider == "azure_openai":
        from app.extractors.azure_openai import AzureOpenAIExtractor

        return AzureOpenAIExtractor.from_env()

    from app.extractors.mock import RuleBasedExtractor

    return RuleBasedExtractor()
