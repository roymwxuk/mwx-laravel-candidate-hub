<?php

// Only the rules used in this app are translated; anything else falls back to English.
return [
    'array' => ':attributeは配列で指定してください。',
    'confirmed' => ':attributeが確認用の値と一致しません。',
    'date_format' => ':attributeは :format の形式で指定してください。',
    'email' => ':attributeは有効なメールアドレス形式で指定してください。',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'file' => ':attributeにはファイルを指定してください。',
    'max' => [
        'array' => ':attributeは :max 個以下で指定してください。',
        'file' => ':attributeは :max KB以下のファイルを指定してください。',
        'numeric' => ':attributeは :max 以下で指定してください。',
        'string' => ':attributeは :max 文字以下で指定してください。',
    ],
    'mimes' => ':attributeには :values 形式のファイルを指定してください。',
    'min' => [
        'string' => ':attributeは :min 文字以上で指定してください。',
    ],
    'password' => [
        'min' => ':attributeは :min 文字以上で指定してください。',
    ],
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeは必須です。',
    'string' => ':attributeは文字列で指定してください。',
    'unique' => 'この:attributeは既に使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',

    'attributes' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'stage' => '選考ステージ',
        'q' => '検索キーワード',
    ],
];
