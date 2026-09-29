{{-- نسبة إنجاز بتسمية واضحة دائمًا. $label: «الإنجاز مقابل …»، $v: النسبة أو null، $key: مفتاح الحالة، $empty: نص بديل --}}
@php $key = $key ?? 'no_data'; @endphp
<div class="ach">
    <span class="lbl">{{ $label }}</span>
    @if ($v === null)
        <span class="v muted">{{ $empty ?? ($key === 'not_due' ? 'لم يستحق بعد' : 'لا توجد بيانات معتمدة') }}</span>
    @else
        <span class="v"><span class="num">{{ \App\Support\Fmt::pct($v) }}</span></span>
        <div class="bar {{ $key }}"><i style="width: {{ min(100, max(0, $v)) }}%"></i></div>
    @endif
</div>
