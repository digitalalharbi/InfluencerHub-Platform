@props(['portal' => 'agency', 'badges' => null])
@php
    use App\Support\Navigation\NavigationBadges;
    $config = config('navigation.' . $portal, ['groups' => []]);
    // على مستوى الوكالة تُحسب من NavigationBadges؛ البوابات الأخرى تمرّر عدّاداتها الخاصة.
    $badges = is_array($badges) ? $badges : ($portal === 'agency' ? NavigationBadges::all() : []);

    // تحديد العنصر النشِط (يدعم مسارات ذات query مثل ?type=influencer)
    $isActive = function (array $item): bool {
        $route = $item['route'] ?? '#';
        $match = $item['match'] ?? null;
        if ($route === '#') return false;
        if ($match === null) {
            if (str_contains($route, '?')) return request()->fullUrlIs(url($route));
            return request()->is(ltrim($route, '/')) && ! request()->has('type');
        }
        return request()->is($match);
    };
@endphp
<nav {{ $attributes->merge(['class' => 'ih-nav']) }} style="display:flex; flex-direction:column; gap:.1rem; flex:1; overflow-y:auto;">
    @foreach($config['groups'] as $group)
        @if(! empty($group['items']))
            @if(($group['key'] ?? '') !== 'overview')
                <div class="ih-nav__group">{{ __('navigation.groups.' . $group['key']) }}</div>
            @endif
            @foreach($group['items'] as $item)
                @php
                    $active = $isActive($item);
                    $label = __('navigation.items.' . $item['key']);
                    $count = ($item['badge'] ?? null) ? ($badges[$item['badge']] ?? 0) : 0;
                    $desc = ($item['desc'] ?? null) ? __('navigation.descriptions.' . $item['desc']) : null;
                @endphp
                @if(! empty($item['soon']))
                    <span class="nav-link ih-nav__link" style="opacity:.45; cursor:default; justify-content:space-between;">
                        <span style="display:flex; align-items:center; gap:.6rem;"><x-icon :name="$item['icon']"/> {{ $label }}</span>
                        <span class="badge badge-plain" style="background:var(--ih-gray-100); color:var(--ih-gray-500); font-size:.62rem;">قريبًا</span>
                    </span>
                @else
                    <a href="{{ $item['route'] }}" @if($desc) title="{{ $desc }}" @endif
                       class="nav-link ih-nav__link {{ $active ? 'active' : '' }}"
                       @if($active) aria-current="page" @endif
                       style="display:flex; align-items:center; gap:.6rem; justify-content:space-between;">
                        <span style="display:flex; align-items:center; gap:.6rem; min-width:0;">
                            <x-icon :name="$item['icon']"/>
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $label }}</span>
                        </span>
                        @if($count > 0)
                            <span class="ih-nav__badge" aria-label="{{ $count }} بانتظار الإجراء">{{ $count > 99 ? '99+' : $count }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        @endif
    @endforeach
</nav>
