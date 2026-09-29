@foreach($attachments as $att)
    @if(! empty($att['is_image']))
        <a href="{{ $att['url'] }}" target="_blank" rel="noopener" style="display:block; margin-top:6px;"><img src="{{ $att['thumb_url'] ?? $att['url'] }}" alt="" style="max-width:180px; border-radius:8px; border:0; display:block;"></a>
    @else
        <a href="{{ $att['url'] }}" target="_blank" rel="noopener" style="display:inline-block; margin-top:6px; font-family: Arial, Helvetica, sans-serif; font-size:12px; color:{{ $color }}; text-decoration:underline;">&#128206; {{ $att['name'] ?? 'Bijlage' }}</a>
    @endif
@endforeach
