@php
    $classes = [
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'rose'    => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        'sky'     => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    ];
@endphp

<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $classes[$status->color()] ?? $classes['sky'] }}">
    {{ $status->label() }}
</span>
