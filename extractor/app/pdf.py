"""PDF → plain text, keeping the page layout.

Japanese resumes are mostly forms and tables (履歴書, キャリアシート, 職務経歴書).
In many exported PDFs the printed labels (氏名, 性別, 会社名 …) and the typed
values are separate layers, so stream-order extraction puts every label first
and every value at the end, and the model can no longer tell which value
belongs to which label. pdfplumber rebuilds the lines from character positions
instead, so each value stays on (or just below) the line of its label and each
table row keeps its cells in column order (one company per column).

Its `layout=True` mode is deliberately not used: it pads text to a character
grid, which also widens spaces inside values (架空 太郎 → 架空    太郎).
"""

from __future__ import annotations

import io
import logging
import re
import unicodedata

import pdfplumber

MAX_PAGES = 10

# pdfminer logs a warning per glyph for some embedded CJK fonts ("Could not get
# FontBBox …"); harmless, and noisy enough to bury real log lines.
logging.getLogger("pdfminer").setLevel(logging.ERROR)

_TRAILING_SPACE = re.compile(r"[ \t]+$", re.MULTILINE)
_BLANK_LINES = re.compile(r"\n{2,}")


class PdfTextError(ValueError):
    """The upload is not a readable, text-based PDF."""


def extract_text(data: bytes) -> str:
    if not data.startswith(b"%PDF"):
        raise PdfTextError("PDFファイルではありません。")

    try:
        with pdfplumber.open(io.BytesIO(data)) as pdf:
            pages = [page.extract_text() or "" for page in pdf.pages[:MAX_PAGES]]
    except Exception as exc:  # pdfminer raises many unrelated types on broken files
        raise PdfTextError("PDFを読み込めませんでした。") from exc

    return clean("\n".join(pages))


def clean(text: str) -> str:
    # NFKC folds lookalike code points that some PDF fonts emit (⽣年⽉⽇ → 生年月日,
    # full-width digits → ASCII), so the prompt rules and field names match.
    text = unicodedata.normalize("NFKC", text)
    text = _BLANK_LINES.sub("\n", _TRAILING_SPACE.sub("", text)).strip("\n")

    if not text.strip():
        # Scanned (image-only) PDFs need OCR, which is out of scope for this service.
        raise PdfTextError("PDFからテキストを抽出できませんでした（画像のみのPDFは未対応です）。")

    return text
