@props(['portal' => 'agency', 'badges' => null])
@php
    use App\Support\Navigation\NavigationBadges;
    $config = config('navigation.' . $portal, ['groups' => []]);
    $badges = is_array($badges) ? $badges : ($portal === 'agency' ? NavigationBadges::all() : []);

    // اجمع عناصر التنقّل السفلي (mobile=true) عبر كل المجموعات، بحد أقصى 5 وجهات.
    $items = collect($config['groups'] ?? [])
        ->flatMap(fn ($g) => $g['items'] ?? [])
        ->filter(fn ($i) => ! empty($i['mobile']) && empty($i['soon']))
        ->take(5)->values();

    $isActive = function (array $item): bool {
        $route = $item['route'] ?? '#';
        $match = $item['match'] ?? null;
        if ($match === null) {
            if (str_contains($route, '?')) return request()->fullUrlIs(url($route));
            return request()->is(ltrim($route, '/')) && ! request()->has('type');
        }
        return request()->is($match);
    };
@endphp
@if($items->isNotEmpty())
<nav class="ih-bottom-nav" aria-label="التنقّل السريع">
    @foreach($items as $item)
        @php
            $active = $isActive($item);
            $label = __('navigation.items.' . $item['key']);
            $count = ($item['badge'] ?? null) ? ($badges[$item['badge']] ?? 0) : 0;
        @endphp
        <a href="{{ $item['route'] }}" class="ih-bottom-nav__link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
            <span class="ih-bottom-nav__icon">
                <x-icon :name="$item['icon']" :size="22"/>
                @if($count > 0)<span class="ih-bottom-nav__dot" aria-hidden="true"></span>@endif
            </span>
            <span class="ih-bottom-nav__label">{{ $label }}</span>
        </a>
    @endforeach
</nav>
@endif
