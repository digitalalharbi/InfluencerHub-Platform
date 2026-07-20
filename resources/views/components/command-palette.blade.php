@props(['portal' => 'agency'])
@php
    // تُبنى قائمة الأوامر من إعداد التنقّل المركزي (وجهات) — بيانات تنقّل غير حسّاسة فقط.
    $config = config('navigation.' . $portal, ['groups' => []]);
    $commands = [];
    foreach ($config['groups'] ?? [] as $g) {
        $groupLabel = __('navigation.groups.' . $g['key']);
        foreach ($g['items'] ?? [] as $it) {
            if (! empty($it['soon'])) continue;
            $commands[] = [
                'label' => __('navigation.items.' . $it['key']),
                'route' => $it['route'],
                'group' => $groupLabel,
                'icon'  => $it['icon'] ?? 'circle',
            ];
        }
    }
    $commandsJson = json_encode(array_values($commands), JSON_UNESCAPED_UNICODE);
@endphp
<div
    x-data="{
        open: false,
        q: '',
        active: 0,
        commands: {{ $commandsJson }},
        get filtered() {
            const s = this.q.trim().toLowerCase();
            if (! s) return this.commands;
            return this.commands.filter(c => (c.label + ' ' + c.group).toLowerCase().includes(s));
        },
        show() { this.open = true; this.q = ''; this.active = 0; this.$nextTick(() => this.$refs.input?.focus()); },
        hide() { this.open = false; },
        move(d) {
            const n = this.filtered.length; if (! n) return;
            this.active = (this.active + d + n) % n;
            this.$nextTick(() => this.$refs.list?.querySelector('[data-active=true]')?.scrollIntoView({ block: 'nearest' }));
        },
        choose() { const c = this.filtered[this.active]; if (c) window.location.href = c.route; },
    }"
    @keydown.window.cmd.k.prevent="show()"
    @keydown.window.ctrl.k.prevent="show()"
    @keydown.escape.window="hide()"
    @ih-open-command-palette.window="show()"
>
    <template x-if="open">
        <div class="ih-cmdk__backdrop" @click.self="hide()">
            <div class="ih-cmdk" role="dialog" aria-modal="true" aria-label="لوحة الأوامر">
                <div class="ih-cmdk__search">
                    <x-icon name="search" :size="18"/>
                    <input x-ref="input" x-model="q" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter.prevent="choose()" type="text" placeholder="اقفز إلى صفحة… أو ابحث في التنقّل" aria-label="بحث الأوامر">
                    <kbd class="ih-kbd">Esc</kbd>
                </div>
                <div class="ih-cmdk__list" x-ref="list">
                    <template x-for="(c, i) in filtered" :key="c.route + i">
                        <a :href="c.route" class="ih-cmdk__item" :data-active="i === active" @mouseenter="active = i">
                            <span class="ih-cmdk__label" x-text="c.label"></span>
                            <span class="ih-cmdk__group" x-text="c.group"></span>
                        </a>
                    </template>
                    <div x-show="filtered.length === 0" class="ih-cmdk__empty">لا نتائج مطابقة</div>
                </div>
                <div class="ih-cmdk__foot">
                    <span><kbd class="ih-kbd">↑</kbd><kbd class="ih-kbd">↓</kbd> تنقّل</span>
                    <span><kbd class="ih-kbd">↵</kbd> فتح</span>
                    <span class="ih-cmdk__hint">⌘K / Ctrl+K</span>
                </div>
            </div>
        </div>
    </template>
</div>
