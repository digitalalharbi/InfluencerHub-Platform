@extends('layouts.public')
@section('title', 'متابعة الطلب')
@section('content')
@php
    $steps = ['draft'=>'مسودة','email_verification_pending'=>'تأكيد البريد','submitted'=>'مُرسَل','under_review'=>'قيد المراجعة','completion_required'=>'مطلوب استكمال','approved'=>'مقبول','rejected'=>'مرفوض'];
    $editable = $app->isEditableByApplicant();
@endphp
<div class="card" style="padding:1.5rem; margin-bottom:1.2rem;">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div style="flex:1;">
            <div style="color:var(--text-muted); font-size:.8rem;">رقم المرجع</div>
            <div style="font-weight:800; font-size:1.1rem; direction:ltr; text-align:right;">{{ $app->reference }}</div>
        </div>
        <span class="badge badge-{{ in_array($app->status,['approved'])?'active':(in_array($app->status,['rejected'])?'suspended':'lead') }}" style="font-size:.85rem;">{{ $steps[$app->status] ?? $app->status }}</span>
    </div>
    <p style="color:var(--text-muted); font-size:.82rem; margin-top:.8rem;">احفظ هذا الرابط لمتابعة طلبك لاحقًا.</p>
</div>

@if($app->status==='approved')
    <div class="card" style="padding:1.5rem; text-align:center; border-inline-start:3px solid var(--success);">
        <div style="font-size:2rem;">✅</div><h2 style="font-weight:800;">تم قبول طلبك</h2>
        <p style="color:var(--text-muted);">سيصلك رابط تفعيل حساب المبدع عبر بريدك.</p>
    </div>
@elseif(in_array($app->status,['submitted','under_review']))
    <div class="card" style="padding:1.5rem; text-align:center;">
        <div style="font-size:2rem;">🕐</div><h2 style="font-weight:800;">طلبك قيد المراجعة</h2>
        <p style="color:var(--text-muted);">سيتواصل معك فريق الوكالة قريبًا.</p>
    </div>
