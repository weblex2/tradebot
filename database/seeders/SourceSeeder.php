<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Source;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'CoinDesk', 'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/', 'category' => 'news', 'weight' => 1.50, 'refresh_minutes' => 30],
            ['name' => 'CoinTelegraph', 'url' => 'https://cointelegraph.com/rss', 'category' => 'news', 'weight' => 1.20, 'refresh_minutes' => 30],
            ['name' => 'Decrypt', 'url' => 'https://decrypt.co/feed', 'category' => 'news', 'weight' => 1.10, 'refresh_minutes' => 60],
            ['name' => 'Bitcoin Magazine', 'url' => 'https://bitcoinmagazine.com/.rss/full/', 'category' => 'news', 'weight' => 1.30, 'refresh_minutes' => 60],
            ['name' => 'The Block', 'url' => 'https://www.theblock.co/rss.xml', 'category' => 'news', 'weight' => 1.40, 'refresh_minutes' => 30],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(['url' => $source['url']], $source);
        }
    }
}
