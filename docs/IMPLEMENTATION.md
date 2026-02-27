1. 実装指示書（必須・これから作成）
Cursor AIが理解しやすい形式で、以下を1つのドキュメントにまとめます：

プロジェクト概要
フォルダ構成
各ファイルの役割
機能要件
画面設計
データ設計
プロンプト設計
処理フロー
2. 初期Memoryデータ（必須・作成済み）
GAISの知見をテキストで用意

3. 参考情報（あると良い）
Simple Job Boardのpost_type情報
WordPress Options APIの使い方
今から作成するもの
「Cursor用 実装指示書」 を作成します。

これは要件定義書をCursor AIが理解しやすい形式に変換したものです。

作成してよいですか？

作成してください。
承知しました。Cursor用の実装指示書を作成します。

JobMemory AI for GAIS - Cursor用 実装指示書
1. プロジェクト概要
プロジェクト名
JobMemory AI for GAIS

説明
GAIS会員企業向けのWordPressプラグイン。AI（OpenAI API）を使って求人文を自動生成する。GAISの知見を初期Memoryとして持ち、ユーザーのフィードバックを蓄積することで、使うほど自社に最適化された求人文が生成される。

主要機能
自社の魅力を入力するフォーム
AIによる3パターンの求人文生成
パターン選択とSimple Job Boardへの保存
フィードバックのMemory蓄積
Memory確認・リセット機能
技術スタック
WordPress 6.x
PHP 8.x
JavaScript (jQuery)
OpenAI API (gpt-4o-mini)
Simple Job Board プラグイン（post_type: jobpost）
2. フォルダ構成
jobmemory-ai/
├── jobmemory-ai.php          # メインプラグインファイル
├── includes/
│   ├── class-admin.php       # 管理画面UI
│   ├── class-ai-client.php   # OpenAI API連携
│   └── class-memory.php      # Memory管理
├── assets/
│   ├── admin.css             # 管理画面スタイル
│   └── admin.js              # 管理画面JavaScript
└── readme.txt                # プラグイン説明
3. 各ファイルの役割
jobmemory-ai.php
プラグインのエントリーポイント
定数定義（JMAI_VERSION, JMAI_PLUGIN_DIR, JMAI_PLUGIN_URL）
クラスファイルの読み込み
プラグイン有効化時の初期化処理
includes/class-memory.php
Memory（GAISの知見 + フィードバック履歴）の管理
WordPress Options APIを使用（キー: jmai_memory）
メソッド: get(), update(), append(), reset(), init_default_memory()
includes/class-ai-client.php
OpenAI API との通信
3パターンの求人文生成
プロンプトの構築
レスポンスのパース（3パターンに分割）
includes/class-admin.php
WordPress管理画面のUI
3つのページ: 求人生成、設定、Memory確認
AJAX処理: 生成、保存、フィードバック保存、Memoryリセット
assets/admin.css
管理画面のスタイル
入力フォーム、タブ切り替え、生成結果表示のデザイン
assets/admin.js
フォーム送信処理
タブ切り替え処理
AJAX通信
ローディング表示
4. データ設計
WordPress Options
キー	型	説明
jmai_memory	string	Memory全体（テキスト）
jmai_openai_api_key	string	OpenAI APIキー
Simple Job Board 保存時
項目	値
post_type	jobpost
post_title	職種名
post_content	選択した求人文
post_status	draft
5. 画面設計
5.1 求人生成画面（メイン）
URL: /wp-admin/admin.php?page=jobmemory-ai

レイアウト:

