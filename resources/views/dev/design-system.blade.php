@extends('layouts.app')
@section('title', 'نظام التصميم')
@section('heading', 'نظام التصميم — InfluencerHub Design System')
@section('content')
@php
    $primaryScale = ['50','100','200','300','400','500','600','700','800','900','950'];
    $accentScale  = ['50','100','400','500','600','700'];
    $inkScale     = ['950','900','850','800'];
    $grayScale    = ['25','50','100','200','300','400','500','600','700','800','900'];
    $states = [
        ['success','نجاح'], ['warning','تحذير'], ['danger','خطر'], ['info','معلومة'],
    ];
    $statuses = [
        ['draft','مسودة'], ['submitted','مُرسل'], ['under_review','قيد المراجعة'],
        ['changes_requested','تعديلات مطلوبة'], ['approved','معتمد'], ['active','نشِط'],
        ['paused','موقوف مؤقتًا'], ['rejected','مرفوض'], ['completed','مكتمل'], ['archived','مؤرشف'],
    ];
    $typeScale = [
        ['--ih-fs-display','Display','عنوان رئيسي كبير'],
        ['--ih-fs-page','Page','عنوان صفحة'],
        ['--ih-fs-section','Section','عنوان قسم'],
        ['--ih-fs-card','Card','عنوان بطاقة'],
        ['--ih-fs-body','Body','نص أساسي'],
        ['--ih-fs-sm','Small','نص صغير'],
        ['--ih-fs-caption','Caption','تعليق'],
    ];
@endphp

<div x-data="{ modal:false, vw:'375' }">

{{-- مقدّمة --}}
<div class="ih-panel" style="padding:1.2rem 1.4rem; margin-bottom:1.4rem;">
    <div style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;">
        <x-ih-logo :size="30" color="var(--ih-primary)"/>
        <div>
            <div style="font-weight:800; font-size:1.15rem;">نظام تصميم InfluencerHub (بادئة <code style="direction:ltr; color:var(--ih-primary);">ih-</code>)</div>
            <div style="color:var(--ih-text-muted); font-size:.85rem;">هوية أصلية مستقلة — بنفسجي <code style="direction:ltr;">#6252E5</code> + سماوي <code style="direction:ltr;">#06B6D4</code>. مرجع بصري حيّ للـ tokens والمكوّنات. <b>صفحة تطوير فقط (محجوبة في الإنتاج).</b></div>
        </div>
    </div>
</div>

@php $sec = 'margin:0 0 .8rem; font-weight:800; font-size:1.05rem; display:flex; align-items:center; gap:.5rem;'; @endphp

