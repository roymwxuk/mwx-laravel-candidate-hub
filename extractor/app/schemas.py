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


class ResumeFields(BaseModel):
    name: str | None = Field(default=None, description="氏名")
    name_kana: str | None = Field(default=None, description="ふりがな")
    email: str | None = None
    phone: str | None = None
    summary: str | None = Field(default=None, description="職務要約 (200文字以内)")
    skills: list[str] = Field(default_factory=list, description="技術スキル・資格")
    work_histories: list[WorkHistory] = Field(default_factory=list)


class ExtractionResponse(BaseModel):
    schema_version: Literal["1"] = SCHEMA_VERSION
    extractor: str
    fields: ResumeFields
    warnings: list[str] = Field(default_factory=list)
