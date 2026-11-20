"""Generate a fictional Japanese resume PDF for tests and demos.

Usage: python scripts/make_sample_pdf.py tests/fixtures/sample_resume.pdf
Needs a TrueType Japanese font (default: IPA Gothic, `apt install fonts-ipafont-gothic`).
"""

from __future__ import annotations

import os
import sys

from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas

FONT_PATH = os.getenv("JA_FONT", "/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf")

LINES = [
    ("履歴書", 18),
    ("", 11),
    ("氏名：架空 太郎", 12),
    ("ふりがな：かくう たろう", 11),
    ("メール：kakuu.taro@example.com", 11),
    ("電話：090-1234-5678", 11),
    ("", 11),
    ("職歴", 13),
    ("2016年4月 株式会社サンプルシステムズ 入社", 11),
    ("2019年9月 株式会社サンプルシステムズ 退職", 11),
    ("2019年10月 合同会社テックデモ 入社", 11),
    ("現在に至る", 11),
    ("", 11),
    ("スキル：PHP、Laravel、MySQL、Docker、AWS", 11),
    ("", 11),
    ("※このPDFはテスト用に生成した架空の人物の履歴書です。", 9),
]


def main(out: str) -> None:
    pdfmetrics.registerFont(TTFont("IPAGothic", FONT_PATH))
    c = canvas.Canvas(out, pagesize=A4)
    c.setTitle("Sample resume (fictional)")
    y = A4[1] - 72
    for text, size in LINES:
        c.setFont("IPAGothic", size)
        c.drawString(72, y, text)
        y -= size + 10
    c.save()


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "sample_resume.pdf")
