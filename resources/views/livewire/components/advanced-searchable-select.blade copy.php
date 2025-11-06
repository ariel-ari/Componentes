<?php

use Livewire\Volt\Component;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Modelable;

new class extends Component {
    
    #[Modelable]
    public $value;

    /**
     * Propiedades básicas del input
     */
    public string $name = '';
    public string $label = '';
    public string $placeholder = 'Search an option...';
    public string $hint = '';
    public ?string $error = null;
    public bool $required = false;
    public bool $disabled = false;
    public bool $multiple = false;
    public bool $clearable = true;

    /**
     * Configuración de la búsqueda
     * @var class-string<Model> $source El modelo de Eloquent a consultar.
     */
    public string $source;
    public string $optionValue = 'id';
    public string $optionLabel = 'name';
    public array $searchColumns = ['name']; // Columnas para buscar
    public int $searchLimit = 50;

    /**
     * Dependencia de otros campos (avanzado)
     * Ej: ['country_id'] para que las opciones dependan del valor de un campo 'country_id'.
     */
    public ?array $dependsOn = null;

    /**
     * Estado interno del componente
     */
    public string $search = '';
    public array $options = [];
    public array $selectedLabels = [];

    /**
     * Se ejecuta cuando el componente se monta o sus dependencias cambian.
     */
    public function mount(): void
    {
        $this->initializeSelectedLabels();
    }

    /**
     * Escucha cambios en las propiedades de las que depende.
     */
    public function updated(): void
    {
        // Si una dependencia cambia, limpiamos la búsqueda y la selección.
        if ($this->dependsOn) {
            foreach ($this->dependsOn as $dependency) {
                if ($this->propertyUpdated($dependency)) {
                    $this->clearAll();
                    return;
                }
            }
        }
    }

    /**
     * Se ejecuta cuando la propiedad $search cambia.
     */
    public function updatedSearch(): void
    {
        if (empty(trim($this->search))) {
            $this->options = [];
            return;
        }
        $this->performSearch();
    }

    /**
     * Realiza la consulta a la base de datos de forma segura.
     */
    private function performSearch(): void
    {
        if (!class_exists($this->source)) {
            $this->options = [];
            return;
        }

        $query = new $this->source;

        // Aplicar filtros de dependencia
        if ($this->dependsOn) {
            $parent = $this->getParent();
            foreach ($this->dependsOn as $dependency) {
                $dependencyValue = $parent->getPropertyValue($dependency);
                if ($dependencyValue) {
                    $query = $query->where($dependency, $dependencyValue);
                }
            }
        }

        // Aplicar búsqueda en las columnas especificadas
        $query = $query->where(function ($q) {
            foreach ($this->searchColumns as $column) {
                $q->orWhere($column, 'like', '%' . $this->search . '%');
            }
        });

        $this->options = $query->limit($this->searchLimit)
            ->pluck($this->optionLabel, $this->optionValue)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->toArray();
    }

    /**
     * Acciones de selección
     */
    public function selectOption(string $value, string $label): void
    {
        if ($this->multiple) {
            $currentValue = $this->value ?? [];
            if (!in_array($value, $currentValue)) {
                $this->value = array_merge($currentValue, [$value]);
                $this->selectedLabels[$value] = $label;
            }
        } else {
            $this->value = $value;
            $this->selectedLabels = [$value => $label];
        }
        $this->resetSearch();
    }

    public function removeOption(string $value): void
    {
        if ($this->multiple) {
            $this->value = array_values(array_filter($this->value ?? [], fn ($v) => $v != $value));
            unset($this->selectedLabels[$value]);
        } else {
            $this->clearAll();
        }
    }

    public function clearAll(): void
    {
        $this->value = $this->multiple ? [] : null;
        $this->selectedLabels = [];
        $this->resetSearch();
    }

    private function resetSearch(): void
    {
        $this->search = '';
        $this->options = [];
    }

    /**
     * Inicializa las etiquetas si el componente tiene un valor inicial.
     */
    private function initializeSelectedLabels(): void
    {
        if (empty($this->value) || !class_exists($this->source)) {
            return;
        }

        $model = new $this->source;
        $values = $this->multiple ? $this->value : [$this->value];

        $results = $model::whereIn($this->optionValue, $values)
            ->pluck($this->optionLabel, $this->optionValue)
            ->toArray();

        foreach ($results as $value => $label) {
            $this->selectedLabels[$value] = $label;
        }
    }
};
?>