┌─────────────────────────────────────────────────────────────┐
│ JobMemory AI - 求人生成                                      │
│ GAIS会員企業向けAI求人生成ツール                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ ■ 基本情報                                                   │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 職種名 * [________________________________]              │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ ■ 自社の魅力・優位性（入力するほど良い求人文が生成されます）    │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 募集背景                                                 │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：事業拡大のため、新規プロジェクト立ち上げ  │ │
│ │                                                         │ │
│ │ 仕事内容の補足                                           │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：LLMを活用した社内ツール開発              │ │
│ │                                                         │ │
│ │ 自社の強み・魅力                                         │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：リモート可、フレックス、AI研修充実       │ │
│ │                                                         │ │
│ │ 職場環境・カルチャー                                     │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：少人数チーム、フラットな組織            │ │
│ │                                                         │ │
│ │ 給与・待遇                                               │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：年収500-800万円、書籍購入補助あり       │ │
│ │                                                         │ │
│ │ 求める人物像                                             │ │
│ │ [____________________________________________________]  │ │
│ │ placeholder: 例：自走できる方、新技術に興味がある方      │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ [AIで求人文を生成（3パターン）]                               │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ ■ 生成結果                                                   │
│                                                             │
│ <div id="loading" style="display:none;">                    │
│   3パターン生成中...（約30秒）                                │
│ </div>                                                      │
│                                                             │
│ <div id="result_area">                                      │
│   ┌────────┬────────┬────────┐                              │
│   │パターンA│パターンB│パターンC│  ← タブ                    │
│   │スタンダード│挑戦的  │カジュアル│                          │
│   └────────┴────────┴────────┘                              │
│   ┌─────────────────────────────────────────────────────┐   │
│   │                                                     │   │
│   │  （選択中のパターンの求人文が表示される）            │   │
│   │                                                     │   │
│   └─────────────────────────────────────────────────────┘   │
│ </div>                                                      │
│                                                             │
│ ■ フィードバック（任意）                                     │
│ [________________________________________________________]  │
│ placeholder: この求人文の改善点があれば入力してください       │
│                                                             │
│ [フィードバックを保存]  [Simple Job Boardに保存]              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
5.2 設定画面
URL: /wp-admin/admin.php?page=jobmemory-ai-settings

内容:

OpenAI APIキー入力欄（type="password"）
保存ボタン
5.3 Memory確認画面
URL: /wp-admin/admin.php?page=jobmemory-ai-memory

内容:

現在のMemory表示（textarea readonly）
Memoryリセットボタン（確認ダイアログ付き）
6. 入力フォームの項目
項目名	name属性	必須	placeholder
職種名	job_title	○	例：AIエンジニア、プロンプトエンジニア
募集背景	recruitment_background	-	例：事業拡大のため、新規プロジェクト立ち上げのため
仕事内容の補足	job_description	-	例：LLMを活用した社内ツール開発
自社の強み・魅力	company_strengths	-	例：リモートワーク可、フレックス制度、AI研修充実
職場環境・カルチャー	work_culture	-	例：少人数チーム、フラットな組織、挑戦を歓迎
給与・待遇	salary_benefits	-	例：年収500-800万円、書籍購入補助あり
求める人物像	ideal_candidate	-	例：自走できる方、新しい技術に興味がある方
7. プロンプト設計
7.1 AIに送るプロンプト
あなたはGAIS（生成AI協会）会員企業向けの求人文作成AIです。
以下のMemoryと入力情報を参考に、3つの異なるトーンで求人文を作成してください。

【Memory】
{$memory}

【入力情報】
職種名: {$job_title}
募集背景: {$recruitment_background}
仕事内容の補足: {$job_description}
自社の強み・魅力: {$company_strengths}
職場環境・カルチャー: {$work_culture}
給与・待遇: {$salary_benefits}
求める人物像: {$ideal_candidate}

【出力形式】
以下の3パターンを生成してください。各パターンは同じ構成ですが、トーンが異なります。

---パターンA---
【トーン】スタンダード（落ち着いた、信頼感のある表現。大手企業や安定志向の候補者向け）

1. キャッチコピー（1行）
2. 募集背景（2-3文）
3. 仕事内容（3-5項目、箇条書き）
4. 必須スキル（3-5項目、箇条書き）
5. 歓迎スキル（2-3項目、箇条書き）
6. この仕事・会社の魅力（3-5項目、箇条書き）
7. 給与・待遇
8. 求める人物像

---パターンB---
【トーン】挑戦的（情熱的で、成長機会や挑戦を強調。ベンチャーや成長意欲の高い候補者向け）

