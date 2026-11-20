"""Rule-based extractor.

Works offline (no API key) so the whole system can be demoed and tested for free.
It also doubles as a baseline: if an LLM cannot beat these regexes on a field,
that field probably doesn't need an LLM.
"""

from __future__ import annotations

import re
import unicodedata

from app.schemas import ExtractionResponse, ResumeFields, WorkHistory

EMAIL = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}")
PHONE = re.compile(r"(?:\+81[- ]?)?0\d{1,4}[- ]?\d{1,4}[- ]?\d{3,4}")
NAME = re.compile(r"^\s*(?:氏\s*名|名\s*前)\s*[:：]?\s*(.+)$", re.MULTILINE)
KANA = re.compile(r"^\s*(?:ふりがな|フリガナ)\s*[:：]?\s*([ぁ-ゟ゠-ヿ\s・ー]+)$", re.MULTILINE)
# "2018年2月 Yowoo Tech 入社" / "2018/02 Yowoo Tech" / "2018-02 〜 2021-08 Yowoo Tech"
PERIOD_LINE = re.compile(
    r"^\s*(?P<y1>\d{4})\s*[年/.-]\s*(?P<m1>\d{1,2})\s*月?"
    r"(?:\s*[〜~\-–—]\s*(?:(?P<y2>\d{4})\s*[年/.-]\s*(?P<m2>\d{1,2})\s*月?|(?P<now>現在|在職中)))?"
    r"\s+(?P<rest>.+)$",
    re.MULTILINE,
)
SECTION_SKILLS = re.compile(r"(?:スキル|技術|Skills?)\s*[:：]?\s*(.+)", re.IGNORECASE)
LEAVE_WORDS = ("退職", "退社")
JOIN_WORDS = ("入社", "入職")


def _ym(year: str | None, month: str | None) -> str | None:
    if not year or not month:
        return None
    m = int(month)
    return f"{int(year):04d}-{m:02d}" if 1 <= m <= 12 else None


class RuleBasedExtractor:
    name = "mock-rules-v1"

    def extract(self, text: str) -> ExtractionResponse:
        text = unicodedata.normalize("NFKC", text)
        warnings: list[str] = []

        name = self._first(NAME, text)
        kana = self._first(KANA, text)
        email = (m.group(0) if (m := EMAIL.search(text)) else None)
        phone = (m.group(0) if (m := PHONE.search(text)) else None)

        if name is None:
            warnings.append("氏名を特定できませんでした。")

        return ExtractionResponse(
            extractor=self.name,
            fields=ResumeFields(
                name=name,
                name_kana=kana.strip() if kana else None,
                email=email,
                phone=phone,
                skills=self._skills(text),
                work_histories=self._work_histories(text, warnings),
            ),
            warnings=warnings,
        )

    @staticmethod
    def _first(pattern: re.Pattern[str], text: str) -> str | None:
        m = pattern.search(text)
        return m.group(1).strip() if m and m.group(1).strip() else None

    @staticmethod
    def _skills(text: str) -> list[str]:
        m = SECTION_SKILLS.search(text)
        if not m:
            return []
        parts = re.split(r"[,、/／・]\s*", m.group(1))
        return [p.strip() for p in parts if 0 < len(p.strip()) <= 50][:30]

    @staticmethod
    def _work_histories(text: str, warnings: list[str]) -> list[WorkHistory]:
        """Pair "入社" / "退職" lines (履歴書 style) or read explicit ranges (職務経歴書 style)."""
        histories: list[WorkHistory] = []

        for m in PERIOD_LINE.finditer(text):
            rest = m.group("rest").strip()
            start = _ym(m.group("y1"), m.group("m1"))

            if any(w in rest for w in LEAVE_WORDS):
                company = re.sub(r"\s*(?:" + "|".join(LEAVE_WORDS) + r").*$", "", rest).strip()
                for h in reversed(histories):
                    if h.company == company and h.ended_on is None:
                        h.ended_on = start
                        break
                continue

            if m.group("y2") or m.group("now") or any(w in rest for w in JOIN_WORDS):
                company = re.sub(r"\s*(?:" + "|".join(JOIN_WORDS) + r").*$", "", rest).strip()
                if not company:
                    continue
                histories.append(
                    WorkHistory(
                        company=company,
                        started_on=start,
                        ended_on=_ym(m.group("y2"), m.group("m2")),
                    )
                )

        if not histories:
            warnings.append("職歴を検出できませんでした。手入力してください。")

        return histories[:20]
