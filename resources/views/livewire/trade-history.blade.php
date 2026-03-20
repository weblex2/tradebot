<div>
    {{-- Stats --}}
    <div class="grid grid-cols-5 gap-4 mb-6">
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-white">{{ $stats['total'] }}</div>
            <div class="text-xs text-white/40 mt-1">Total</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-green">{{ $stats['filled'] }}</div>
            <div class="text-xs text-white/40 mt-1">Filled</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-red">{{ $stats['failed'] }}</div>
            <div class="text-xs text-white/40 mt-1">Failed</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-blue">{{ $stats['paper'] }}</div>
            <div class="text-xs text-white/40 mt-1">Paper</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400">{{ $stats['live'] }}</div>
            <div class="text-xs text-white/40 mt-1">Live</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card p-4 mb-6 flex gap-4">
        <select wire:model.live="filterMode" class="select-glass">
            <option value="">All Modes</option>
            <option value="paper">Paper</option>
            <option value="live">Live</option>
        </select>
        <select wire:model.live="filterStatus" class="select-glass">
            <option value="">All Statuses</option>
            <option value="filled">Filled</option>
            <option value="failed">Failed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="filterAsset" class="select-glass">
            <option value="">All Assets</option>
            @foreach(['BTC','ETH','SOL','XRP'] as $asset)
                <option value="{{ $asset }}">{{ $asset }}</option>
            @endforeach
        </select>
    </div>

    {{-- Table --}}
    <div class="glass-card overflow-hidden">
        <table class="table-glass">
            <thead>
                <tr class="bg-white/[0.03]">
                    <th>#</th>
                    <th>Asset</th>
                    <th>Action</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Price</th>
                    <th>Fee</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($executions as $exec)
                <tr>
                    <td class="font-mono text-white/30 text-xs">#{{ $exec->id }}</td>
                    <td class="font-bold text-white">{{ $exec->asset_symbol }}</td>
                    <td><span class="badge-{{ $exec->action }}">{{ strtoupper($exec->action) }}</span></td>
                    <td><span class="badge-{{ $exec->mode }}">{{ $exec->mode }}</span></td>
                    <td>
                        <span class="badge-{{ $exec->status }}">{{ $exec->status }}</span>
                        @if($exec->status === 'failed' && $exec->failure_reason)
                            <div class="text-xs text-neon-red/70 mt-1 max-w-[180px] truncate" title="{{ $exec->failure_reason }}">
                                {{ $exec->failure_reason }}
                            </div>
                        @endif
                    </td>
                    <td class="font-mono">€{{ number_format($exec->amountInDollars(), 2) }}</td>
                    <td class="font-mono text-white/50">
                        {{ $exec->priceInDollars() ? '€'.number_format($exec->priceInDollars(), 2) : '–' }}
                    </td>
                    <td class="font-mono text-white/40">
                        {{ $exec->feeInDollars() ? '€'.number_format($exec->feeInDollars(), 4) : '–' }}
                    </td>
                    <td class="text-xs text-white/30">{{ $exec->created_at->local()->format('d.m H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-12 text-white/30">No executions yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($executions->hasPages())
        <div class="p-4 border-t border-white/[0.05]">{{ $executions->links() }}</div>
        @endif
    </div>
</div>
