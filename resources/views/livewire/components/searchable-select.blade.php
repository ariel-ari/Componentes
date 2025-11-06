<?php

use Livewire\Volt\Component;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Modelable;

new class extends Component {
    
    #[Modelable]
    public $value;

    // Propiedades básicas
    public string $name = '';
    public string $label = '';
    public string $placeholder = 'Search...';
    public string $hint = '';
    public bool $required = false;
    public bool $disabled = false;
    public bool $multiple = false;
    public bool $clearable = true;

    // Configuración de búsqueda
    public string $source = '';
    public string $optionValue = 'id';
    public string $optionLabel = 'name';
    public array $searchColumns = ['name'];
    public int $searchLimit = 50;
    public int $minSearchLength = 1;

    // Dependencias
    public ?array $dependsOn = null;

    // Estado interno
    public string $search = '';
    public array $options = [];
    public array $selectedLabels = [];
    public bool $isSearching = false;
    public string $errorMessage = '';

    public function mount(): void
    {
        $this->validateSource();
        $this->initializeSelectedLabels();
    }

    private function validateSource(): void
    {
        if (empty($this->source) || !class_exists($this->source)) {
            throw new \InvalidArgumentException("Invalid source model: {$this->source}");
        }

        if (!is_subclass_of($this->source, Model::class)) {
            throw new \InvalidArgumentException("Source must be an Eloquent Model");
        }
    }

    public function updatedValue(): void
    {
        $this->validateCurrentValue();
    }

    public function updatedSearch(): void
    {
        $searchTerm = trim($this->search);
        
        if (empty($searchTerm)) {
            $this->options = [];
            return;
        }

        if (strlen($searchTerm) < $this->minSearchLength) {
            return;
        }

        $this->performSearch();
    }

    private function performSearch(): void
    {
        try {
            $this->isSearching = true;
            
            $query = $this->source::query();

            // Aplicar dependencias si existen
            if ($this->dependsOn) {
                $parent = $this->getParentComponent();
                if ($parent) {
                    foreach ($this->dependsOn as $dependency) {
                        $value = data_get($parent, $dependency);
                        if (!empty($value)) {
                            $query->where($dependency, $value);
                        }
                    }
                }
            }

            // Aplicar búsqueda
            $searchTerm = str_replace(['%', '_'], ['\%', '\_'], $this->search);
            $query->where(function ($q) use ($searchTerm) {
                foreach ($this->searchColumns as $index => $column) {
                    if ($index === 0) {
                        $q->where($column, 'like', "%{$searchTerm}%");
                    } else {
                        $q->orWhere($column, 'like', "%{$searchTerm}%");
                    }
                }
            });

            // Excluir ya seleccionados en modo múltiple
            if ($this->multiple && is_array($this->value) && !empty($this->value)) {
                $query->whereNotIn($this->optionValue, $this->value);
            }

            $results = $query
                ->limit($this->searchLimit)
                ->get([$this->optionValue, $this->optionLabel]);

            $this->options = $results->map(function ($item) {
                return [
                    'value' => (string) $item->{$this->optionValue},
                    'label' => (string) $item->{$this->optionLabel}
                ];
            })->toArray();

        } catch (\Exception $e) {
            $this->options = [];
            logger()->error('SearchableSelect search error: ' . $e->getMessage());
        } finally {
            $this->isSearching = false;
        }
    }

    public function selectOption(string $value, string $label): void
    {
        if ($this->disabled) {
            return;
        }

        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        if ($this->multiple) {
            $current = is_array($this->value) ? $this->value : [];
            if (!in_array($value, $current, true)) {
                $this->value = array_merge($current, [$value]);
                $this->selectedLabels[$value] = $label;
            }
        } else {
            $this->value = $value;
            $this->selectedLabels = [$value => $label];
        }

        $this->resetSearch();
        $this->validateCurrentValue();
        $this->dispatch('optionSelected', value: $value);
    }

    public function removeOption(string $value): void
    {
        if ($this->disabled) {
            return;
        }

        if ($this->multiple) {
            $this->value = array_values(array_filter(
                is_array($this->value) ? $this->value : [],
                fn($v) => $v !== $value
            ));
            unset($this->selectedLabels[$value]);
        } else {
            $this->clearAll();
        }

        $this->validateCurrentValue();
    }

    public function clearAll(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->value = $this->multiple ? [] : null;
        $this->selectedLabels = [];
        $this->errorMessage = '';
        $this->resetSearch();
    }

    private function resetSearch(): void
    {
        $this->search = '';
        $this->options = [];
    }

    private function validateCurrentValue(): void
    {
        $this->errorMessage = '';

        if (!$this->required) {
            return;
        }

        if ($this->multiple) {
            if (!is_array($this->value) || empty($this->value)) {
                $this->errorMessage = $this->label 
                    ? "The {$this->label} field is required." 
                    : 'This field is required.';
            }
        } else {
            if (empty($this->value)) {
                $this->errorMessage = $this->label 
                    ? "The {$this->label} field is required." 
                    : 'This field is required.';
            }
        }
    }

    public function validateSelection(): bool
    {
        $this->validateCurrentValue();
        return empty($this->errorMessage);
    }

    private function initializeSelectedLabels(): void
    {
        if (empty($this->value)) {
            return;
        }

        try {
            $values = $this->multiple 
                ? (is_array($this->value) ? $this->value : []) 
                : [$this->value];

            if (empty($values)) {
                return;
            }

            $results = $this->source::whereIn($this->optionValue, $values)
                ->get([$this->optionValue, $this->optionLabel]);

            foreach ($results as $item) {
                $value = (string) $item->{$this->optionValue};
                $label = (string) $item->{$this->optionLabel};
                $this->selectedLabels[$value] = $label;
            }

        } catch (\Exception $e) {
            logger()->error('Error initializing labels: ' . $e->getMessage());
            $this->selectedLabels = [];
        }
    }

    private function getParentComponent()
    {
        try {
            return $this->getParent();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getHasSelectionProperty(): bool
    {
        return !empty($this->selectedLabels);
    }
};
?>

<div
    x-data="{
        isOpen: false,
        highlightedIndex: 0,
        
        toggle() {
            if (@js($disabled)) return;
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },
        
        close() {
            this.isOpen = false;
            this.highlightedIndex = 0;
        },
        
        select(value, label) {
            $wire.selectOption(value, label);
            this.highlightedIndex = 0;
            if (!@js($multiple)) {
                this.close();
            }
        },
        
        remove(value, event) {
            if (event) event.stopPropagation();
            $wire.removeOption(value);
        },
        
        clearAll(event) {
            if (event) event.stopPropagation();
            $wire.clearAll();
        },
        
        highlight(index) {
            const count = this.$refs.optionsList?.children?.length || 0;
            if (count > 0) {
                this.highlightedIndex = ((index % count) + count) % count;
                this.scrollToHighlighted();
            }
        },
        
        scrollToHighlighted() {
            const option = this.$refs.optionsList?.children[this.highlightedIndex];
            if (option) {
                option.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        },
        
        selectHighlighted() {
            const option = this.$refs.optionsList?.children[this.highlightedIndex];
            if (option && !option.hasAttribute('data-disabled')) {
                option.click();
            }
        }
    }"
    @click.away="close()"
    class="relative w-full"
    x-cloak
>
    <!-- Inputs ocultos para formularios -->
    @if($multiple)
        @foreach((array)($value ?? []) as $val)
            <input type="hidden" name="{{ $name }}[]" value="{{ $val }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $name }}" value="{{ $value ?? '' }}">
    @endif

    <!-- Label -->
    @if($label)
        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <!-- Campo Principal -->
    <div
        @click="toggle()"
        class="relative w-full min-h-[42px] px-3 py-2 bg-white dark:bg-gray-800 border rounded-lg shadow-sm cursor-pointer focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all"
        :class="{
            'border-red-500 ring-2 ring-red-200': @js(!empty($errorMessage)),
            'border-gray-300 dark:border-gray-600 hover:border-gray-400': @js(empty($errorMessage)),
            'bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-60': @js($disabled)
        }"
        tabindex="{{ $disabled ? '-1' : '0' }}"
        @keydown.enter.prevent="toggle()"
        @keydown.space.prevent="toggle()"
    >
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Tags (modo múltiple) -->
            @if($multiple)
                @foreach($selectedLabels as $val => $lab)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        <span class="max-w-[150px] truncate">{{ $lab }}</span>
                        @if(!$disabled)
                            <button 
                                type="button" 
                                @click="remove('{{ $val }}', $event)"
                                class="ml-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-300"
                            >
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        @endif
                    </span>
                @endforeach
            @endif

            <!-- Texto placeholder o valor único -->
            @if(!$multiple || empty($selectedLabels))
                <span class="flex-1 truncate text-gray-700 dark:text-gray-300 {{ empty($selectedLabels) ? 'text-gray-400' : '' }}">
                    @if(!$multiple && !empty($selectedLabels))
                        {{ array_values($selectedLabels)[0] ?? $placeholder }}
                    @else
                        {{ $placeholder }}
                    @endif
                </span>
            @endif

            <!-- Botones -->
            <div class="flex items-center gap-1 ml-auto">
                <!-- Loading -->
                <div wire:loading wire:target="search" class="text-gray-400">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- Clear -->
                @if($clearable && !$disabled)
                    @if(!empty($selectedLabels))
                        <button 
                            type="button" 
                            @click="clearAll($event)"
                            class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    @endif
                @endif

                <!-- Arrow -->
                <svg 
                    class="w-5 h-5 text-gray-400 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                    fill="currentColor" 
                    viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Dropdown -->
    <div
        x-show="isOpen"
        x-transition
        class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden"
        style="display: none;"
    >
        <!-- Search Input -->
        <div class="p-2 border-b border-gray-200 dark:border-gray-700">
            <input
                x-ref="searchInput"
                type="text"
                wire:model.live.debounce.300ms="search"
                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="{{ $placeholder }}"
                @keydown.down.prevent="highlight(highlightedIndex + 1)"
                @keydown.up.prevent="highlight(highlightedIndex - 1)"
                @keydown.enter.prevent="selectHighlighted()"
                @keydown.escape.prevent="close()"
                autocomplete="off"
            >
        </div>

        <!-- Options List -->
        <ul 
            x-ref="optionsList"
            class="max-h-60 overflow-auto py-1"
        >
            @if(empty($search))
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-disabled>
                    Start typing to search (min {{ $minSearchLength }} chars)...
                </li>
            @elseif($isSearching)
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-disabled>
                    <span class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching...
                    </span>
                </li>
            @elseif(empty($options))
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-disabled>
                    No results found
                </li>
            @else
                @foreach($options as $index => $option)
                    <li
                        @click="select('{{ $option['value'] }}', '{{ $option['label'] }}')"
                        @mouseenter="highlightedIndex = {{ $index }}"
                        class="px-4 py-2.5 cursor-pointer text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        :class="{ 'bg-blue-50 dark:bg-blue-900/30 font-medium': highlightedIndex === {{ $index }} }"
                    >
                        {{ $option['label'] }}
                    </li>
                @endforeach
            @endif
        </ul>
    </div>

    <!-- Hint -->
    @if($hint && empty($errorMessage))
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    <!-- Error -->
    @if($errorMessage)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400 flex items-start gap-1">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>