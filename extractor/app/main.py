"""Resume extraction microservice.

POST /extract  (multipart "file": PDF)  →  ExtractionResponse JSON
GET  /health

Stateless on purpose: it never stores files or results. Persistence, auth for
end users and the human review step all live in the Laravel app.
"""

from __future__ import annotations

import hmac
import logging
import os
from functools import lru_cache

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile

from app.extractors import Extractor, build_extractor
from app.pdf import PdfTextError, extract_text
from app.schemas import ExtractionResponse

MAX_BYTES = 5 * 1024 * 1024

logger = logging.getLogger("extractor")
app = FastAPI(title="Resume Extractor", version="1.0.0")


@lru_cache
def get_extractor() -> Extractor:
    return build_extractor()


def verify_token(x_internal_token: str | None = Header(default=None)) -> None:
    """Shared-secret check for service-to-service calls (skipped when unset, e.g. local dev)."""
    expected = os.getenv("EXTRACTOR_TOKEN")
    if expected and not hmac.compare_digest(x_internal_token or "", expected):
        raise HTTPException(status_code=401, detail="invalid internal token")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "extractor": get_extractor().name}


@app.post("/extract", response_model=ExtractionResponse, dependencies=[Depends(verify_token)])
async def extract(
    file: UploadFile = File(...),
    extractor: Extractor = Depends(get_extractor),
) -> ExtractionResponse:
    data = await file.read(MAX_BYTES + 1)
    if len(data) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="ファイルサイズが大きすぎます（5MBまで）。")

    try:
        text = extract_text(data)
    except PdfTextError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    try:
        return extractor.extract(text)
    except Exception as exc:  # LLM/API failures are transient from the caller's point of view
        # Log the type only: the exception text may echo resume content (personal data).
        logger.error("extraction failed: %s", type(exc).__name__)
        raise HTTPException(status_code=502, detail="抽出処理に失敗しました。") from exc
