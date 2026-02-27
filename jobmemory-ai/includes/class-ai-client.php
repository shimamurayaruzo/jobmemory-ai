<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JMAI_AI_Client {

    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-4o-mini';

    private function get_api_key(): string {
        return get_option( 'jmai_openai_api_key', '' );
    }

    public function generate( array $params ): array {
        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'error'   => '設定画面でOpenAI APIキーを入力してください。',
            ];
        }

        $memory = ( new JMAI_Memory() )->get();
        $prompt = $this->build_prompt( $memory, $params );

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 90,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode( [
                'model'       => self::MODEL,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたはGAIS（生成AI協会）会員企業向けの求人文作成AIです。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens'  => 4000,
                'temperature' => 0.7,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            if ( str_contains( $msg, 'timed out' ) || str_contains( $msg, 'cURL error 28' ) ) {
                return [
                    'success' => false,
                    'error'   => '生成がタイムアウトしました。もう一度お試しください。',
                ];
            }
            return [
                'success' => false,
                'error'   => '生成に失敗しました: ' . $msg,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $error_msg = $body['error']['message'] ?? '不明なエラー';
            return [
                'success' => false,
                'error'   => '生成に失敗しました: ' . $error_msg,
            ];
        }

        $content  = $body['choices'][0]['message']['content'] ?? '';
        $patterns = $this->parse_patterns( $content );

        return [
            'success'   => true,
            'pattern_a' => $patterns['a'],
            'pattern_b' => $patterns['b'],
            'pattern_c' => $patterns['c'],
        ];
    }

    private function build_prompt( string $memory, array $p ): string {
        $job_title              = $p['job_title'] ?? '';
        $recruitment_background = $p['recruitment_background'] ?? '';
        $job_description        = $p['job_description'] ?? '';
        $company_strengths      = $p['company_strengths'] ?? '';
        $work_culture           = $p['work_culture'] ?? '';
        $salary_benefits        = $p['salary_benefits'] ?? '';
        $ideal_candidate        = $p['ideal_candidate'] ?? '';

        return <<<PROMPT
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
PROMPT;
    }

    private function parse_patterns( string $content ): array {
        $patterns = preg_split( '/---パターン[ABC]---/', $content );

        return [
            'a' => trim( $patterns[1] ?? '' ),
            'b' => trim( $patterns[2] ?? '' ),
            'c' => trim( $patterns[3] ?? '' ),
        ];
    }
}
