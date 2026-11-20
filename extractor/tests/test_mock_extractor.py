from app.extractors.mock import RuleBasedExtractor

RESUME = """職務経歴書
氏　名：山田　花子
フリガナ：ヤマダ ハナコ
E-mail: hanako@example.co.jp  TEL ０９０－１１１１－２２２２
2015/04 〜 2018/03 株式会社アルファ
2018年4月 〜 現在 株式会社ベータ
技術：Go, TypeScript, AWS
"""


def test_extracts_contact_fields_with_fullwidth_normalisation():
    fields = RuleBasedExtractor().extract(RESUME).fields

    assert fields.name == "山田 花子"
    assert fields.name_kana == "ヤマダ ハナコ"
    assert fields.email == "hanako@example.co.jp"
    assert fields.phone == "090-1111-2222"
    assert fields.skills == ["Go", "TypeScript", "AWS"]


def test_reads_explicit_period_ranges():
    histories = RuleBasedExtractor().extract(RESUME).fields.work_histories

    assert [(h.company, h.started_on, h.ended_on) for h in histories] == [
        ("株式会社アルファ", "2015-04", "2018-03"),
        ("株式会社ベータ", "2018-04", None),
    ]


def test_pairs_join_and_leave_lines():
    text = "2016年4月 A社 入社\n2019年9月 A社 退職\n2019年10月 B社 入社\n"
    histories = RuleBasedExtractor().extract(text).fields.work_histories

    assert [(h.company, h.started_on, h.ended_on) for h in histories] == [
        ("A社", "2016-04", "2019-09"),
        ("B社", "2019-10", None),
    ]


def test_reports_what_it_could_not_find_instead_of_guessing():
    result = RuleBasedExtractor().extract("自己PR：がんばります")

    assert result.fields.name is None
    assert result.fields.work_histories == []
    assert any("氏名" in w for w in result.warnings)
    assert any("職歴" in w for w in result.warnings)


def test_invalid_months_are_dropped():
    histories = RuleBasedExtractor().extract("2020年13月 C社 入社").fields.work_histories

    assert histories[0].started_on is None
