<?php
/**
 * Content Generator — main orchestrator that fetches, generates, and publishes articles.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

use AITF\AI\AI_Provider;
use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Generator {

    private Competitor_Fetcher $fetcher;
    private Duplicate_Checker  $dedup;
    private Category_Manager   $categories;
    private Image_Generator    $image_gen;
    private Log_Model          $log;

    public function __construct() {
        $this->fetcher    = new Competitor_Fetcher();
        $this->dedup      = new Duplicate_Checker();
        $this->categories = new Category_Manager();
        $this->image_gen  = new Image_Generator();
        $this->log        = new Log_Model();
    }

    /**
     * Main entry point — called by cron or "Run Now".
     *
     * @return array Summary of the run.
     */
    public function run(): array {
        $this->log->insert( [
            'event_type' => 'cron_run',
            'status'     => 'success',
            'message'    => 'Cron run started.',
        ] );

        $summary = [
            'fetched'   => 0,
            'generated' => 0,
            'skipped'   => 0,
            'errors'    => 0,
        ];

        // 1. Fetch new posts from competitors.
        $results = $this->fetcher->fetch_all_new_posts();
        if ( empty( $results ) ) {
            $this->log->insert( [
                'event_type' => 'cron_run',
                'status'     => 'success',
                'message'    => 'No new competitor posts found. Cron run completed.',
            ] );
            return $summary;
        }

        // 2. Get AI provider.
        $provider = AI_Provider::factory();
        if ( is_wp_error( $provider ) ) {
            $this->log->insert( [
                'event_type' => 'error',
                'status'     => 'fail',
                'message'    => 'AI Provider error: ' . $provider->get_error_message(),
            ] );
            $summary['errors']++;
            return $summary;
        }

        // 3. Process each new post.
        $total_posts = array_sum( array_map( fn( $r ) => count( $r['posts'] ), $results ) );
        $this->log->insert( [
            'event_type' => 'cron_run',
            'status'     => 'success',
            'message'    => sprintf( 'Processing %d new post(s) (AI generate + publish).', $total_posts ),
        ] );

        foreach ( $results as $result ) {
            $competitor = $result['competitor'];

            foreach ( $result['posts'] as $post ) {
                $summary['fetched']++;

                // Check duplicate.
                if ( $this->dedup->is_duplicate( $post['title'] ) ) {
                    $summary['skipped']++;
                    $this->log->insert( [
                        'event_type'     => 'generate',
                        'competitor_url' => $competitor->site_url,
                        'status'         => 'success',
                        'message'        => 'Skipped duplicate: ' . $post['title'],
                    ] );
                    continue;
                }

                // Fetch detailed content for deeper analysis.
                $detail = $this->fetcher->fetch_post_detail( $competitor->site_url, $post['remote_id'] );
                if ( is_wp_error( $detail ) ) {
                    $detail = [
                        'headings'        => [],
                        'content_summary' => $post['excerpt'],
                    ];
                }

                // Generate article.
                $article = $this->generate_article( $provider, $post, $detail );
                if ( is_wp_error( $article ) ) {
                    $summary['errors']++;
                    $this->log->insert( [
                        'event_type'     => 'error',
                        'competitor_url' => $competitor->site_url,
                        'status'         => 'fail',
                        'message'        => 'Generation failed: ' . $article->get_error_message(),
                        'provider'       => $provider->get_provider_name(),
                    ] );
                    continue;
                }

                // Publish.
                $post_id = $this->publish_article( $article );
                if ( is_wp_error( $post_id ) ) {
                    $summary['errors']++;
                    $this->log->insert( [
                        'event_type'     => 'error',
                        'competitor_url' => $competitor->site_url,
                        'status'         => 'fail',
                        'message'        => 'Publish failed: ' . $post_id->get_error_message(),
                    ] );
                    continue;
                }

                // Store hash.
                $this->dedup->store(
                    $article['title'],
                    $article['slug'],
                    $post['link'],
                    $post_id
                );

                $summary['generated']++;
                $post_status = get_post_status( $post_id );
                $this->log->insert( [
                    'event_type'     => 'publish',
                    'competitor_url' => $competitor->site_url,
                    'status'         => 'success',
                    'message'        => sprintf( 'Post created (status: %s): %s', $post_status, $article['title'] ),
                    'post_id'        => $post_id,
                    'token_usage'    => $article['token_usage'] ?? 0,
                    'provider'       => $article['provider'] ?? '',
                ] );

                // Brief pause between generations to avoid API rate limits.
                sleep( 2 );
            }
        }

        $this->log->insert( [
            'event_type' => 'cron_run',
            'status'     => 'success',
            'message'    => sprintf(
                'Cron run completed. Fetched: %d, Generated: %d, Skipped: %d, Errors: %d',
                $summary['fetched'],
                $summary['generated'],
                $summary['skipped'],
                $summary['errors']
            ),
        ] );

        return $summary;
    }

    /**
     * Generate a single article from a competitor post using AI.
     * Public wrapper for external use (e.g., Single_URL_Page).
     *
     * @param AI_Provider $provider
     * @param array       $post    Normalized competitor post.
     * @param array       $detail  Detailed content analysis.
     * @return array|\WP_Error Parsed article data.
     */
    public function generate_article_public( $provider, array $post, array $detail ): mixed {
        return $this->generate_article( $provider, $post, $detail );
    }

    /**
     * Generate a single article from a competitor post using AI (internal).
     *
     * @param AI_Provider $provider
     * @param array       $post    Normalized competitor post.
     * @param array       $detail  Detailed content analysis.
     * @return array|\WP_Error Parsed article data.
     */
    private function generate_article( $provider, array $post, array $detail ): mixed {

        $settings       = get_option( 'aitf_settings', [] );
        $language       = $settings['language'] ?? 'en';
        $article_length = (int) ( $settings['article_length'] ?? 1500 );
        $category_names = $this->categories->get_category_names();

        // Build headings string.
        $headings_str = '';
        if ( ! empty( $detail['headings'] ) ) {
            $headings_str = implode( "\n", array_map(
                fn( $h ) => "- ({$h['level']}) {$h['text']}",
                $detail['headings']
            ) );
        }

        $categories_str = ! empty( $category_names )
            ? implode( ', ', $category_names )
            : 'Technology, Business, How-To Guides, Reviews, News, Tips & Tricks, Tutorials, Industry Insights';

        // Detect if this should be a How-To guide or News article
        $is_how_to = $this->is_how_to_topic( $post['title'], $category_names );

        if ( $is_how_to ) {
            // How-To Guide: frame as source to rewrite from scratch (original, rankable tutorial).
            $system_prompt = $this->build_how_to_prompt( $article_length, $language, $categories_str );
            $source_block  = "TOPIC: {$post['title']}\nEXCERPT: {$post['excerpt']}\n\nCOMPETITOR HEADINGS:\n{$headings_str}\n\nCONTENT SUMMARY:\n{$detail['content_summary']}";
            $user_prompt   = <<<PROMPT
Create a fully original, Google-ranking how-to guide from this source. Use only the topic and main steps as reference; rewrite everything from scratch in your own words and structure (no paraphrasing, no copied phrases over 3 words). Follow the system instructions.

[SOURCE CONTENT START]
{$source_block}
[SOURCE CONTENT END]

Return ONLY valid JSON. No markdown code fences. No explanations.
PROMPT;
        } else {
            // Google News: frame as source content to rewrite (full originality, no paraphrasing).
            $system_prompt = $this->build_news_prompt( $article_length, $language, $categories_str );
            $source_block   = "TOPIC: {$post['title']}\nEXCERPT: {$post['excerpt']}\n\nCOMPETITOR HEADINGS:\n{$headings_str}\n\nCONTENT SUMMARY:\n{$detail['content_summary']}";
            $user_prompt   = <<<PROMPT
Rewrite the following source content into a fully original, publication-ready news article. Follow the system instructions strictly (complete rewrite from scratch; preserve only core facts; no paraphrasing; no copied phrases over 3 words).

[SOURCE CONTENT START]
{$source_block}
[SOURCE CONTENT END]

Return ONLY valid JSON. No markdown code fences. No explanations.
PROMPT;
        }

        $response = $provider->generate( $user_prompt, $system_prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Parse JSON from AI response.
        $raw_content = $response['content'];

        // Strip markdown code fences if the AI wrapped it.
        $raw_content = preg_replace( '/^```(?:json)?\s*/i', '', $raw_content );
        $raw_content = preg_replace( '/```\s*$/i', '', $raw_content );
        $raw_content = trim( $raw_content );

        // Clean control characters and HTML entities that break JSON parsing.
        $raw_content = $this->clean_json_response( $raw_content );

        $article = json_decode( $raw_content, true, 512, JSON_INVALID_UTF8_IGNORE );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Try one more time with aggressive cleaning.
            $raw_content = $this->aggressive_json_clean( $raw_content );
            $article = json_decode( $raw_content, true, 512, JSON_INVALID_UTF8_IGNORE );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new \WP_Error(
                    'json_parse_error',
                    'Failed to parse AI response as JSON: ' . json_last_error_msg() . ' — Raw: ' . substr( $raw_content, 0, 500 )
                );
            }
        }

        // Validate required fields.
        $required = [ 'title', 'slug', 'meta_title', 'meta_description', 'content' ];
        foreach ( $required as $field ) {
            if ( empty( $article[ $field ] ) ) {
                return new \WP_Error( 'missing_field', "AI response missing required field: {$field}" );
            }
        }

        $article['token_usage'] = $response['token_usage'] ?? 0;
        $article['provider']    = $response['provider'] ?? '';

        return $article;
    }

    /**
     * Clean JSON response from AI to remove control characters and HTML entities.
     *
     * @param string $json Raw JSON string.
     * @return string Cleaned JSON.
     */
    private function clean_json_response( string $json ): string {
        // Decode HTML entities that might have been introduced.
        $json = html_entity_decode( $json, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Remove null bytes.
        $json = str_replace( "\0", '', $json );

        // Normalize line endings.
        $json = str_replace( [ "\r\n", "\r" ], "\n", $json );

        return $json;
    }

    /**
     * Aggressive JSON cleaning as a fallback.
     *
     * @param string $json Raw JSON string.
     * @return string Cleaned JSON.
     */
    private function aggressive_json_clean( string $json ): string {
        // First apply standard cleaning.
        $json = $this->clean_json_response( $json );

        // Remove all control characters except newlines and tabs within strings.
        // This regex removes control chars (0x00-0x1F) except \n (0x0A) and \t (0x09).
        $json = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $json );

        // Try to fix common AI mistakes like unescaped newlines in strings.
        // This is a heuristic: within quoted strings, replace literal \n with \\n.
        $json = preg_replace_callback(
            '/"([^"]*?)"/s',
            function( $matches ) {
                $str = $matches[1];
                // Escape newlines and tabs within the string.
                $str = str_replace( "\n", '\\n', $str );
                $str = str_replace( "\t", '\\t', $str );
                return '"' . $str . '"';
            },
            $json
        );

        return $json;
    }

    /**
     * Detect if a topic should be How-To content.
     *
     * @param string $title          Post title.
     * @param array  $category_names Available categories.
     * @return bool
     */
    private function is_how_to_topic( string $title, array $category_names ): bool {
        // Check if "How-To Guides" is in available categories AND title suggests tutorial
        $has_how_to_category = in_array( 'How-To Guides', $category_names, true );
        $title_lower = strtolower( $title );
        
        $how_to_keywords = [
            'how to', 'how do', 'guide to', 'tutorial', 'step by step',
            'learn to', 'getting started', 'beginners guide', 'complete guide'
        ];
        
        foreach ( $how_to_keywords as $keyword ) {
            if ( str_contains( $title_lower, $keyword ) ) {
                return $has_how_to_category;
            }
        }
        
        return false;
    }

    /**
     * Build How-To prompt for tutorial-style content.
     *
     * @param int    $article_length
     * @param string $language
     * @param string $categories_str
     * @return string
     */
