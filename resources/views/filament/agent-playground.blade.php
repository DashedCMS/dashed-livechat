<x-filament::page>
    <div class="flex flex-col gap-6">

        {{-- Agent-keuze + guardrail-uitleg --}}
        <x-filament::section>
            <x-slot name="heading">Agent</x-slot>
            <x-slot name="description">
                Kies de AI-agent waarmee je in deze geïsoleerde testomgeving chat.
                Berichten hier komen niet in de echte gesprekken of statistieken terecht.
            </x-slot>

            <div class="flex flex-col gap-4">
                @if (empty($agents))
                    <div class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center dark:border-white/10 dark:bg-gray-800">
                        <x-filament::icon icon="heroicon-o-cpu-chip" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Nog geen actieve AI-agent voor deze site. Maak er eerst een aan bij Chat &rarr; Agents.
                        </p>
                    </div>
                @else
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedAgentId">
                            @foreach ($agents as $agent)
                                <option value="{{ $agent['id'] }}">{{ $agent['name'] }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    @if ($this->selectedAgent)
                        <div class="flex items-center gap-2 text-sm">
                            <x-filament::badge :color="$this->selectedAgent->guardrail_mode === 'strict' ? 'warning' : 'gray'">
                                {{ $this->selectedAgent->guardrail_mode === 'strict' ? 'Streng' : 'Standaard' }}
                            </x-filament::badge>
                            <span class="text-gray-500 dark:text-gray-400">{{ $this->guardrailExplanation }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </x-filament::section>

        {{-- Gesprek --}}
        <x-filament::section>
            <x-slot name="heading">Gesprek</x-slot>
            <x-slot name="headerEnd">
                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-path" wire:click="resetSandbox">
                    Sandbox resetten
                </x-filament::button>
            </x-slot>

            <div class="flex flex-col gap-4">

                @if ($errorMessage)
                    <div class="flex items-start gap-2 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-400">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                    @forelse ($turns as $turn)
                        <div class="flex flex-col gap-2 {{ $turn['role'] === 'visitor' ? 'items-end' : 'items-start' }}">
                            <div class="max-w-[80%] rounded-xl px-4 py-2 text-sm shadow-sm
                                {{ $turn['role'] === 'visitor'
                                    ? 'bg-primary-600 text-white'
                                    : 'border border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100' }}">
                                {{ $turn['content'] ?: '—' }}
                            </div>

                            @if (! empty($turn['tool_trace']))
                                <div class="flex max-w-[80%] flex-col gap-2">
                                    @foreach ($turn['tool_trace'] as $call)
                                        <details class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs dark:border-white/10 dark:bg-gray-900">
                                            <summary class="cursor-pointer font-medium text-gray-600 transition duration-150 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                                                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="mr-1 inline h-3.5 w-3.5" />
                                                Tool: {{ $call['name'] ?? 'onbekend' }}
                                            </summary>
                                            <div class="mt-2 flex flex-col gap-2">
                                                <div>
                                                    <div class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Input</div>
                                                    <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-100 p-2 text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ json_encode($call['input'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                                @if (! empty($call['products']))
                                                    <div>
                                                        <div class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Output (producten)</div>
                                                        <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-100 p-2 text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ json_encode($call['products'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-2 py-8 text-center">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen berichten. Typ hieronder iets om de agent te testen.</p>
                        </div>
                    @endforelse
                </div>

                <form wire:submit="send" class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="message"
                                placeholder="Typ een bericht als testbezoeker…"
                                :disabled="$sending || empty($agents)"
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button type="submit" icon="heroicon-o-paper-airplane" :disabled="$sending || empty($agents)">
                        {{ $sending ? 'Bezig…' : 'Versturen' }}
                    </x-filament::button>
                </form>
            </div>
        </x-filament::section>

    </div>
</x-filament::page>
