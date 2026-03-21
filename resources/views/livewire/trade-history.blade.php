<div>
    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-white">{{ $stats['total'] }}</div>
            <div class="text-xs text-white/40 mt-1">Total</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-green">{{ $stats['filled'] }}</div>
            <div class="text-xs text-white/40 mt-1">Filled</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400">{{ $stats['pending'] }}</div>
            <div class="text-xs text-white/40 mt-1">Pending</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-red">{{ $stats['failed'] }}</div>
            <div class="text-xs text-white/40 mt-1">Failed</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-orange-400">{{ $stats['cancelled'] }}</div>
            <div class="text-xs text-white/40 mt-1">Cancelled</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card p-4 mb-6 flex gap-4 items-center">
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
        <div class="ml-auto flex gap-2">
            @foreach([['failed', 'btn-neon-red'], ['cancelled', 'bg-orange-500/20 text-orange-400 border border-orange-500/30 hover:bg-orange-500/30 rounded-lg font-medium transition-colors'], ['pending', 'badge-hold border border-white/20 hover:bg-white/10 rounded-lg font-medium transition-colors']] as [$status, $btnClass])
                @if($stats[$status] > 0)
                    <button wire:click="deleteByStatus('{{ $status }}')"
                            wire:confirm="Alle {{ $stats[$status] }} {{ $status }} Executions löschen?"
                            class="{{ $btnClass }} !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ ucfirst($status) }} ({{ $stats[$status] }})
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="glass-card overflow-hidden">
        <table class="table-glass">
            <thead>
                <tr class="bg-white/[0.03]">
                    <th>#</th>
                    <th>Asset</th>
                    <th>Action</th>
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
                    <td>
                        <div class="flex items-center gap-2">
                            <x-asset-icon :symbol="$exec->asset_symbol" :size="5" />
                            <span class="font-bold text-white">{{ $exec->asset_symbol }}</span>
                        </div>
                    </td>
                    <td><span class="badge-{{ $exec->action }}">{{ strtoupper($exec->action) }}</span></td>
                    <td>
                        <span class="badge-{{ $exec->status }}">{{ strtoupper($exec->status) }}</span>
                        @if(in_array($exec->status, ['failed', 'cancelled']) && $exec->failure_reason)
                            <div class="text-xs mt-1 max-w-[180px] truncate {{ $exec->status === 'failed' ? 'text-neon-red/70' : 'text-white/40' }}" title="{{ $exec->failure_reason }}">
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
                    <td colspan="8" class="text-center py-12 text-white/30">No executions yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($executions->hasPages())
        <div class="p-4 border-t border-white/[0.05]">{{ $executions->links() }}</div>
        @endif
    </div>
</div>