@else
    {{-- تأكيد البريد --}}
    <div class="card" style="padding:1.3rem; margin-bottom:1.2rem;">
        <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.8rem;">
            <h3 style="font-weight:800; margin:0;">تأكيد البريد الإلكتروني</h3>
            @if($app->email_verified_at)<span class="badge badge-active">مُتحقَّق</span>@else<span class="badge badge-lead">غير مُتحقَّق</span>@endif
        </div>
        @unless($app->email_verified_at)
            @if(session('dev_otp'))
                <div class="card" style="padding:.6rem .8rem; margin-bottom:.8rem; background:#fffbeb; border:1px dashed #d97706; font-size:.82rem;">
                    (وضع تطوير) رمز التحقق: <b style="direction:ltr;">{{ session('dev_otp') }}</b> — في الإنتاج يُرسَل عبر البريد.
                </div>
            @endif
            <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end;">
                <form method="POST" action="/join/creator/{{ $app->reference }}/verify-email">@csrf
                    <button class="btn">إرسال رمز التحقق</button>
                </form>
                <form method="POST" action="/join/creator/{{ $app->reference }}/verify-email" style="display:flex; gap:.5rem; align-items:flex-end;">@csrf
                    <div><label class="label">أدخل الرمز</label><input class="field" name="code" style="max-width:140px; direction:ltr;" placeholder="123456"></div>
                    <button class="btn btn-primary">تأكيد</button>
                </form>
            </div>
        @endunless
    </div>

    {{-- استكمال البيانات --}}
    <form method="POST" action="/join/creator/{{ $app->reference }}/continue" class="card" style="padding:1.3rem; margin-bottom:1.2rem;">@csrf
        <h3 style="font-weight:800; margin:0 0 1rem;">استكمال الملف (الخطوة 2)</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div><label class="label">الاسم المهني</label><input class="field" name="professional_name" value="{{ $app->professional_name }}"></div>
            <div><label class="label">واتساب</label><input class="field" name="whatsapp" value="{{ $app->whatsapp }}" style="direction:ltr;"></div>
        </div>
        <div style="margin-bottom:1rem;"><label class="label">النبذة</label><textarea class="field" name="bio" rows="3">{{ $app->bio }}</textarea></div>
        <div style="margin-bottom:1.2rem;"><label class="label">التصنيفات</label>
            <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
                @foreach($categories as $cat)
                    <label style="display:flex; gap:.35rem; align-items:center; font-size:.85rem; padding:.3rem .6rem; border:1px solid var(--border-strong); border-radius:999px;">
                        <input type="checkbox" name="categories[]" value="{{ $cat->slug }}" @checked(in_array($cat->slug, $app->categories ?? []))> {{ $cat->name_ar }}
                    </label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-primary">حفظ التقدّم</button>
    </form>

    {{-- الحسابات الاجتماعية (Step 3) --}}
    <div class="card" style="padding:1.3rem; margin-bottom:1.2rem;">
        <h3 style="font-weight:800; margin:0 0 1rem;">الحسابات الاجتماعية</h3>
        @forelse($app->platforms as $p)
            <div style="display:flex; justify-content:space-between; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; margin-bottom:.5rem; font-size:.88rem;">
                <span>{{ $p->platform }} · <span style="direction:ltr;">{{ '@'.$p->username }}</span></span><span style="color:var(--text-muted);">{{ number_format($p->followers_count) }} متابع</span>
            </div>
        @empty <p style="color:var(--text-muted); font-size:.85rem; margin:0 0 .8rem;">لم تُضِف حسابات بعد.</p> @endforelse
        <form method="POST" action="/join/creator/{{ $app->reference }}/platforms" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:.6rem;">@csrf
            <div style="min-width:120px;"><label class="label">المنصة</label><select class="field" name="platform">@foreach(\App\Support\Platforms\PlatformRegistry::options('creator_application') as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div style="flex:1; min-width:120px;"><label class="label">المعرّف</label><input class="field" name="username" required style="direction:ltr;"></div>
            <div style="min-width:110px;"><label class="label">المتابعون</label><input class="field" type="number" name="followers_count" min="0" value="0"></div>
            <button class="btn btn-primary">+ إضافة</button>
        </form>
    </div>

    {{-- الخدمات والأسعار (Step 4) --}}
    <div class="card" style="padding:1.3rem; margin-bottom:1.2rem;">
        <h3 style="font-weight:800; margin:0 0 1rem;">الخدمات والأسعار</h3>
        @forelse($app->services as $s)
            <div style="display:flex; justify-content:space-between; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; margin-bottom:.5rem; font-size:.88rem;">
                <span>{{ $s->service_type }}</span><span style="color:var(--brand); font-weight:700;">{{ $s->price_minor ? number_format($s->price_minor/100).' ر.س' : '—' }}</span>
            </div>
        @empty <p style="color:var(--text-muted); font-size:.85rem; margin:0 0 .8rem;">لم تُضِف خدمات بعد.</p> @endforelse
        <form method="POST" action="/join/creator/{{ $app->reference }}/services" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:.6rem;">@csrf
            <div style="min-width:140px;"><label class="label">الخدمة</label><select class="field" name="service_type">@foreach(['post'=>'منشور','story'=>'ستوري','reel'=>'ريل','tiktok_video'=>'فيديو تيك توك','youtube_video'=>'فيديو يوتيوب','ugc_video'=>'UGC فيديو','ugc_images'=>'UGC صور','product_review'=>'مراجعة','live'=>'بث','brand_ambassador'=>'سفير','custom'=>'مخصّص'] as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div style="min-width:110px;"><label class="label">السعر (ر.س)</label><input class="field" type="number" name="price" min="0" step="0.01"></div>
            <div style="min-width:100px;"><label class="label">التسليم (يوم)</label><input class="field" type="number" name="delivery_days" min="0"></div>
            <button class="btn btn-primary">+ إضافة</button>
        </form>
    </div>

    {{-- نماذج الأعمال (Step 5) --}}
    <div class="card" style="padding:1.3rem; margin-bottom:1.2rem;">
        <h3 style="font-weight:800; margin:0 0 1rem;">نماذج الأعمال</h3>
        @forelse($app->portfolios as $pf)
            <div style="padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; margin-bottom:.5rem; font-size:.85rem;">{{ $pf->type }} · {{ $pf->previous_brand ?? $pf->category ?? '—' }} <span class="badge badge-lead" style="font-size:.6rem;">{{ $pf->status }}</span></div>
        @empty <p style="color:var(--text-muted); font-size:.85rem; margin:0 0 .8rem;">لم تُضِف نماذج بعد.</p> @endforelse
        <form method="POST" action="/join/creator/{{ $app->reference }}/portfolio" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:.6rem;">@csrf
            <div style="min-width:100px;"><label class="label">النوع</label><select class="field" name="type"><option value="link">رابط</option><option value="image">صورة</option><option value="video">فيديو</option></select></div>
            <div style="flex:1; min-width:150px;"><label class="label">الرابط</label><input class="field" name="url" style="direction:ltr;" placeholder="https://…"></div>
            <div style="min-width:120px;"><label class="label">علامة سابقة</label><input class="field" name="previous_brand"></div>
            <button class="btn btn-primary">+ إضافة</button>
        </form>
    </div>

    {{-- موثوق (Step 6) --}}
    <form method="POST" action="/join/creator/{{ $app->reference }}/mowthooq" class="card" style="padding:1.3rem; margin-bottom:1.2rem;">@csrf
        <h3 style="font-weight:800; margin:0 0 1rem;">رخصة موثوق <span class="badge badge-lead" style="font-size:.6rem;">{{ $app->mowthooq_status }}</span></h3>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem; margin-bottom:1rem;">
            <div><label class="label">رقم الترخيص</label><input class="field" name="mowthooq_license_number" value="{{ $app->mowthooq_license_number }}" style="direction:ltr;"></div>
            <div><label class="label">تاريخ الإصدار</label><input class="field" type="date" name="mowthooq_issued_at" value="{{ optional($app->mowthooq_issued_at)->format('Y-m-d') }}"></div>
            <div><label class="label">تاريخ الانتهاء</label><input class="field" type="date" name="mowthooq_expires_at" value="{{ optional($app->mowthooq_expires_at)->format('Y-m-d') }}"></div>
        </div>
        <button class="btn">حفظ</button>
    </form>

    {{-- البيانات المالية (Step 7) --}}
    <form method="POST" action="/join/creator/{{ $app->reference }}/financial" class="card" style="padding:1.3rem; margin-bottom:1.2rem;">@csrf
        <h3 style="font-weight:800; margin:0 0 1rem;">البيانات المالية <span class="badge badge-lead" style="font-size:.6rem;">{{ $app->financial_verification_status }}</span></h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-bottom:.8rem;">
            <div><label class="label">اسم المستفيد</label><input class="field" name="beneficiary_name" value="{{ $app->beneficiary_name }}"></div>
            <div><label class="label">البنك</label><input class="field" name="bank_name" value="{{ $app->bank_name }}"></div>
        </div>
        <div style="margin-bottom:1rem;"><label class="label">الآيبان (IBAN)</label>
            <input class="field" name="iban" style="direction:ltr;" placeholder="{{ $app->iban_last4 ? 'المحفوظ: •••• '.$app->iban_last4 : 'SA...' }}">
            <div style="color:var(--text-muted); font-size:.72rem; margin-top:.2rem;">🔒 يُخزَّن مشفّرًا؛ يُعرض آخر 4 فقط. المحفوظ: {{ $app->iban_last4 ? '•••• '.$app->iban_last4 : 'لا يوجد' }}</div>
        </div>
        <button class="btn">حفظ</button>
    </form>

    {{-- رفع الملفات --}}
    <div class="card" style="padding:1.3rem; margin-bottom:1.2rem;">
        <h3 style="font-weight:800; margin:0 0 .3rem;">الملفات</h3>
        <p style="color:var(--text-muted); font-size:.82rem; margin:0 0 1rem;">الصورة الشخصية، مستند الآيبان، رخصة موثوق، نماذج الأعمال. الملفات خاصّة ولا تُعرض للعامة.</p>
        @foreach(['avatar'=>'الصورة الشخصية','iban_document'=>'مستند الآيبان','mowthooq_document'=>'رخصة موثوق','portfolio_image'=>'نموذج عمل (صورة)'] as $cat=>$label)
            @php $existing = $app->documents->firstWhere('kind', $cat); @endphp
            <form method="POST" action="/join/creator/{{ $app->reference }}/upload" enctype="multipart/form-data" style="display:flex; gap:.6rem; align-items:flex-end; margin-bottom:.7rem; flex-wrap:wrap;">@csrf
                <input type="hidden" name="category" value="{{ $cat }}">
                <div style="flex:1; min-width:180px;"><label class="label">{{ $label }} @if($existing)<span class="badge badge-active" style="font-size:.6rem;">مرفوع</span>@endif</label>
                    <input class="field" type="file" name="file" required></div>
                <button class="btn">رفع</button>
            </form>
        @endforeach
    </div>

    {{-- إرسال --}}
    @if($editable)
    <form method="POST" action="/join/creator/{{ $app->reference }}/submit" class="card" style="padding:1.3rem; text-align:center;">@csrf
        <p style="color:var(--text-muted); margin:0 0 1rem;">بعد استكمال بياناتك وتأكيد بريدك، أرسل طلبك للمراجعة.</p>
        <button class="btn btn-primary" style="font-size:1rem;">إرسال الطلب للمراجعة</button>
    </form>
    @endif
@endif
@endsection
