<x-filament::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .dlv-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; }
        .dlv-stat { background:#0f172a; color:#fff; border-radius:14px; padding:16px 18px; }
        .dlv-stat__label { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; opacity:.6; }
        .dlv-stat__value { font-size:2rem; font-weight:700; line-height:1.15; margin-top:4px; }
        .dlv-stat__value .dlv-dot { display:inline-block; width:10px; height:10px; border-radius:9999px; background:#22c55e; margin-right:8px; vertical-align:middle; animation: dlv-blink 1.6s infinite; }
        @keyframes dlv-blink { 0%,100%{opacity:1} 50%{opacity:.35} }

        .dlv-map { height: 520px; border-radius:14px; overflow:hidden; background:#0b1020; }
        .dlv-dot-marker span { display:block; width:12px; height:12px; border-radius:9999px; background:#22c55e; box-shadow:0 0 0 0 rgba(34,197,94,.65); animation: dlv-pulse 1.8s infinite; }
        @keyframes dlv-pulse { 0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)} 70%{box-shadow:0 0 0 16px rgba(34,197,94,0)} 100%{box-shadow:0 0 0 0 rgba(34,197,94,0)} }
        .leaflet-container { background:#0b1020; }

        .dlv-list-row { display:flex; justify-content:space-between; gap:.5rem; padding:.4rem 0; border-bottom:1px solid rgba(127,127,127,.15); font-size:.875rem; }
        .dlv-list-row span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    </style>

    <div wire:poll.5s="pollData" style="display:flex; flex-direction:column; gap:1rem;">
        {{-- Metric-kaarten --}}
        <div class="dlv-grid">
            <div class="dlv-stat">
                <div class="dlv-stat__label">Bezoekers nu</div>
                <div class="dlv-stat__value"><span class="dlv-dot"></span>{{ $liveCount }}</div>
            </div>
            @if($showCart)
                <div class="dlv-stat">
                    <div class="dlv-stat__label">Actieve mandjes</div>
                    <div class="dlv-stat__value">{{ $activeCarts }}</div>
                </div>
                <div class="dlv-stat">
                    <div class="dlv-stat__label">Waarde in mandjes</div>
                    <div class="dlv-stat__value">€ {{ number_format($cartTotal, 2, ',', '.') }}</div>
                </div>
                <div class="dlv-stat">
                    <div class="dlv-stat__label">Omzet vandaag</div>
                    <div class="dlv-stat__value">€ {{ number_format($revenueToday, 2, ',', '.') }}</div>
                </div>
            @endif
            <div class="dlv-stat">
                <div class="dlv-stat__label">Landen</div>
                <div class="dlv-stat__value">{{ count($countries) }}</div>
            </div>
        </div>

        @if($showCart)
            {{-- Funnel: live bezoekers -> met mandje -> bestellingen vandaag --}}
            <x-filament::section>
                <x-slot name="heading">Funnel</x-slot>
                @php($cartPct = $liveCount > 0 ? round($activeCarts / $liveCount * 100) : 0)
                <div style="display:flex; align-items:stretch; gap:.5rem; flex-wrap:wrap;">
                    <div style="flex:1; min-width:140px; background:#0f172a; color:#fff; border-radius:12px; padding:14px 16px;">
                        <div style="font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Live bezoekers</div>
                        <div style="font-size:1.6rem; font-weight:700;">{{ $liveCount }}</div>
                    </div>
                    <div style="display:flex; align-items:center; opacity:.4; font-size:1.2rem;">&rarr;</div>
                    <div style="flex:1; min-width:140px; background:#0f172a; color:#fff; border-radius:12px; padding:14px 16px;">
                        <div style="font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Met mandje</div>
                        <div style="font-size:1.6rem; font-weight:700;">{{ $activeCarts }} <span style="font-size:.8rem; opacity:.6;">({{ $cartPct }}%)</span></div>
                    </div>
                    <div style="display:flex; align-items:center; opacity:.4; font-size:1.2rem;">&rarr;</div>
                    <div style="flex:1; min-width:140px; background:#16a34a; color:#fff; border-radius:12px; padding:14px 16px;">
                        <div style="font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; opacity:.75;">Bestellingen vandaag</div>
                        <div style="font-size:1.6rem; font-weight:700;">{{ $ordersToday }}</div>
                    </div>
                </div>
                <p style="opacity:.55; font-size:.75rem; margin-top:.5rem;">Live bezoekers en mandjes zijn een momentopname; bestellingen zijn het totaal van vandaag.</p>
            </x-filament::section>
        @endif

        {{-- Kaart --}}
        <div wire:ignore
            x-data="{
                map: null,
                layer: null,
                userMoved: false,
                autoFitting: false,
                init() {
                    if (typeof L === 'undefined') { return; }
                    this.map = L.map($el, { worldCopyJump: true, attributionControl: false }).setView([25, 0], 2);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 18, subdomains: 'abcd' }).addTo(this.map);
                    this.layer = L.layerGroup().addTo(this.map);
                    this.map.on('zoomstart dragstart', () => { if (! this.autoFitting) { this.userMoved = true; } });
                    this.draw($wire.points);
                    window.addEventListener('resize', () => this.map && this.map.invalidateSize());
                },
                draw(points) {
                    if (! this.map || ! this.layer) { return; }
                    this.layer.clearLayers();
                    const coords = [];
                    (points || []).forEach((p) => {
                        if (! p.lat || ! p.lng) { return; }
                        coords.push([p.lat, p.lng]);
                        const label = [p.city, p.country].filter(Boolean).join(', ')
                            + (p.cart > 0 ? ' — mandje € ' + p.cart.toFixed(2) : '');
                        const icon = L.divIcon({ className: 'dlv-dot-marker', html: '<span></span>', iconSize: [12, 12] });
                        L.marker([p.lat, p.lng], { icon }).addTo(this.layer).bindPopup(label || 'Bezoeker');
                    });
                    // Focus op de bezoekers: pas de uitsnede aan zodat iedereen in
                    // beeld staat (niet de hele wereldkaart). Stopt met auto-zoomen
                    // zodra de admin zelf de kaart heeft versleept of gezoomd.
                    if (coords.length && ! this.userMoved) {
                        this.autoFitting = true;
                        this.map.fitBounds(coords, { padding: [40, 40], maxZoom: 6 });
                        this.map.once('moveend', () => { this.autoFitting = false; });
                    }
                },
            }"
            x-effect="draw($wire.points)"
            class="dlv-map"></div>

        {{-- Lijsten --}}
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; align-items:start;">
            <x-filament::section>
                <x-slot name="heading">Top landen</x-slot>
                @forelse($countries as $country)
                    <div class="dlv-list-row"><span>{{ $country['name'] }}</span><strong>{{ $country['count'] }}</strong></div>
                @empty
                    <p style="opacity:.6; font-size:.875rem;">Geen live bezoekers.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Drukst bekeken pagina's</x-slot>
                @forelse($topPages as $page)
                    <div class="dlv-list-row"><span>{{ $page['path'] }}</span><strong>{{ $page['count'] }}</strong></div>
                @empty
                    <p style="opacity:.6; font-size:.875rem;">Geen live bezoekers.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>
</x-filament::page>