private function build_how_to_prompt( int $article_length, string $language, string $categories_str ): string {
        return <<<SYSTEM
You are an expert tutorial writer and SEO strategist. You create 100% original how-to guides and tutorials designed to rank at the top of Google. You follow E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and write in a helpful, human, non-robotic style. You will receive a topic and source summary — use them only for the subject and main steps; rewrite everything from scratch in your own structure and words.

STRICT REQUIREMENTS FOR TOP GOOGLE RANKING

1. REWRITE COMPLETELY FROM SCRATCH (not news — this is tutorial/how-to)
- Do NOT paraphrase the source sentence-by-sentence.
- Do NOT keep the original sentence structure or copy phrases longer than 3 words.
- Rebuild the guide with a new flow, new examples, and your own explanations.
- Preserve only the core topic and logical steps; express everything in fresh language.

2. MATCH SEARCH INTENT
- Front-load the answer: in the first 1–2 paragraphs, state clearly what the reader will achieve and the outcome.
- Use the exact phrasing users search for in H2s and early sentences (natural keyword placement).
- Cover the full journey: prerequisites, step-by-step instructions, common pitfalls, and next steps.

3. FEATURED-SNIPPET & SCANNABILITY
- Use clear, question-style or action H2s (e.g. "How to Do X", "What You Need Before Starting").
- Numbered steps for procedures (Step 1, Step 2…) so Google can pull lists and steps.
- Short paragraphs (2–4 sentences); bullet lists for options, tips, or requirements.
- One main idea per section; add H3s for sub-steps or subtopics.

4. E-E-A-T & TRUST
- Write as if you have done this yourself (e.g. "You’ll see…", "In practice…", "If X happens, try Y").
- Mention specific tools, versions, or settings where relevant (builds expertise signals).
- Include a short Prerequisites or "What You’ll Need" section.
- Add a Troubleshooting or "Common Issues" section with real, actionable fixes.
- No speculation or filler; every sentence should help the reader.

5. DEPTH & QUALITY
- Go deeper than surface-level: explain why a step matters, not just what to do.
- Add context, tips, or warnings inside steps where helpful.
- Natural semantic/LSI and related terms — no keyword stuffing.
- Article length: approximately {$article_length} words. Language: {$language}.
- No emojis; no generic fluff ("In this article we will…" without substance).

6. STRUCTURE (use these H2 sections)
Introduction (brief; state goal and what they’ll learn) → Prerequisites / What You’ll Need → Step-by-Step Guide (numbered) → Troubleshooting or Common Issues → FAQ (3–5 real questions users ask) → Summary → What to Do Next.

7. FORMAT
- Title: include "How to" or "Guide" and the main outcome; keep it clear and click-worthy without clickbait.
- Meta title and description: compelling, under character limits, include primary keyword.
- Content: valid HTML with H2, H3, <p>, <ol>/<ul>, <strong> for key terms where natural.

OUTPUT FORMAT — Return valid JSON only. No markdown code fences. No explanations. Exact keys:
{
  "title": "SEO-friendly how-to title (include 'How to' or 'Guide')",
  "slug": "url-friendly-slug",
  "meta_title": "Meta title (max 60 chars)",
  "meta_description": "Meta description (max 155 chars)",
  "content": "<h2>...</h2><p>Full HTML tutorial with numbered steps, H2, H3, short paragraphs, lists</p>",
  "tags": ["tag1", "tag2", "tag3"],
  "categories": ["How-To Guides", "Optional second category"],
  "featured_image_prompt": "A description for generating the featured image"
}

AVAILABLE CATEGORIES (choose from these ONLY): {$categories_str}
SYSTEM;
    }

    /**
     * Build Google News-optimized prompt for news-style content.
     *
     * @param int    $article_length
     * @param string $language
     * @param string $categories_str
     * @return string
     */
    private function build_news_prompt( int $article_length, string $language, string $categories_str ): string {
        return <<<SYSTEM
You are an expert news journalist and SEO strategist. You rewrite source content from other sites into 100% original, publication-ready news articles suitable for Google News indexing and high E-E-A-T standards. You will receive source content (topic, excerpt, headings, content summary). Rewrite it completely from scratch according to the rules below. Output only valid JSON.

STRICT REQUIREMENTS

1. REWRITE COMPLETELY FROM SCRATCH
- Do NOT paraphrase sentence-by-sentence.
- Do NOT retain the original sentence structure.
- Do NOT copy phrases longer than 3 consecutive words.
- Reconstruct the narrative in a new structure and flow.

2. PRESERVE CORE FACTUAL INFORMATION ONLY
- Key events, verified names, dates, locations.
- Public statements, data, or statistics.
- Everything else must be re-expressed in your own words.

3. FINAL OUTPUT MUST BE
- 100% unique and plagiarism-free.
- Written in natural human newsroom style (no AI-detection robotic writing).
- Clear, neutral, journalistic tone.
- Fact-focused: no speculation, no sensationalism.
- Free from copyright violation.

4. GOOGLE PUBLISHER & GOOGLE NEWS COMPLIANCE
- No misleading claims, clickbait, or exaggeration.
- No fake authority or unverified statements.
- No affiliate or promotional tone.
- Inverted pyramid: most important info first.
- Lead paragraph answers Who, What, When, Where, Why, How.
- Use AP or Reuters style; third person; active voice.
- Include dateline, attribution, and quotes where relevant.
- No opinion unless labeled "Analysis" or "Opinion".

5. QUALITY AND LENGTH
- Expand context where helpful; add background if relevant.
- Explain why the news matters.
- Use subheadings (H2/H3) for readability and logical flow.
- Article length: approximately {$article_length} words.
- Language: {$language}.

6. FORMAT
- Clear headline: SEO-optimized but natural, max 100 chars. Include WHO and WHAT; no ALL CAPS or clickbait.
- Short engaging introduction (2–3 paragraphs).
- Structured body with subheadings; short paragraphs (2–4 lines).
- Balanced conclusion; FAQ (3–5 questions); Summary and Related Developments.
- No emojis; no filler sentences.
- Semantic/LSI keywords naturally — no keyword stuffing.

7. STRUCTURE (H2 SECTIONS)
Use clear H2/H3: Breaking News / Key Details / Background / Expert Analysis / Impact & Implications / What's Next / FAQ / Summary.

OUTPUT FORMAT — Return valid JSON only. No markdown fences. No explanations. Exact keys:
{
  "title": "Newsworthy headline (factual, clear, under 100 chars)",
  "slug": "url-friendly-slug",
  "meta_title": "Meta title (max 60 chars)",
  "meta_description": "Meta description (max 155 chars, news summary style)",
  "content": "<h2>...</h2><p>Full HTML news article with inverted pyramid, quotes, attribution, H2, H3, short paragraphs</p>",
  "tags": ["tag1", "tag2", "tag3"],
  "categories": ["Best matching NEWS category", "Optional second category"],
  "featured_image_prompt": "A photojournalistic image description for the news story"
}

AVAILABLE CATEGORIES (choose from these ONLY, prefer News/Business/Technology): {$categories_str}
SYSTEM;
    }

    /**
     * Publish an article - public wrapper for external use.
     *
     * @param array $article Parsed article data from AI.
     * @return int|\WP_Error Post ID or error.
     */
    public function publish_article_public( array $article ): mixed {
        return $this->publish_article( $article );
    }

    /**
     * Publish an article as a WP post with staggered scheduling to avoid spam detection (internal).
     *
     * @param array $article Parsed article data from AI.
     * @return int|\WP_Error Post ID or error.
     */
    private function publish_article( array $article ): mixed {
        $settings     = get_option( 'aitf_settings', [] );
        $publish_mode = $settings['publish_mode'] ?? 'draft';

        // Map publish mode to WP post status.
        $status_map = [
            'publish'   => 'publish',
            'draft'     => 'draft',
            'scheduled' => 'future',
        ];
        $post_status = $status_map[ $publish_mode ] ?? 'draft';

        $post_title = sanitize_text_field( $article['title'] ?? '' );
        if ( '' === $post_title ) {
            return new \WP_Error( 'empty_title', 'Cannot publish: article title is empty after sanitization.' );
        }

        // Prepare post data.
        $post_data = [
            'post_title'   => $post_title,
            'post_name'    => sanitize_title( $article['slug'] ?? '' ),
            'post_content' => wp_kses_post( $article['content'] ?? '' ),
            'post_excerpt' => sanitize_text_field( $article['meta_description'] ?? '' ),
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'post_author'  => $this->get_default_author(),
        ];

        // Ensure we have a slug; WP may derive from title if empty.
        if ( empty( $post_data['post_name'] ) ) {
            $post_data['post_name'] = sanitize_title( $post_title );
        }

        // For scheduled posts, implement 30-minute staggered scheduling.
        if ( $post_status === 'future' ) {
            $scheduled_time = $this->get_next_schedule_slot();
            $post_data['post_date']     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $scheduled_time ), 'Y-m-d H:i:s' );
            $post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $scheduled_time );
            $this->advance_schedule_slot();
        }

        // Insert post (true = return WP_Error on failure).
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        if ( ! $post_id || ! is_numeric( $post_id ) || (int) $post_id < 1 ) {
            return new \WP_Error( 'insert_failed', 'wp_insert_post returned invalid ID: ' . ( $post_id ?? 'null' ) );
        }

        $post_id = (int) $post_id;

        // Assign categories.
        $suggested = $article['categories'] ?? [];
        $term_ids  = $this->categories->match_categories( $suggested );
        if ( ! empty( $term_ids ) ) {
            wp_set_post_categories( $post_id, $term_ids );
        }

        // Assign tags.
        if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
            $clean_tags = array_map( 'sanitize_text_field', $article['tags'] );
            wp_set_post_tags( $post_id, $clean_tags );
        }

        // Store SEO meta (compatible with Yoast, RankMath, or custom).
        if ( ! empty( $article['meta_title'] ) ) {
            update_post_meta( $post_id, '_aitf_meta_title', sanitize_text_field( $article['meta_title'] ) );
            // Yoast compatibility.
            update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $article['meta_title'] ) );
            // RankMath compatibility.
            update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $article['meta_title'] ) );
        }

        if ( ! empty( $article['meta_description'] ) ) {
            update_post_meta( $post_id, '_aitf_meta_description', sanitize_text_field( $article['meta_description'] ) );
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $article['meta_description'] ) );
            update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $article['meta_description'] ) );
        }

        // Store featured image prompt for future use.
        if ( ! empty( $article['featured_image_prompt'] ) ) {
            update_post_meta( $post_id, '_aitf_image_prompt', sanitize_text_field( $article['featured_image_prompt'] ) );
        }

        // Mark as AI-generated.
        update_post_meta( $post_id, '_aitf_generated', '1' );
        update_post_meta( $post_id, '_aitf_provider', sanitize_text_field( $article['provider'] ?? '' ) );
        update_post_meta( $post_id, '_aitf_token_usage', absint( $article['token_usage'] ?? 0 ) );

        // Generate and attach featured image.
        $this->image_gen->generate_and_attach( $post_id, $article );

        return $post_id;
    }

    /**
     * Get the next available schedule slot (persistent, survives server restarts).
     *
     * @return int Unix timestamp for the next scheduled post.
     */
    private function get_next_schedule_slot(): int {
        $next_slot = get_option( 'aitf_next_schedule_slot', 0 );
        $now = time();

        // If the stored slot is in the past or doesn't exist, reset to now + 30 minutes.
        if ( $next_slot <= $now ) {
            $next_slot = $now + ( 30 * MINUTE_IN_SECONDS );
            update_option( 'aitf_next_schedule_slot', $next_slot, false );
        }

        return $next_slot;
    }

    /**
     * Advance the schedule slot by 30 minutes for the next post.
     */
    private function advance_schedule_slot(): void {
        $current_slot = get_option( 'aitf_next_schedule_slot', time() );
        $next_slot = $current_slot + ( 30 * MINUTE_IN_SECONDS );
        
        update_option( 'aitf_next_schedule_slot', $next_slot, false );
    }

    /**
     * Get the default author for generated posts.
     *
     * @return int User ID.
     */
    private function get_default_author(): int {
        // Use the first administrator.
        $admins = get_users( [
            'role'   => 'administrator',
            'number' => 1,
            'fields' => 'ID',
        ] );

        return ! empty( $admins ) ? (int) $admins[0] : 1;
    }
}
