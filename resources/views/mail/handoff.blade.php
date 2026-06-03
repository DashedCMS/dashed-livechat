<p>Een bezoeker vraagt om een medewerker in een chatgesprek.</p>
@if($reason)<p>Reden: {{ $reason }}</p>@endif
<p>Site: {{ $conversation->site_id }}</p>
@if($url)<p><a href="{{ $url }}">Open het gesprek</a></p>@endif
