<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-currency-dollar"
                    class="w-5 h-5 text-gray-500 dark:text-gray-400"
                />
                <span>Tipos de Cambio</span>
            </div>
        </x-slot>

        @php
            $rates = $this->getExchangeRates();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- USD -> ARS --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">USD → ARS</span>
                    <x-filament::icon
                        icon="heroicon-o-banknotes"
                        class="w-5 h-5 text-warning-500"
                    />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $rates['usd_ars']['formatted'] }}
                </div>
                @if($rates['usd_ars']['fetched_at'])
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $rates['usd_ars']['fetched_at']->diffForHumans() }}
                    </p>
                @endif
            </div>

            {{-- USD -> EUR --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">USD → EUR</span>
                    <x-filament::icon
                        icon="heroicon-o-currency-euro"
                        class="w-5 h-5 text-primary-500"
                    />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $rates['usd_eur']['formatted'] }}
                </div>
                @if($rates['usd_eur']['fetched_at'])
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $rates['usd_eur']['fetched_at']->diffForHumans() }}
                    </p>
                @endif
            </div>

            {{-- EUR -> ARS (calculado) --}}
            <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 border-2 border-primary-200 dark:border-primary-800">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-primary-600 dark:text-primary-400">EUR → ARS</span>
                    <x-filament::icon
                        icon="heroicon-o-calculator"
                        class="w-5 h-5 text-primary-500"
                    />
                </div>
                <div class="text-2xl font-bold text-primary-900 dark:text-primary-100">
                    {{ $rates['eur_ars']['formatted'] }}
                </div>
                @if($rates['eur_ars']['fetched_at'])
                    <p class="mt-1 text-xs text-primary-600 dark:text-primary-400">
                        Calculado {{ $rates['eur_ars']['fetched_at']->diffForHumans() }}
                    </p>
                @else
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Ejecutar sincronización
                    </p>
                @endif
            </div>
        </div>

        {{-- Info adicional --}}
        <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
            <div class="flex items-start gap-2">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"
                />
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-medium">Actualización automática</p>
                    <p class="text-xs mt-1">Los tipos de cambio se sincronizan automáticamente todos los días a las 6:00 AM. Este widget se actualiza automáticamente cada 5 minutos.</p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

