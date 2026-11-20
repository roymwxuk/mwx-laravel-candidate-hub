from __future__ import annotations

import io

from pypdf import PdfReader
from pypdf.errors import PdfReadError

MAX_PAGES = 10


class PdfTextError(ValueError):
    """The upload is not a readable, text-based PDF."""


def extract_text(data: bytes) -> str:
    if not data.startswith(b"%PDF"):
        raise PdfTextError("PDFファイルではありません。")

    try:
        reader = PdfReader(io.BytesIO(data))
        pages = reader.pages[:MAX_PAGES]
        text = "\n".join(page.extract_text() or "" for page in pages)
    except (PdfReadError, ValueError, KeyError) as exc:  # pypdf raises a few types on broken files
        raise PdfTextError("PDFを読み込めませんでした。") from exc

    text = text.strip()
    if not text:
        # Scanned (image-only) PDFs need OCR, which is out of scope for this service.
        raise PdfTextError("PDFからテキストを抽出できませんでした（画像のみのPDFは未対応です）。")

    return text
