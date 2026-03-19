<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-white/40 mt-1">Manage news sources for sentiment analysis</p>
        </div>
        <button wire:click="openCreate" class="btn-neon-green">
            + Add Source
        </button>
    </div>

    {{-- Modal Form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="glass-card p-8 w-full max-w-lg">
            <h3 class="text-lg font-semibold text-white mb-6">
                {{ $editingId ? 'Edit Source' : 'Add Source' }}
            </h3>
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-xs text-white/50 mb-1.5">Name</label>
                    <input wire:model="name" type="text" class="input-glass w-full" placeholder="CoinDesk">
                    @error('name') <span class="text-neon-red text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1.5">RSS/Feed URL</label>
                    <input wire:model="url" type="url" class="input-glass w-full" placeholder="https://...">
                    @error('url') <span class="text-neon-red text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Category</label>
                        <select wire:model="category" class="select-glass w-full">
                            <option value="news">News</option>
                            <option value="social">Social</option>
                            <option value="blog">Blog</option>
                            <option value="official">Official</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Weight (0.10–2.00)</label>
                        <input wire:model="weight" type="number" step="0.1" min="0.1" max="2.0" class="input-glass w-full">
                        @error('weight') <span class="text-neon-red text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1.5">Refresh Interval (minutes)</label>
                    <input wire:model="refresh_minutes" type="number" min="5" max="1440" class="input-glass w-full">
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="$toggle('is_active')"
                        class="relative w-10 h-5 rounded-full transition-colors duration-200
                               {{ $is_active ? 'bg-neon-green/60' : 'bg-white/10' }}">
                        <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform duration-200
                                     {{ $is_active ? 'translate-x-5' : '' }}"></span>
                    </button>
                    <span class="text-sm text-white/60">Active</span>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="btn-neon-green flex-1">Save</button>
                    <button type="button" wire:click="$set('showForm', false)" class="btn-glass flex-1">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Sources Table --}}
    <div class="glass-card overflow-hidden">
        <table class="table-glass">
            <thead>
                <tr class="bg-white/[0.03]">
                    <th>Source</th>
                    <th>Category</th>
                    <th>Weight</th>
                    <th>Interval</th>
                    <th>Articles</th>
                    <th>Last Scraped</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sources as $source)
                <tr>
                    <td>
                        <div class="font-medium text-white">{{ $source->name }}</div>
                        <div class="text-xs text-white/30 truncate max-w-xs">{{ $source->url }}</div>
                    </td>
                    <td>
                        <span class="text-xs px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-white/50">
                            {{ $source->category }}
                        </span>
                    </td>
                    <td class="font-mono text-neon-blue">{{ number_format($source->weight, 2) }}</td>
                    <td class="text-white/50">{{ $source->refresh_minutes }}m</td>
                    <td class="font-mono">{{ $source->articles_count }}</td>
                    <td class="text-white/40 text-xs">
                        {{ $source->last_scraped_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td>
                        <button wire:click="toggleActive({{ $source->id }})"
                            class="text-xs px-2 py-1 rounded-full border transition-colors
                                   {{ $source->is_active
                                      ? 'bg-neon-green/10 border-neon-green/30 text-neon-green'
                                      : 'bg-white/5 border-white/10 text-white/30' }}">
                            {{ $source->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <button wire:click="openEdit({{ $source->id }})" class="btn-glass py-1 text-xs">Edit</button>
                            <button wire:click="delete({{ $source->id }})"
                                    wire:confirm="Delete this source? All articles will be deleted."
                                    class="text-xs px-3 py-1 rounded-lg bg-neon-red/10 border border-neon-red/20 text-neon-red hover:bg-neon-red/20 transition-colors">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-white/30">
                        No sources yet. Add your first source above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($sources->hasPages())
        <div class="p-4 border-t border-white/[0.05]">
            {{ $sources->links() }}
        </div>
        @endif
    </div>
</div>
