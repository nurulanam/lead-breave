<?php

namespace App\Services\Website;

/**
 * On-page SEO basics from the home page. Ported from LeadMap's Seo_Analyzer.
 */
class SeoAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(FetchResult $page): array
    {
        $html = $page->body;
        $title = preg_match('#<title[^>]*>(.*?)</title>#is', $html, $match)
            ? trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : '';
        $description = preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i', $html, $match) ? trim($match[1]) : '';
        $h1Count = preg_match_all('#<h1\b[^>]*>#i', $html);

        preg_match_all('#<img\b[^>]*>#i', $html, $images);
        $imageCount = count($images[0]);
        $withAlt = count(array_filter($images[0], fn (string $img): bool => preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $img) === 1));
        $isHttps = $page->isHttps();

        return [
            'title' => $title,
            'title_length' => mb_strlen($title),
            'description' => $description,
            'description_length' => mb_strlen($description),
            'h1_count' => $h1Count,
            'image_count' => $imageCount,
            'images_with_alt' => $withAlt,
            'alt_coverage' => $imageCount > 0 ? (int) round($withAlt / $imageCount * 100) : 100,
            'has_viewport' => preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html) === 1,
            'has_schema' => preg_match('#application/ld\+json#i', $html) === 1,
            'has_canonical' => preg_match('/<link[^>]+rel=["\']canonical["\']/i', $html) === 1,
            'has_open_graph' => preg_match('/<meta[^>]+property=["\']og:/i', $html) === 1,
            'has_favicon' => preg_match('/<link[^>]+rel=["\'][^"\']*icon/i', $html) === 1,
            'is_https' => $isHttps,
            'mixed_content' => $isHttps && preg_match('#(?:src|href)\s*=\s*["\']http://#i', $html) === 1,
            'copyright_year' => $this->copyrightYear($html),
        ];
    }

    private function copyrightYear(string $html): ?int
    {
        $text = EmailExtractor::toText($html);

        if (! preg_match_all('/(?:©|\(c\)|copyright)\s*(?:[0-9]{4}\s*[–-]\s*)?((?:19|20)[0-9]{2})/iu', $text, $matches)) {
            return null;
        }

        $years = array_filter(array_map('intval', $matches[1]), fn (int $year): bool => $year <= (int) now()->format('Y'));

        return $years === [] ? null : max($years);
    }
}
