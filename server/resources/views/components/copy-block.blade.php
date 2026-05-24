@props(['code', 'label' => 'Skopiuj', 'language' => 'bash'])

<div x-data="{ copied: false }" class="relative group">
    <pre class="p-4 pr-20 bg-slate-950 text-slate-100 rounded-lg font-mono text-xs overflow-x-auto leading-relaxed select-all"><code>{{ $code }}</code></pre>
    <button type="button"
            @click="navigator.clipboard.writeText($el.previousElementSibling.innerText.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            class="absolute top-2 right-2 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition opacity-90"
            :class="copied && 'bg-emerald-600 hover:bg-emerald-600 text-white border-emerald-500'">
        <span x-show="!copied" class="inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
            </svg>
            {{ $label }}
        </span>
        <span x-show="copied" class="inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            Skopiowano
        </span>
    </button>
</div>
