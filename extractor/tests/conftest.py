from __future__ import annotations

from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app.main import app, get_extractor

FIXTURES = Path(__file__).parent / "fixtures"


@pytest.fixture
def sample_pdf() -> bytes:
    return (FIXTURES / "sample_resume.pdf").read_bytes()


@pytest.fixture
def client(monkeypatch: pytest.MonkeyPatch) -> TestClient:
    monkeypatch.delenv("EXTRACTOR_TOKEN", raising=False)
    monkeypatch.setenv("EXTRACTOR_PROVIDER", "mock")
    get_extractor.cache_clear()
    app.dependency_overrides.clear()
    yield TestClient(app)
    app.dependency_overrides.clear()
