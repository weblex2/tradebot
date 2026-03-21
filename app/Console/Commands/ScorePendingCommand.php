<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\BotLogger;
use App\Services\GeminiAnalysisService;
use Illuminate\Console\Command;

class ScorePendingCommand extends Command
{
    protected $signature   = 'scraper:score-pending {--batch=10 : Articles per Gemini call} {--limit=200 : Max articles to process}';
    protected $description = 'Score unprocessed articles using Gemini';

    public function handle(GeminiAnalysisService $gemini): int
    {
        $batchSize = (int) $this->option('batch');
        $limit     = (int) $this->option('limit');

        $pending = Article::where('is_processed', false)
            ->oldest()
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending articles.');
            return 0;
        }

        $this->info("Scoring {$pending->count()} articles via Gemini (batch size: {$batchSize})...");

        $allowedAssets = implode(', ', config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']));

        $system = <<<SYSTEM
You are a crypto market sentiment analyst. Score each article and return ONLY a valid JSON array – no markdown, no explanation.

Response shape (one object per article, same order as input):
[{"id":1,"sentiment_score":0.0,"signals":[{"asset_symbol":"BTC","signal_score":0.7,"signal_type":"regulatory"}],"relevance":0.9}]

Rules:
- sentiment_score: float -1.0 to 1.0
- signal_score: float -1.0 to 1.0 per asset mentioned
- signal_type: regulatory | market_move | adoption | technical | macro | other
- relevance: float 0.0 to 1.0
- Only include assets from this list: BTC, ETH, SOL, XRP, DOGE, SHIB, APE, ADA, AVAX, LINK, DOT, LTC, UNI, ATOM, FIL, ALGO, MANA, CRV, GRT, BAT, CHZ, MINA, SNX, XLM, XTZ, 1INCH
- If no relevant signals, use "signals": []
SYSTEM;

        $scored = 0;
        $irrelevant = 0;
        $failed = 0;

        foreach ($pending->chunk($batchSize) as $batch) {
            $items = $batch->map(fn($a) => [
                'id'      => $a->id,
                'title'   => $a->title,
                'content' => mb_substr($a->content, 0, 500),
            ])->values()->toArray();

            $user = json_encode($items, JSON_UNESCAPED_UNICODE);
            $data = $gemini->callGemini($system, $user, 'score ' . $batch->count() . ' pending articles');

            if (!is_array($data)) {
                $failed += $batch->count();
                $this->warn("Batch failed for IDs: " . $batch->pluck('id')->implode(','));
                continue;
            }

            // Handle single object response
            if (isset($data['id'])) {
                $data = [$data];
            }

            $resultMap = collect($data)->keyBy('id');

            foreach ($batch as $article) {
                $result = $resultMap->get($article->id);

                if (!$result) {
                    $article->update(['is_processed' => true, 'is_irrelevant' => true]);
                    $irrelevant++;
                    continue;
                }

                $article->update([
                    'sentiment_score' => (float) ($result['sentiment_score'] ?? 0),
                    'is_processed'    => true,
                ]);

                foreach ($result['signals'] ?? [] as $sig) {
                    $article->sentimentSignals()->firstOrCreate(
                        ['asset_symbol' => $sig['asset_symbol'], 'signal_type' => $sig['signal_type']],
                        ['signal_score' => $sig['signal_score']]
                    );
                }

                $scored++;
            }

            $this->line("  Batch done — scored: {$scored}, irrelevant: {$irrelevant}, failed: {$failed}");
        }

        $remaining = Article::where('is_processed', false)->count();

        BotLogger::info('scraper', "scraper:score-pending complete — scored: {$scored}, irrelevant: {$irrelevant}, failed: {$failed}, remaining: {$remaining}");

        $this->info("\nDone. Scored: {$scored} | Irrelevant: {$irrelevant} | Failed: {$failed} | Still pending: {$remaining}");

        return 0;
    }
}