（同じ構成）

---パターンC---
【トーン】カジュアル（フレンドリーで、働きやすさやチームの雰囲気を強調。ワークライフバランス重視の候補者向け）

（同じ構成）

【注意事項】
- 3パターンとも同じ情報を基にしていますが、表現やトーンを変えてください
- キャッチコピーは各パターンで必ず異なるものにしてください
- 入力が空の項目は、GAISの知見とMemoryから推測して補完してください
- 自社の強み・魅力は必ず「この仕事・会社の魅力」セクションに反映してください
- 過去のフィードバック履歴を参考に、改善された求人文を生成してください

日本語で出力してください。
7.2 レスポンスのパース方法
AIからのレスポンスを以下の区切り文字で分割：

---パターンA---
---パターンB---
---パターンC---
PHPでの分割例：

Copy$patterns = preg_split('/---パターン[ABC]---/', $response);
// $patterns[1] = パターンA
// $patterns[2] = パターンB
// $patterns[3] = パターンC
8. 初期Memoryデータ
プラグイン有効化時に以下のテキストをjmai_memoryに保存する：

=== GAIS（生成AI協会）共通知見 ===

【GAISとは】
一般社団法人 生成AI協会（GAIS：Generative AI Society）は、生成AIの社会実装と普及を推進する団体。AI革命に対応できる人材育成、企業変革、新産業創出、社会変革をサポートしている。

【GAISの理念】
- AI革命の進展に対応可能な人材育成
- AIと人間の共生と協働の実現
- 傍観ではなく社会課題解決への積極的関与

【GAIS会員企業の特徴】
- AI活用に積極的な企業・自治体
- DX推進を重視
- 生成AIのビジネス活用を模索中
- 自治体DX、地方創生に関心が高い

【GAISで扱われるAI技術・用語】
- LLM（大規模言語モデル）
- RAG（検索拡張生成）
- プロンプトエンジニアリング
- バイブコーディング（AI駆動開発）
- Claude Code
- Dify、n8n（ノーコード/ローコードAIツール）
- AIエージェント
- ファインチューニング
- ベクトルデータベース
- API連携

【AI人材に求められるスキル】
- Python、JavaScript等のプログラミング
- OpenAI API、Claude API等の連携経験
- ノーコードツール（Dify、n8n）の活用
- プロンプト設計・最適化
- データ分析・可視化
- 業務プロセスの自動化設計

【GAIS会員企業の業種傾向】
- IT・テクノロジー企業
- 自治体・公共機関
- 製造業（DX推進）
- 教育機関
- コンサルティング

【求人文作成の方針】
- GAIS会員企業はAI活用に理解がある前提で書く
- 技術用語は適切に使用してよい
- 「AIと人間の共生」という理念を反映
- 実務でのAI活用経験を重視
- 自走できる人材を求める傾向

=== 自社フィードバック履歴 ===
（まだフィードバックはありません）
9. AJAX エンドポイント
9.1 求人文生成
項目	値
action	jmai_generate_job
method	POST
nonce	jmai_nonce
リクエストパラメータ:

job_title（必須）
recruitment_background
job_description
company_strengths
work_culture
salary_benefits
ideal_candidate
レスポンス（成功時）:

Copy{
  "success": true,
  "data": {
    "pattern_a": "パターンAの求人文...",
    "pattern_b": "パターンBの求人文...",
    "pattern_c": "パターンCの求人文...",
    "job_title": "AIエンジニア"
  }
}
9.2 フィードバック保存
項目	値
action	jmai_save_feedback
method	POST
nonce	jmai_nonce
リクエストパラメータ:

job_title
selected_pattern（a/b/c）
feedback
レスポンス（成功時）:

Copy{
  "success": true,
  "data": {
    "message": "フィードバックを保存しました。"
  }
}
9.3 求人保存
項目	値
action	jmai_save_job
method	POST
nonce	jmai_nonce
リクエストパラメータ:

job_title
content（選択したパターンの求人文）
selected_pattern（a/b/c）
レスポンス（成功時）:

