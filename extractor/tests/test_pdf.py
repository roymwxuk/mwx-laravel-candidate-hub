"""extract_text(): layout-preserving PDF text for form-style resumes."""

from __future__ import annotations

from pathlib import Path

import pytest

from app.pdf import PdfTextError, clean, extract_text

FIXTURES = Path(__file__).parent / "fixtures"


@pytest.fixture
def career_sheet_text() -> str:
    # Fictional filled-in キャリアシート: the form labels and the typed values are
    # separate layers, so stream-order extraction separates them completely.
    return extract_text((FIXTURES / "career_sheet.pdf").read_bytes())


def line_with(text: str, *needles: str) -> str | None:
    return next((line for line in text.splitlines() if all(n in line for n in needles)), None)


def test_values_stay_on_the_line_of_their_label(career_sheet_text):
    assert line_with(career_sheet_text, "携帯電話", "090-0000-0000")
    assert line_with(career_sheet_text, "PCアドレス", "hanako.yamada@example.com")
    assert line_with(career_sheet_text, "転職回数", "2回")


def test_table_columns_stay_together(career_sheet_text):
    # One company per column: each row keeps its label and all three values in order.
    row = line_with(career_sheet_text, "会社名", "株式会社サンプルリンク", "株式会社見本クラウド", "株式会社テスト工房")
    assert row and row.index("サンプルリンク") < row.index("見本クラウド") < row.index("テスト工房")
    assert line_with(career_sheet_text, "入退社", "2022年7月", "2018年4月", "2014年4月")


def test_gender_and_address_are_readable(career_sheet_text):
    lines = career_sheet_text.splitlines()
    gender_label = next(i for i, line in enumerate(lines) if "性別" in line)
    # The value sits just below its label, not in a block at the end of the text.
    assert any("女性" in line for line in lines[gender_label : gender_label + 3])
    assert line_with(career_sheet_text, "東京都サンプル区見本町2-4-8")


def test_lookalike_code_points_are_normalized(career_sheet_text):
    assert "生年月日" in career_sheet_text
    assert "⽣" not in career_sheet_text  # U+2F63 KANGXI RADICAL LIFE


def test_padding_is_trimmed():
    assert clean("氏名   山田    \n\n\n\n電話  090  \n") == "氏名   山田\n電話  090"


def test_image_only_text_is_rejected():
    with pytest.raises(PdfTextError):
        clean(" \n\n  \n")


def test_broken_pdfs_are_rejected():
    with pytest.raises(PdfTextError):
        extract_text(b"%PDF-1.7\nthis is not really a pdf")