<div
    x-data="{
        isOpen: false,
        highlightedIndex: 0,
        init() {
            this.$watch('$wire.selectedLabels', value => this.selectedLabels = value);
            this.$el.addEventListener('click', e => e.stopPropagation());
            document.addEventListener('click', () => this.isOpen = false);
        },
        toggle() {
            if (this.$wire.disabled) return;
            this.isOpen = !this.isOpen;
            if (this.isOpen) this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        select(value, label) {
            $wire.selectOption(value, label);
            this.highlightedIndex = 0;
        },
        remove(value) {
            $wire.removeOption(value);
        },
        highlight(index) {
            const options = this.$refs.options;
            if (options) this.highlightedIndex = (index + options.length) % options.length;
        },
        selectHighlighted() {
            const option = this.$refs.options?.[this.highlightedIndex];
            if (option) option.click();
        }
    }"
    class="relative w-full"
    wire:ignore.self
>
    <!-- Input oculto para el formulario -->
    @if($multiple)
        <input type="hidden" :name="`${name}[]`" x-model="JSON.stringify($wire.value)">
    @else
        <input type="hidden" :name="name" wire:model="value">
    @endif

    <!-- Label -->
    @if($label)
        <label class="block mb-2 text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <!-- Contenedor Principal -->
    <div
        @click="toggle()"
        class="w-full min-h-[42px] px-3 py-2 text-left bg-white border border-gray-300 rounded-lg shadow-sm cursor-text focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 flex items-center gap-2 flex-wrap"
        :class="{ 'border-red-500 ring-red-200': '{{ $error }}', 'bg-gray-100 cursor-not-allowed': '{{ $disabled }}' }"
    >
        <!-- Etiquetas Seleccionadas (para modo múltiple) -->
        <template x-if="!!Object.keys($wire.selectedLabels || {}).length">
            <div class="flex flex-wrap gap-1">
                <template x-for="[value, label] in Object.entries($wire.selectedLabels || {})">
                    <span x-text="label" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                        <button type="button" @click.stop="remove(value)" class="ml-1 text-blue-600 hover:text-blue-800">&times;</button>
                    </span>
                </template>
            </div>
        </template>

        <!-- Placeholder o Etiqueta Simple -->
        <span x-show="!Object.keys($wire.selectedLabels || {}).length" x-text="Object.values($wire.selectedLabels || {})[0] || '{{ $placeholder }}'" class="flex-1 truncate"></span>

        <!-- Botón de Limpiar -->
        @if($clearable && !$disabled)
            <button type="button" x-show="!!Object.keys($wire.selectedLabels || {}).length" @click.stop="$wire.clearAll()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        @endif

        <!-- Flecha -->
        <div class="pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        </div>
    </div>

    <!-- Dropdown de Opciones -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto"
    >
        <div class="p-2 border-b border-gray-200">
            <input
                x-ref="searchInput"
                type="text"
                wire:model.live.debounce.300ms="search"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                placeholder="{{ $placeholder }}"
                @keydown.down.prevent="highlight(highlightedIndex + 1)"
                @keydown.up.prevent="highlight(highlightedIndex - 1)"
                @keydown.enter.prevent="selectHighlighted()"
                @keydown.escape.prevent="isOpen = false"
            >
        </div>
        <ul x-ref="optionsList" class="py-1" role="listbox">
            @if(empty($search))
                <li class="px-4 py-2 text-sm text-gray-500">Start typing to search...</li>
            @elseif(count($options) === 0)
                <li class="px-4 py-2 text-sm text-gray-500">No results found.</li>
            @else
                @foreach($options as $index => $option)
                    <li
                        x-ref="options"
                        @click="select('{{ $option['value'] }}', '{{ $option['label'] }}')"
                        @mouseenter="highlight({{ $index }})"
                        role="option"
                        class="px-4 py-2 cursor-pointer hover:bg-gray-100"
                        :class="{ 'bg-blue-50': highlightedIndex === {{ $index }} }"
                    >
                        {{ $option['label'] }}
                    </li>
                @endforeach
            @endif
        </ul>
    </div>

    <!-- Hint y Error -->
    @if($hint && !$error)
        <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
    @endif
    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
    
</div>
