from __future__ import annotations

from app.main import app, get_extractor
from app.schemas import ExtractionResponse


def upload(client, data: bytes, **headers):
    return client.post("/extract", files={"file": ("resume.pdf", data, "application/pdf")}, headers=headers)


def test_health(client):
    assert client.get("/health").json() == {"status": "ok", "extractor": "mock-rules-v1"}


def test_extracts_a_text_pdf_end_to_end(client, sample_pdf):
    res = upload(client, sample_pdf)

    assert res.status_code == 200
    body = ExtractionResponse.model_validate(res.json())  # contract check
    assert body.schema_version == "1"
    assert body.fields.name == "架空 太郎"
    assert body.fields.email == "kakuu.taro@example.com"
    assert len(body.fields.work_histories) == 2


def test_rejects_non_pdf_uploads(client):
    res = upload(client, b"hello, I am not a pdf")

    assert res.status_code == 422
    assert "PDF" in res.json()["detail"]


def test_rejects_oversized_uploads(client):
    res = upload(client, b"%PDF" + b"0" * (5 * 1024 * 1024))

    assert res.status_code == 413


def test_requires_the_internal_token_when_configured(client, sample_pdf, monkeypatch):
    monkeypatch.setenv("EXTRACTOR_TOKEN", "secret")

    assert upload(client, sample_pdf).status_code == 401
    assert upload(client, sample_pdf, **{"X-Internal-Token": "wrong"}).status_code == 401
    assert upload(client, sample_pdf, **{"X-Internal-Token": "secret"}).status_code == 200


def test_backend_failures_become_502_without_leaking_details(client, sample_pdf):
    class Broken:
        name = "broken"

        def extract(self, text):
            raise RuntimeError(f"upstream exploded while reading: {text[:20]}")

    app.dependency_overrides[get_extractor] = lambda: Broken()

    res = upload(client, sample_pdf)

    assert res.status_code == 502
    assert "架空" not in res.text  # no resume content in the error
