<?php
namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use App\Services\BotLogger;
use Illuminate\Support\Facades\Http;

class ScraperService
{
    public function __construct(private ClaudeAnalysisService $claude) {}

    public function scrape(Source $source): int
    {
        $articlesAdded = 0;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Tradebot/1.0 (+https://github.com/tradebot)',
            ])->timeout(15)->get($source->url);

            if (!$response->successful()) {
                BotLogger::warning('scraper', "HTTP {$response->status()} fetching {$source->name}", ['http_status' => $response->status()], $source->id);
                return 0;
            }

            $items = $this->parseRss($response->body(), $source->url);

            $newArticles = collect();

            foreach ($items as $item) {
                $hash    = hash('sha256', $item['content']);
                $article = Article::firstOrCreate(
                    ['content_hash' => $hash],
                    [
                        'source_id'    => $source->id,
                        'url'          => $item['url'],
                        'title'        => $item['title'],
                        'content'      => $item['content'],
                        'published_at' => $item['published_at'],
                        'is_processed' => false,
                    ]
                );

                if ($article->wasRecentlyCreated) {
                    $articlesAdded++;
                    $newArticles->push($article);
                }
            }

            // Score all new articles in batches (one Claude call per 10 articles)
            if ($newArticles->isNotEmpty()) {
                $this->scoreArticles($newArticles);
            }

            $source->update(['last_scraped_at' => now()]);
        } catch (\Throwable $e) {
            BotLogger::error('scraper', "Scraper exception: {$e->getMessage()}", ['exception' => $e->getMessage()], $source->id);
        }

        return $articlesAdded;
    }

    private function scoreArticles(\Illuminate\Support\Collection $articles): void
    {
        $results = $this->claude->scoreArticles($articles);

        foreach ($articles as $article) {
            $result = $results[$article->id] ?? null;

            if ($result === null) {
                // If result is null, it means either:
                // 1. The article was skipped because it was irrelevant (keyword filter)
                // 2. Both Claude and Gemini failed (timeout, limit, etc.)
                // In case of #2, we should NOT mark as processed so we can retry later.
                
                // Check if it was skipped due to relevance filter inside the service
                // (In our current service, we don't know for sure here, but we can assume 
                // that if BOTH failed, we want to try again. If it's truly irrelevant, 
                // it might keep failing, so we'll add a check.)
                
                // Let's be smart: If we got NO results for the WHOLE batch, it's likely an API failure.
                if (empty($results)) {
                    continue; // Leave is_processed = false
                }
                
                // If some articles in the batch got results but this one didn't, 
                // it's likely irrelevant (skipped by keywords).
                $article->update(['is_processed' => true]);
                continue;
            }

            $article->update([
                'sentiment_score' => $result['sentiment_score'],
                'is_processed'    => true,
            ]);

            foreach ($result['signals'] ?? [] as $signal) {
                $article->sentimentSignals()->create([
                    'asset_symbol' => $signal['asset_symbol'],
                    'signal_score' => $signal['signal_score'],
                    'signal_type'  => $signal['signal_type'],
                ]);
            }
        }
    }

    private function parseRss(string $xml, string $sourceUrl): array
    {
        $items = [];

        try {
            $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR);
            if ($feed === false) return [];

            // RSS 2.0
            $entries = $feed->channel->item ?? [];

            // Atom
            if (empty($entries)) {
                $feed->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
                $entries = $feed->xpath('//atom:entry') ?? [];
            }

            foreach ($entries as $entry) {
                $title   = (string) ($entry->title ?? '');
                $link    = (string) ($entry->link ?? $entry->guid ?? '');
                $content = (string) ($entry->{'content:encoded'} ?? $entry->description ?? $entry->summary ?? '');
                $pubDate = (string) ($entry->pubDate ?? $entry->updated ?? $entry->published ?? '');

                // Strip HTML tags
                $content = strip_tags($content);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);

                if (empty($title) || empty($content)) continue;

                // Use source URL as fallback for relative links
                if (empty($link) || !str_starts_with($link, 'http')) {
                    $link = $sourceUrl;
                }

                $publishedAt = null;
                if (!empty($pubDate)) {
                    try {
                        $publishedAt = new \DateTime($pubDate);
                        $publishedAt = $publishedAt->format('Y-m-d H:i:s');
                    } catch (\Throwable) {
                        $publishedAt = null;
                    }
                }

                $items[] = [
                    'title'        => mb_substr($title, 0, 255),
                    'url'          => mb_substr($link, 0, 2048),
                    'content'      => mb_substr($title . "\n\n" . $content, 0, 65535),
                    'published_at' => $publishedAt,
                ];
            }
        } catch (\Throwable $e) {
            BotLogger::warning('scraper', "RSS parse error: {$e->getMessage()}", ['url' => $sourceUrl, 'exception' => $e->getMessage()]);
        }

        return $items;
    }
}
