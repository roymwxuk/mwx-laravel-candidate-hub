"""Prompt and limits for the LLM-backed extractor (Azure OpenAI)."""

from __future__ import annotations

MAX_INPUT_CHARS = 30_000

SYSTEM_PROMPT = """あなたは日本の採用担当者のアシスタントです。
履歴書・職務経歴書のテキストから、指定されたフィールドを抽出してください。

ルール:
- 書かれている情報だけを抽出し、推測で補完しないこと。見つからない項目は null にする。
- 年月は YYYY-MM 形式に正規化する（例: 2018年2月 → 2018-02）。在職中の終了年月は null。
- 生年月日は YYYY-MM-DD 形式（例: 1990年4月1日 → 1990-04-01）。和暦は西暦に変換する。
- gender は性別欄に明記されている場合のみ設定する（男 → male、女 → female、それ以外や「回答しない」→ other）。記載がなければ null。氏名・写真・文体などから絶対に推測しない。
- summary は職務要約を200文字以内で要約する。
- 語学（英語・中国語など）は skills に入れず languages に入れる。TOEIC等のスコアやレベルは括弧で添える。
- 最終学歴は卒業（または修了）した最も高い学歴を1つだけ選ぶ。在学中・中退のみの場合は warnings に書く。
- expected_salary は希望年収を万円単位の整数にする（例: 600万円 → 600）。月給のみの記載や「応相談」の場合は null にし、warnings に書く。
- desired_location は希望勤務地を記載どおり短く書く（例: 東京都、リモート可）。
- address は現住所を記載どおり1行で書く（例: 東京都千代田区丸の内1-2-3 サンプルビル101）。郵便番号（〒）と住所フリガナは含めない。連絡先住所しかない場合も現住所として扱わない。
- 判断に迷った点は warnings に日本語で短く書く。
"""

TRUNCATION_WARNING = "書類が長いため先頭部分のみ解析しました。"


class ExtractionError(RuntimeError):
    """The model did not return usable structured output."""


def truncate(text: str) -> tuple[str, list[str]]:
    """Cap the input size; return the (possibly shortened) text and any warning to surface."""
    if len(text) > MAX_INPUT_CHARS:
        return text[:MAX_INPUT_CHARS], [TRUNCATION_WARNING]
    return text, []
