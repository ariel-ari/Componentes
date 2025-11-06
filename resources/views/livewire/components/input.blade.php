<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Modelable;

new class extends Component {
    #[Modelable]
    public $value = '';

    // Propiedades básicas
    public string $name = '';
    public string $type = 'text';
    public string $label = '';
    public string $placeholder = '';
    public string $hint = '';
    public ?string $error = null;
    public bool $required = false;
    public bool $disabled = false;
    public bool $readonly = false;
    public ?string $icon = null;
    public ?string $iconPosition = 'left';
    public ?int $maxlength = null;
    public ?string $autocomplete = null;
    public string $size = 'md'; // sm, md, lg

    // Formateo con Alpine
    public ?string $format = null; // currency, number, percentage, phone, card, ruc, dni
    public ?string $currency = 'PEN'; // PEN, USD, EUR
    public ?int $decimals = 2;
    public ?string $locale = 'es-PE'; // es-PE, en-US, es-ES
    public ?string $prefix = null;
    public ?string $suffix = null;
    public bool $allowNegative = true;
    public ?int $min = null;
    public ?int $max = null;

    // Toggle de contraseña
    public bool $showPasswordToggle = false;

    // Validación
    public array $rules = [];

    /**
     * Genera clases CSS dinámicas para el input
     */
    public function getInputClasses(): string
    {
        $baseClasses = 'block w-full rounded-lg border transition-colors duration-200 focus:outline-none focus:ring-2';

        // Tamaños
        $sizeClasses = match($this->size) {
            'sm' => 'px-3 py-1.5 text-sm',
            'lg' => 'px-4 py-3 text-lg',
            default => 'px-4 py-2 text-base',
        };

        // Estados (error, disabled, normal)
        if ($this->error) {
            $stateClasses = 'border-red-500 focus:border-red-500 focus:ring-red-200';
        } elseif ($this->disabled) {
            $stateClasses = 'bg-gray-100 border-gray-300 cursor-not-allowed';
        } else {
            $stateClasses = 'border-gray-300 focus:border-blue-500 focus:ring-blue-200';
        }

        // Padding para iconos/prefix/suffix
        $iconClasses = '';
        if ($this->icon && $this->iconPosition === 'left') {
            $iconClasses = 'pl-10';
        } elseif ($this->icon && $this->iconPosition === 'right' && !($this->type === 'password' && $this->showPasswordToggle)) {
            $iconClasses = 'pr-10';
        }

        if ($this->prefix) {
            $iconClasses .= ' pl-8';
        }
        if ($this->suffix) {
            $iconClasses .= ' pr-12';
        }

        // Espacio para botón de toggle de contraseña
        if ($this->type === 'password' && $this->showPasswordToggle) {
            $iconClasses .= ' pr-10';
        }

        return "{$baseClasses} {$sizeClasses} {$stateClasses} {$iconClasses}";
    }

    /**
     * Validación en tiempo real
     */
    public function updated($property): void
    {
        if ($property === 'value' && !empty($this->rules)) {
            $this->validateOnly('value', $this->rules);
        }
    }

    /**
     * Obtiene el valor sin formato (solo números)
     * Útil para guardar en base de datos
     */
    public function getRawValue(): mixed
    {
        if (!$this->format) {
            return $this->value;
        }

        $raw = preg_replace('/[^0-9.-]/', '', $this->value);
        return is_numeric($raw) ? (float) $raw : $this->value;
    }

};
?>
<div>
    <div class="w-full" @if($format || ($type==='password' && $showPasswordToggle)) x-data="inputComponent({
            wireId: '{{ $this->getId() }}',
            wireName: 'value',
            format: '{{ $format }}',
            currency: '{{ $currency }}',
            decimals: {{ $decimals }},
            locale: '{{ $locale }}',
            allowNegative: {{ $allowNegative ? 'true' : 'false' }},
            min: {{ $min ?? 'null' }},
            max: {{ $max ?? 'null' }},
            showPassword: false
        })" @if($format) x-init="initFormatter()" @endif @endif>
        {{-- Label --}}
        @if($label)
        <label for="{{ $name }}" class="block mb-2 text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
            <span class="text-red-500">*</span>
            @endif
        </label>
        @endif

        {{-- Input Container --}}
        <div class="relative">
            {{-- Icon Left --}}
            @if($icon && $iconPosition === 'left')
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <x-dynamic-component :component="$icon" class="w-5 h-5 text-gray-400" />
            </div>
            @endif

            {{-- Prefix --}}
            @if($prefix)
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <span class="text-gray-500 text-sm">{{ $prefix }}</span>
            </div>
            @endif

            {{-- Input Field --}}
            <input @if($type==='password' && $showPasswordToggle) x-bind:type="showPassword ? 'text' : 'password'" @else
                type="{{ $type }}" @endif id="{{ $name }}" name="{{ $name }}" @if($format) x-model="displayValue"
                @input="handleInput($event)" @blur="handleBlur($event)" @focus="handleFocus($event)" @else
                wire:model.live.debounce.300ms="value" @endif placeholder="{{ $placeholder }}"
                class="{{ $this->getInputClasses() }}" @if($required) required @endif @if($disabled) disabled @endif
                @if($readonly) readonly @endif @if($maxlength) maxlength="{{ $maxlength }}" @endif @if($autocomplete)
                autocomplete="{{ $autocomplete }}" @endif />

            {{-- Password Toggle Button --}}
            @if($type === 'password' && $showPasswordToggle)
            <button type="button" @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none"
                tabindex="-1">
                {{-- Ojo abierto (mostrar contraseña) --}}
                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{-- Ojo cerrado (ocultar contraseña) --}}
                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
            </button>
            @endif

            {{-- Icon Right --}}
            @if($icon && $iconPosition === 'right' && !($type === 'password' && $showPasswordToggle))
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <x-dynamic-component :component="$icon" class="w-5 h-5 text-gray-400" />
            </div>
            @endif

            {{-- Suffix --}}
            @if($suffix)
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="text-gray-500 text-sm">{{ $suffix }}</span>
            </div>
            @endif
        </div>

        {{-- Hint Text --}}
        @if($hint && !$error)
        <p class="mt-1 text-sm text-gray-500">
            {{ $hint }}
        </p>
        @endif

        {{-- Error Message --}}
        @if($error)
        <p class="mt-1 text-sm text-red-600">
            {{ $error }}
        </p>
        @endif

        {{-- Character Counter --}}
        @if($maxlength)
        <div class="mt-1 text-xs text-right text-gray-500">
            <span>{{ strlen($value) }}</span> / {{ $maxlength }}
        </div>
        @endif
    </div>

    {{-- Alpine.js Component Script --}}
    @once
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
    Alpine.data('inputComponent', (config) => ({
        // Estado
        displayValue: '',
        rawValue: '',

        // Configuración
        wireId: config.wireId,
        wireName: config.wireName || 'value',
        format: config.format || null,
        currency: config.currency || 'PEN',
        decimals: config.decimals || 2,
        locale: config.locale || 'es-PE',
        allowNegative: config.allowNegative ?? true,
        min: config.min ?? null,
        max: config.max ?? null,
        showPassword: config.showPassword ?? false,

        /**
         * Inicializa el formateador y sincronización con Livewire
         */
        initFormatter() {
            if (!this.format) return;

            const wire = Livewire.find(this.wireId);
            if (!wire) {
                console.error('Livewire component not found:', this.wireId);
                return;
            }

            // Valor inicial
            this.rawValue = wire.get(this.wireName) || '';
            this.displayValue = this.rawValue ? this.formatValue(this.rawValue) : '';

            // Escuchar cambios desde Livewire
            wire.$watch(this.wireName, (value) => {
                if (value !== this.rawValue) {
                    this.rawValue = value || '';
                    this.displayValue = this.formatValue(this.rawValue);
                }
            });
        },

        /**
         * Maneja input del usuario (mientras escribe)
         */
        handleInput(event) {
            const input = event.target.value;
            this.rawValue = this.unformatValue(input);

            // Aplicar límites min/max
            if (this.min !== null && parseFloat(this.rawValue) < this.min) {
                this.rawValue = this.min.toString();
            }
            if (this.max !== null && parseFloat(this.rawValue) > this.max) {
                this.rawValue = this.max.toString();
            }

            // Actualizar Livewire
            const wire = Livewire.find(this.wireId);
            if (wire) {
                wire.set(this.wireName, this.rawValue);
            }
        },

        /**
         * Formatea el valor cuando pierde el foco
         */
        handleBlur(event) {
            this.displayValue = this.formatValue(this.rawValue);
        },

        /**
         * Muestra valor sin formato al enfocar (para edición fácil)
         */
        handleFocus(event) {
            if (this.format === 'currency' || this.format === 'percentage') {
                this.displayValue = this.rawValue;
                this.$nextTick(() => event.target.select());
            }
        },

        /**
         * Formatea el valor según el tipo
         */
        formatValue(value) {
            if (!value || !this.format) return '';

            const num = parseFloat(value);
            if (isNaN(num)) return value;

            switch(this.format) {
                case 'currency':
                    return new Intl.NumberFormat(this.locale, {
                        style: 'currency',
                        currency: this.currency,
                        minimumFractionDigits: this.decimals,
                        maximumFractionDigits: this.decimals,
                    }).format(num);

                case 'number':
                    return new Intl.NumberFormat(this.locale, {
                        minimumFractionDigits: this.decimals,
                        maximumFractionDigits: this.decimals,
                    }).format(num);

                case 'percentage':
                    return num.toFixed(this.decimals) + '%';

                case 'phone':
                    const cleaned = value.replace(/\D/g, '');
                    if (cleaned.length === 9) {
                        return cleaned.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                    }
                    return value;

                case 'card':
                    const card = value.replace(/\s/g, '');
                    return card.match(/.{1,4}/g)?.join(' ') || value;

                case 'ruc':
                    return value.replace(/\D/g, '').slice(0, 11);

                case 'dni':
                    return value.replace(/\D/g, '').slice(0, 8);

                default:
                    return value;
            }
        },

        /**
         * Remueve el formato del valor (extrae solo números)
         */
        unformatValue(value) {
            if (!value) return '';

            // Remover todo excepto números, punto y signo negativo
            let cleaned = value.replace(/[^\d.-]/g, '');

            // Solo permitir un punto decimal
            const parts = cleaned.split('.');
            if (parts.length > 2) {
                cleaned = parts[0] + '.' + parts.slice(1).join('');
            }

            // Manejar signo negativo
            if (!this.allowNegative) {
                cleaned = cleaned.replace('-', '');
            }

            return cleaned;
        }
    }));
});

    </script>
    @endpush

    {{-- Estilos para x-cloak --}}
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @endonce
</div>
