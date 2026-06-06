<x-filament::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <div wire:poll.10s="pollData" style="display:flex; flex-direction:column; gap:1rem;">
        {{-- Statistieken --}}
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
            <x-filament::section>
                <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Live bezoekers</div>
                <div style="font-size:2rem; font-weight:700; line-height:1.2;">{{ $liveCount }}</div>
            </x-filament::section>

            @if($showCart)
                <x-filament::section>
                    <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Waarde in mandjes</div>
                    <div style="font-size:2rem; font-weight:700; line-height:1.2;">€ {{ number_format($cartTotal, 2, ',', '.') }}</div>
                </x-filament::section>
            @endif

            <x-filament::section>
                <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Landen</div>
                <div style="font-size:2rem; font-weight:700; line-height:1.2;">{{ count($countries) }}</div>
            </x-filament::section>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem; align-items:start;">
            {{-- Kaart --}}
            <x-filament::section>
                <x-slot name="heading">Waar komen ze vandaan</x-slot>

                @if(count($points))
                    <div wire:ignore
                        x-data="{
                            map: null,
                            layer: null,
                            init() {
                                if (typeof L === 'undefined') { return; }
                                this.map = L.map($el).setView([20, 0], 2);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 18 }).addTo(this.map);
                                this.layer = L.layerGroup().addTo(this.map);
                                this.draw($wire.points);
                            },
                            draw(points) {
                                if (! this.map || ! this.layer) { return; }
                                this.layer.clearLayers();
                                (points || []).forEach((p) => {
                                    if (! p.lat || ! p.lng) { return; }
                                    const label = [p.city, p.country].filter(Boolean).join(', ')
                                        + (p.cart > 0 ? ' — mandje € ' + p.cart.toFixed(2) : '');
                                    L.marker([p.lat, p.lng]).addTo(this.layer).bindPopup(label || 'Bezoeker');
                                });
                            },
                        }"
                        x-effect="draw($wire.points)"
                        style="height: 440px; border-radius: 10px; overflow: hidden;"></div>
                @else
                    <p style="opacity:.6; font-size:.875rem; padding:2rem 0; text-align:center;">Nog geen bezoekers met bekende locatie.</p>
                @endif
            </x-filament::section>

            {{-- Landenlijst --}}
            <x-filament::section>
                <x-slot name="heading">Top landen</x-slot>

                @forelse($countries as $country)
                    <div style="display:flex; justify-content:space-between; gap:.5rem; padding:.35rem 0; border-bottom:1px solid rgba(127,127,127,.15); font-size:.875rem;">
                        <span>{{ $country['name'] }}</span>
                        <strong>{{ $country['count'] }}</strong>
                    </div>
                @empty
                    <p style="opacity:.6; font-size:.875rem;">Geen live bezoekers.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>
</x-filament::page>
