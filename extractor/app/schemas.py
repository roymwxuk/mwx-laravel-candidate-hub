"""Response contract shared with the Laravel app (ExtractionResult::SCHEMA_VERSION)."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field

SCHEMA_VERSION = "1"

YEAR_MONTH = r"^\d{4}-(0[1-9]|1[0-2])$"


class WorkHistory(BaseModel):
    company: str = Field(description="会社名")
    title: str | None = Field(default=None, description="役職・職種")
    started_on: str | None = Field(default=None, description="開始年月 (YYYY-MM)")
    ended_on: str | None = Field(default=None, description="終了年月 (YYYY-MM)。在職中なら null")
    description: str | None = Field(default=None, description="業務内容の要約")


EducationLevel = Literal["high_school", "vocational", "associate", "bachelor", "master", "doctorate"]

Gender = Literal["male", "female", "other"]


class ResumeFields(BaseModel):
    name: str | None = Field(default=None, description="氏名")
    name_kana: str | None = Field(default=None, description="ふりがな")
    email: str | None = None
    phone: str | None = None
    address: str | None = Field(
        default=None,
        description="現住所（都道府県から建物名・部屋番号まで）。郵便番号とフリガナは含めない",
    )
    birth_date: str | None = Field(default=None, description="生年月日 (YYYY-MM-DD)")
    gender: Gender | None = Field(
        default=None,
        description="性別欄に明記されている場合のみ: male=男性, female=女性, other=その他・回答しない。"
        "記載がなければ null。氏名や写真から推測しない",
    )
    summary: str | None = Field(default=None, description="職務要約 (200文字以内)")
    skills: list[str] = Field(default_factory=list, description="技術スキル・資格（語学は含めない）")
    languages: list[str] = Field(
        default_factory=list,
        description="語学力。1言語1項目で、レベルや試験スコアを括弧で添える（例: 英語（TOEIC 850点））",
    )
    education_level: EducationLevel | None = Field(
        default=None,
        description="最終学歴の区分: high_school=高校, vocational=専門学校, associate=短大・高専, "
        "bachelor=大学, master=大学院修士, doctorate=大学院博士",
    )
    school_name: str | None = Field(default=None, description="最終学歴の学校名")
    major: str | None = Field(default=None, description="最終学歴の学部・学科・専攻")
    graduated_on: str | None = Field(default=None, description="最終学歴の卒業年月 (YYYY-MM)")
    expected_salary: int | None = Field(default=None, description="希望年収（万円、整数）")
    desired_location: str | None = Field(default=None, description="希望勤務地")
    work_histories: list[WorkHistory] = Field(default_factory=list)


class ExtractionResponse(BaseModel):
    schema_version: Literal["1"] = SCHEMA_VERSION
    extractor: str
    # Null for backends that do not reason (mock). Shown next to the extractor name on the review page.
    reasoning_effort: str | None = None
    fields: ResumeFields
    warnings: list[str] = Field(default_factory=list)
