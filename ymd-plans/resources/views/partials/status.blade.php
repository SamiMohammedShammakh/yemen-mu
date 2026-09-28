{{-- حالة الإنجاز: $s = ['key'=>..., 'label'=>...] --}}
<span class="badge {{ $s['key'] ?? 'no_data' }}"><span class="status-dot {{ $s['key'] ?? '' }}"></span>{{ $s['label'] ?? '—' }}</span>
