@props(['text'])
<span x-data="{ above: true }" x-init="
    const rect = $el.getBoundingClientRect();
    above = rect.top > 100;
" class="group relative inline-flex cursor-help" aria-label="{{ $text }}">
    <x-phosphor-question class="h-3.5 w-3.5 text-sand-400 transition-colors group-hover:text-amber-500" />
    <span role="tooltip" :class="above ? 'bottom-full mb-2' : 'top-full mt-2'" class="pointer-events-none absolute left-1/2 z-10 -translate-x-1/2 whitespace-normal rounded-lg bg-sand-900 px-3 py-2 text-xs leading-relaxed text-sand-100 opacity-0 shadow-lg transition-opacity group-hover:opacity-100" style="width: max-content; max-width: 220px;">
        {{ $text }}
        <span :class="above ? 'top-full border-t-sand-900' : 'bottom-full border-b-sand-900'" class="absolute left-1/2 -translate-x-1/2 border-4 border-transparent"></span>
    </span>
</span>