Copy{
  "success": true,
  "data": {
    "message": "下書きとして保存しました。",
    "post_id": 123,
    "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit"
  }
}
9.4 Memoryリセット
項目	値
action	jmai_reset_memory
method	POST
nonce	jmai_nonce
レスポンス（成功時）:

Copy{
  "success": true,
  "data": {
    "message": "Memoryをリセットしました。"
  }
}
10. OpenAI API 設定
項目	値
エンドポイント	https://api.openai.com/v1/chat/completions
モデル	gpt-4o-mini
max_tokens	4000
temperature	0.7
タイムアウト	90秒
11. タブ切り替えUI
HTML構造
Copy<div class="jmai-tabs">
  <button class="jmai-tab active" data-pattern="a">
    パターンA<br><small>スタンダード</small>
  </button>
  <button class="jmai-tab" data-pattern="b">
    パターンB<br><small>挑戦的</small>
  </button>
  <button class="jmai-tab" data-pattern="c">
    パターンC<br><small>カジュアル</small>
  </button>
</div>

<div class="jmai-tab-content" id="pattern_a">
  <!-- パターンAの内容 -->
</div>
<div class="jmai-tab-content" id="pattern_b" style="display:none;">
  <!-- パターンBの内容 -->
</div>
<div class="jmai-tab-content" id="pattern_c" style="display:none;">
  <!-- パターンCの内容 -->
</div>
CSS
Copy.jmai-tabs {
  display: flex;
  gap: 0;
  margin-bottom: 15px;
}

.jmai-tab {
  flex: 1;
  padding: 10px 15px;
  border: 1px solid #ccd0d4;
  background: #f0f0f1;
  cursor: pointer;
  text-align: center;
}

.jmai-tab.active {
  background: #0073aa;
  color: #fff;
  border-color: #0073aa;
}

.jmai-tab:hover:not(.active) {
  background: #e0e0e0;
}

.jmai-tab-content {
  background: #f9f9f9;
  padding: 20px;
  border: 1px solid #ccd0d4;
  border-radius: 4px;
  white-space: pre-wrap;
  line-height: 1.8;
}
Copy
JavaScript
CopyjQuery(document).ready(function($) {
  // タブ切り替え
  $('.jmai-tab').on('click', function() {
    var pattern = $(this).data('pattern');
    
    // タブのアクティブ状態を切り替え
    $('.jmai-tab').removeClass('active');
    $(this).addClass('active');
    
    // コンテンツの表示切り替え
    $('.jmai-tab-content').hide();
    $('#pattern_' + pattern).show();
    
    // 選択中のパターンを保持
    selectedPattern = pattern;
  });
});
12. エラーハンドリング
エラー	メッセージ	対応
APIキー未設定	「設定画面でOpenAI APIキーを入力してください。」	設定画面へのリンクを表示
職種名未入力	「職種名を入力してください。」	アラート表示
API呼び出し失敗	「生成に失敗しました: {エラーメッセージ}」	エラー内容を表示
タイムアウト	「生成がタイムアウトしました。もう一度お試しください。」	再試行を促す
13. 完成条件チェックリスト
#	項目
1	プラグインを有効化すると、管理メニューに「JobMemory AI」が表示される
2	設定画面でOpenAI APIキーを保存できる
3	職種名のみの入力でも3パターンの求人文が生成される
4	自社の魅力を入力すると、求人文に反映される
5	タブ切り替えで3パターンを比較できる
6	フィードバックを入力するとMemoryに追記される
7	選択したパターンをSimple Job Boardに下書き保存できる
8	Memory確認画面で現在のMemoryを確認できる
9	MemoryリセットでMemoryが初期状態に戻る
10	2回目の生成でフィードバックが反映される

14. 実装の優先順位
メインプラグインファイル（jobmemory-ai.php）
Memory管理クラス（class-memory.php）
OpenAI API連携クラス（class-ai-client.php）
管理画面クラス（class-admin.php）
CSS（admin.css）
JavaScript（admin.js）

