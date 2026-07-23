<x-filament::page>
    @php($vapidPublicKey = $this->getVapidPublicKey())

    @if (! $vapidPublicKey)
        <x-filament::section>
            <x-slot name="heading">Nog niet geconfigureerd</x-slot>
            <p class="text-sm">
                Er zijn nog geen Web Push-sleutels ingesteld. Genereer ze met
                <code>php artisan chat:generate-web-push-keys</code> en zet ze in
                de <code>.env</code> van deze omgeving.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Bureaubladmeldingen op dit apparaat</x-slot>

            <div
                x-data="livechatWebPush(@js($vapidPublicKey), @js(\Dashed\DashedCore\Classes\Sites::getActive()))"
                x-init="init()"
                class="space-y-4"
            >
                <p class="text-sm" x-text="statusText"></p>

                <div class="flex gap-3">
                    <x-filament::button type="button" x-show="!subscribed" x-on:click="enable()">
                        Meldingen inschakelen
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" x-show="subscribed" x-on:click="disable()">
                        Meldingen uitschakelen
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Waarvoor wil je een melding?</x-slot>

            <div class="space-y-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="notifyHandoff" />
                    <span>Chat wacht op een medewerker (handoff)</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="notifyMessage" />
                    <span>Nieuw bezoekersbericht in een lopend gesprek</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="notifyNew" />
                    <span>Elk nieuw gesprek dat wordt gestart</span>
                </label>

                <x-filament::button type="button" wire:click="savePreferences">
                    Voorkeuren opslaan
                </x-filament::button>
            </div>
        </x-filament::section>

        <script>
            function livechatWebPush(vapidPublicKey, siteId) {
                return {
                    subscribed: false,
                    statusText: 'Meldingen staan uit op dit apparaat.',
                    swUrl: @js(route('dashed-livechat.web-push.sw')),
                    subscribeUrl: @js(route('dashed-livechat.web-push.subscribe')),
                    unsubscribeUrl: @js(route('dashed-livechat.web-push.unsubscribe')),
                    csrf: @js(csrf_token()),

                    async init() {
                        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                            this.statusText = 'Deze browser ondersteunt geen bureaubladmeldingen.';
                            return;
                        }
                        const reg = await navigator.serviceWorker.getRegistration();
                        if (reg) {
                            const sub = await reg.pushManager.getSubscription();
                            this.setSubscribed(!!sub);
                        }
                    },

                    setSubscribed(value) {
                        this.subscribed = value;
                        this.statusText = value
                            ? 'Meldingen staan aan op dit apparaat.'
                            : 'Meldingen staan uit op dit apparaat.';
                    },

                    urlBase64ToUint8Array(base64String) {
                        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                        const raw = atob(base64);
                        const output = new Uint8Array(raw.length);
                        for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
                        return output;
                    },

                    async enable() {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') {
                            this.statusText = 'Je hebt geen toestemming gegeven voor meldingen.';
                            return;
                        }

                        const reg = await navigator.serviceWorker.register(this.swUrl, { scope: '/' });
                        await navigator.serviceWorker.ready;

                        const sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
                        });

                        const json = sub.toJSON();
                        await fetch(this.subscribeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                            body: JSON.stringify({
                                site_id: siteId,
                                endpoint: sub.endpoint,
                                public_key: json.keys.p256dh,
                                auth_token: json.keys.auth,
                                content_encoding: 'aesgcm',
                            }),
                        });

                        this.setSubscribed(true);
                    },

                    async disable() {
                        const reg = await navigator.serviceWorker.getRegistration();
                        if (reg) {
                            const sub = await reg.pushManager.getSubscription();
                            if (sub) {
                                await fetch(this.unsubscribeUrl, {
                                    method: 'DELETE',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                                    body: JSON.stringify({ endpoint: sub.endpoint }),
                                });
                                await sub.unsubscribe();
                            }
                        }
                        this.setSubscribed(false);
                    },
                };
            }
        </script>
    @endif
</x-filament::page>
