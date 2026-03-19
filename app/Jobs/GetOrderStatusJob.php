<?php
namespace App\Jobs;

use App\Models\Execution;
use App\Services\CoinbaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(private Execution $execution) {}

    public function handle(CoinbaseService $coinbase): void
    {
        if ($this->execution->status !== 'pending' || empty($this->execution->exchange_order_id)) {
            return;
        }

        $order = $coinbase->getOrderStatus($this->execution->exchange_order_id);
        if ($order === null) {
            Log::warning('GetOrderStatusJob: could not fetch order status', ['execution_id' => $this->execution->id]);
            $this->release(60);
            return;
        }

        $status = strtolower($order['status'] ?? '');

        $mapped = match (true) {
            in_array($status, ['filled', 'done'])    => 'filled',
            in_array($status, ['cancelled', 'canceled', 'expired']) => 'cancelled',
            in_array($status, ['rejected', 'failed']) => 'failed',
            default                                   => null,
        };

        if ($mapped === null) {
            // Still pending, retry
            $this->release(30);
            return;
        }

        $fillPrice = isset($order['average_filled_price'])
            ? (int) round((float) $order['average_filled_price'] * 100)
            : null;

        $fee = isset($order['total_fees'])
            ? (int) round((float) $order['total_fees'] * 100)
            : null;

        $filledSize = isset($order['filled_size'])
            ? (float) $order['filled_size']
            : null;

        $this->execution->update([
            'status'             => $mapped,
            'price_at_execution' => $fillPrice,
            'fee_usd'            => $fee,
            'filled_size'        => $filledSize,
        ]);

        Log::info('GetOrderStatusJob: order resolved', [
            'execution_id' => $this->execution->id,
            'status'       => $mapped,
            'fill_price'   => $fillPrice,
        ]);
    }
}