{{-- ============ الألوان ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🎨 الألوان — Color Tokens</h3>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:.6rem 0 .3rem;">Primary — بنفسجي (الهوية)</div>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
        @foreach($primaryScale as $s)
            <div style="flex:1; min-width:62px; text-align:center;">
                <div style="height:52px; border-radius:var(--ih-radius-sm); background:var(--ih-primary-{{ $s }}); border:1px solid var(--ih-border);"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.25rem; direction:ltr;">{{ $s }}</div>
            </div>
        @endforeach
    </div>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .3rem;">Accent — سماوي (ذكاء / تكامل / مباشر)</div>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
        @foreach($accentScale as $s)
            <div style="flex:1; min-width:62px; text-align:center;">
                <div style="height:52px; border-radius:var(--ih-radius-sm); background:var(--ih-accent-{{ $s }}); border:1px solid var(--ih-border);"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.25rem; direction:ltr;">{{ $s }}</div>
            </div>
        @endforeach
    </div>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .3rem;">Ink — أسطح داكنة (الشريط الجانبي / auth)</div>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
        @foreach($inkScale as $s)
            <div style="flex:1; min-width:80px; text-align:center;">
                <div style="height:52px; border-radius:var(--ih-radius-sm); background:var(--ih-ink-{{ $s }});"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.25rem; direction:ltr;">{{ $s }}</div>
            </div>
        @endforeach
    </div>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .3rem;">Neutral — رمادي</div>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
        @foreach($grayScale as $s)
            <div style="flex:1; min-width:56px; text-align:center;">
                <div style="height:44px; border-radius:var(--ih-radius-sm); background:var(--ih-gray-{{ $s }}); border:1px solid var(--ih-border);"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.25rem; direction:ltr;">{{ $s }}</div>
            </div>
        @endforeach
    </div>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .3rem;">State — ألوان الحالات</div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.6rem;">
        @foreach($states as [$k,$ar])
            <div style="border:1px solid var(--ih-border); border-radius:var(--ih-radius-sm); overflow:hidden;">
                <div style="height:40px; background:var(--ih-{{ $k }});"></div>
                <div style="display:flex; align-items:center; gap:.4rem; padding:.5rem .6rem;">
                    <span style="width:16px; height:16px; border-radius:4px; background:var(--ih-{{ $k }}-soft); border:1px solid var(--ih-{{ $k }});"></span>
                    <span style="font-size:.8rem; font-weight:600;">{{ $ar }}</span>
                    <code style="margin-inline-start:auto; font-size:.68rem; color:var(--ih-text-muted); direction:ltr;">--ih-{{ $k }}</code>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ============ الطباعة ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🔤 الطباعة — Typography</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.8rem;">الخط: <b style="color:var(--ih-text);">IBM Plex Sans Arabic</b> + Inter (لاتيني). المقياس تجاوبي عبر <code style="direction:ltr;">clamp()</code>.</div>
    @foreach($typeScale as [$var,$en,$ar])
        <div style="display:flex; align-items:baseline; gap:1rem; padding:.5rem 0; border-bottom:1px solid var(--ih-border);">
            <div style="font-size:var({{ $var }}); font-weight:700; line-height:1.2; flex:1;">{{ $ar }} · InfluencerHub</div>
            <code style="font-size:.68rem; color:var(--ih-text-muted); direction:ltr; white-space:nowrap;">{{ $en }} · {{ $var }}</code>
        </div>
    @endforeach
</div>

{{-- ============ الأزرار ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🔘 الأزرار — Buttons</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.6rem;">الأنواع (min-height 40px، لمسة ≥ 44px على الجوال)</div>
    <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center;">
        <button class="btn btn-primary">أساسي</button>
        <button class="btn btn-secondary">ثانوي</button>
        <button class="btn btn-outline">محدّد</button>
        <button class="btn btn-ghost">شبح</button>
        <button class="btn btn-danger">خطر</button>
        <button class="btn btn-primary" disabled style="opacity:.5;">معطّل</button>
    </div>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .6rem;">الأحجام</div>
    <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center;">
        <button class="btn btn-primary btn-xs">xs</button>
        <button class="btn btn-primary btn-sm">sm</button>
        <button class="btn btn-primary">md</button>
        <button class="btn btn-primary btn-lg">lg</button>
    </div>
    <div style="margin-top:1rem;"><button class="btn btn-primary btn-block">زر بعرض كامل (btn-block)</button></div>
</div>

{{-- ============ الحقول ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">📝 الحقول — Form Fields</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
        <div>
            <label class="label" for="ds-name">حقل نصّي</label>
            <input id="ds-name" class="field" type="text" placeholder="اكتب هنا…">
        </div>
        <div>
            <label class="label" for="ds-sel">قائمة اختيار</label>
            <select id="ds-sel" class="field"><option>خيار أول</option><option>خيار ثانٍ</option></select>
        </div>
        <div>
            <label class="label" for="ds-email">بريد إلكتروني</label>
            <input id="ds-email" class="field" type="email" placeholder="name@example.com" inputmode="email" style="direction:ltr;">
        </div>
        <div style="grid-column:1/-1;">
            <label class="label" for="ds-note">منطقة نص</label>
            <textarea id="ds-note" class="field" rows="3" placeholder="ملاحظات…"></textarea>
        </div>
    </div>
    <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; margin-top:.8rem; color:var(--ih-text-secondary);"><input type="checkbox"> خيار قابل للتحديد</label>
    <div style="color:var(--ih-text-muted); font-size:.72rem; margin-top:.5rem;">حجم الخط 16px على الجوال لمنع تكبير iOS التلقائي · حلقة تركيز بنفسجية.</div>
</div>

{{-- ============ البطاقات ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🗂️ البطاقات والأسطح — Cards & Surfaces</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem;">
        <div class="card" style="padding:1rem;">
            <div style="font-weight:700; margin-bottom:.3rem;">card</div>
            <div style="color:var(--ih-text-muted); font-size:.82rem;">سطح أبيض مع ظل ناعم وحدود.</div>
        </div>
        <div class="ih-panel" style="padding:1rem;">
            <div style="font-weight:700; margin-bottom:.3rem;">ih-panel</div>
            <div style="color:var(--ih-text-muted); font-size:.82rem;">لوحة بخلفية خفيفة للتجميع.</div>
        </div>
        <div class="card" style="padding:1rem; border-inline-start:3px solid var(--ih-primary);">
            <div style="font-weight:700; margin-bottom:.3rem;">بطاقة مميّزة</div>
            <div style="color:var(--ih-text-muted); font-size:.82rem;">حدّ جانبي بلون الهوية.</div>
        </div>
    </div>
</div>

{{-- ============ الشارات والحالات ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🏷️ الشارات ونظام الحالات الموحّد — Badges & Status</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.6rem;">شارات عامة</div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        <span class="badge badge-active">نشِط</span>
        <span class="badge badge-lead">عميل محتمل</span>
        <span class="badge badge-qualified">مؤهَّل</span>
        <span class="badge badge-inactive">غير نشِط</span>
        <span class="badge badge-plain" style="background:var(--ih-gray-100); color:var(--ih-gray-600);">محايد</span>
    </div>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1rem 0 .6rem;">نظام الحالات الموحّد (<code style="direction:ltr;">ih-status-*</code>) — لغة حالة واحدة لكل الوحدات</div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        @foreach($statuses as [$k,$ar])
            <span class="badge ih-status-{{ $k }}">{{ $ar }}</span>
        @endforeach
    </div>
</div>

{{-- ============ الجداول ============ --}}
<div class="card" style="padding:0; margin-bottom:1.4rem; overflow:hidden;">
    <h3 style="{{ $sec }} padding:1.3rem 1.4rem 0;">📊 الجداول — Tables</h3>
    <div class="ih-table-wrap" style="padding:1rem 1.4rem 1.4rem;">
        <table class="table">
            <thead><tr><th>المبدع</th><th>المنصّة</th><th>الحالة</th><th>القيمة</th></tr></thead>
            <tbody>
                <tr><td style="font-weight:600;">رنـد</td><td>Instagram</td><td><span class="badge ih-status-active">نشِط</span></td><td style="direction:ltr;">12,500 SAR</td></tr>
                <tr><td style="font-weight:600;">سارة</td><td>TikTok</td><td><span class="badge ih-status-under_review">قيد المراجعة</span></td><td style="direction:ltr;">8,000 SAR</td></tr>
                <tr><td style="font-weight:600;">أحمد</td><td>YouTube</td><td><span class="badge ih-status-completed">مكتمل</span></td><td style="direction:ltr;">21,000 SAR</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ============ النوافذ ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🪟 النوافذ — Modal</h3>
    <button class="btn btn-primary" @click="modal=true">افتح نافذة</button>
    <template x-if="modal">
        <div class="modal-backdrop" @click.self="modal=false" style="position:fixed; inset:0; background:rgba(8,13,26,.55); display:flex; align-items:center; justify-content:center; z-index:var(--ih-z-modal); padding:1rem;">
            <div class="card" style="max-width:420px; width:100%; padding:1.4rem;">
                <div style="font-weight:800; font-size:1.1rem; margin-bottom:.4rem;">عنوان النافذة</div>
                <div style="color:var(--ih-text-muted); font-size:.88rem; margin-bottom:1.2rem;">محتوى النافذة مع خلفية معتمة قابلة للإغلاق (Esc / نقرة خارجية).</div>
                <div style="display:flex; gap:.6rem; justify-content:flex-start;">
                    <button class="btn btn-primary" @click="modal=false">تأكيد</button>
                    <button class="btn btn-ghost" @click="modal=false">إلغاء</button>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- ============ الرموز التصميمية ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">📐 الزوايا والظلال والمسافات — Radius / Shadow / Spacing</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.5rem;">Radius</div>
    <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
        @foreach(['--ih-radius-sm'=>'sm','--ih-radius'=>'md','--ih-radius-lg'=>'lg','--ih-radius-xl'=>'xl','--ih-radius-pill'=>'pill'] as $v=>$n)
            <div style="text-align:center;">
                <div style="width:64px; height:48px; background:var(--ih-primary-100); border:1px solid var(--ih-primary-300); border-radius:var({{ $v }});"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.25rem; direction:ltr;">{{ $n }}</div>
            </div>
        @endforeach
    </div>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:1.2rem 0 .5rem;">Shadow</div>
    <div style="display:flex; flex-wrap:wrap; gap:1.4rem;">
        @foreach(['--ih-shadow-xs'=>'xs','--ih-shadow-sm'=>'sm','--ih-shadow'=>'base','--ih-shadow-lg'=>'lg'] as $v=>$n)
            <div style="text-align:center;">
                <div style="width:80px; height:52px; background:var(--ih-surface); border-radius:var(--ih-radius); box-shadow:var({{ $v }});"></div>
                <div style="font-size:.66rem; color:var(--ih-text-muted); margin-top:.4rem; direction:ltr;">{{ $n }}</div>
            </div>
        @endforeach
    </div>
</div>

{{-- ============ مكوّنات مساحة العمل التفاعلية ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">🧩 مساحة العمل التفاعلية — Workspace Components</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.9rem;">رأس مساحة العمل + شريط الملخّص + شارة الحالة الموحّدة + لوحة الأوامر (⌘K).</div>

    <x-workspace-header
        title="حملة إطلاق مجموعة الصيف"
        eyebrow="حملة · CMP-1-0042"
        status="active"
        back="#"
        :meta="['العميل' => 'نايك السعودية', 'المنصّة' => 'Instagram · TikTok', 'الميزانية' => '120,000 SAR']">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm">{{ __('actions.submit_for_review') }}</button>
            <button class="btn btn-ghost btn-sm">{{ __('actions.edit') }}</button>
        </x-slot:actions>
    </x-workspace-header>

    <x-summary-strip :items="[
        ['label' => 'المخرجات', 'value' => '12', 'icon' => 'image'],
        ['label' => 'المعتمد', 'value' => '7', 'tone' => 'success', 'icon' => 'shield-check'],
        ['label' => 'قيد المراجعة', 'value' => '3', 'tone' => 'warning', 'icon' => 'clipboard-check'],
        ['label' => 'المتبقّي', 'value' => '2', 'tone' => 'primary'],
    ]"/>

    <div style="color:var(--ih-text-muted); font-size:.8rem; margin:.4rem 0 .5rem;">شارات الحالة الموحّدة عبر <code style="direction:ltr;">&lt;x-status-badge&gt;</code></div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        @foreach(['draft','submitted','under_review','changes_requested','approved','active','rejected','completed'] as $st)
            <x-status-badge :status="$st"/>
        @endforeach
    </div>

    <div style="margin-top:1.1rem;">
        <button type="button" class="ih-cmdk__trigger" @click="$dispatch('ih-open-command-palette')">
            <x-icon name="search" :size="16"/><span>جرّب لوحة الأوامر</span><kbd class="ih-kbd" style="direction:ltr;">⌘K</kbd>
        </button>
    </div>
</div>

{{-- ============ مبدّل العرض التجاوبي ============ --}}
<div class="card" style="padding:1.3rem 1.4rem; margin-bottom:1.4rem;">
    <h3 style="{{ $sec }}">📱 مبدّل العرض التجاوبي — Responsive Viewport (320→1440)</h3>
    <div style="color:var(--ih-text-muted); font-size:.8rem; margin-bottom:.7rem;">معاينة حيّة لصفحة الدخول التسويقية عبر عروض مختلفة (تتحوّل لعمود واحد ≤ 900px).</div>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.9rem;">
        @foreach(['320'=>'320 · موبايل صغير','375'=>'375 · موبايل','768'=>'768 · لوحي','1024'=>'1024 · لوحي عريض','1280'=>'1280 · سطح مكتب','1440'=>'1440 · واسع'] as $w=>$lbl)
            <button class="btn btn-sm" :class="vw==='{{ $w }}' ? 'btn-primary' : 'btn-outline'" @click="vw='{{ $w }}'">{{ $lbl }}</button>
        @endforeach
    </div>
    <div style="background:var(--ih-surface-sunken); border-radius:var(--ih-radius); padding:1rem; overflow-x:auto;">
        <div style="margin:0 auto; transition:width var(--ih-motion) var(--ih-ease); box-shadow:var(--ih-shadow-lg); border-radius:var(--ih-radius-sm); overflow:hidden; background:#fff;"
             :style="'width:' + vw + 'px; max-width:100%;'">
            <iframe src="/login" title="معاينة الدخول" style="width:100%; height:560px; border:0; display:block;"></iframe>
        </div>
    </div>
    <div style="color:var(--ih-text-muted); font-size:.72rem; margin-top:.6rem; direction:ltr;">
        <span x-text="'preview width: ' + vw + 'px'"></span>
    </div>
</div>

<div style="color:var(--ih-text-muted); font-size:.78rem; text-align:center; padding:1rem 0 2rem;">
    نظام تصميم InfluencerHub · ih-tokens · صفحة تطوير محجوبة في الإنتاج ·
    <a href="/app/preview" style="color:var(--ih-primary);">← مركز المعاينة</a>
</div>

</div>
@endsection
