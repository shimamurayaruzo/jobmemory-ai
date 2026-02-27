<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JMAI_Memory {

    private const OPTION_KEY = 'jmai_memory';

    public function get(): string {
        return get_option( self::OPTION_KEY, '' );
    }

    public function update( string $content ): bool {
        return update_option( self::OPTION_KEY, $content );
    }

    public function append( string $entry ): bool {
        $current = $this->get();
        $current .= "\n" . $entry;
        return $this->update( $current );
    }

    public function reset(): bool {
        delete_option( self::OPTION_KEY );
        return $this->init_default_memory();
    }

    public function init_default_memory(): bool {
        if ( $this->get() !== '' ) {
            return false;
        }

        $default = <<<'MEMORY'
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
MEMORY;

        return $this->update( $default );
    }
}
