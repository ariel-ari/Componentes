<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public string $title = 'Mi Tabla de prueba';
    public array $columns = [];
    public string $tableId = 'default';
    public string $modelClass = '';
    public array $filters = [];
    public string $scopeMethod = ''; // Nombre del scope a usar
    public int $perPage = 10;
    public string $search = '';
    
    public function with()
    {
        if (empty($this->modelClass) || !class_exists($this->modelClass)) {
            return ['items' => collect()->paginate($this->perPage)];
        }
        
        $query = $this->modelClass::query();
        
        // Si hay un scope definido, úsalo
        if (!empty($this->scopeMethod) && method_exists($this->modelClass, 'scope' . ucfirst($this->scopeMethod))) {
            $query = $query->{$this->scopeMethod}(...array_values($this->filters));
        } else {
            // Filtros simples
            foreach ($this->filters as $field => $value) {
                if (!empty($value)) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                }
            }
        }
        
        // Búsqueda interna
        if (!empty($this->search)) {
            $query->where(function($q) {
                foreach ($this->columns as $field => $label) {
                    $q->orWhere($field, 'like', '%' . $this->search . '%');
                }
            });
        }
        
        return [
            'items' => $query->paginate($this->perPage, ['*'], $this->tableId . 'Page')
        ];
    }
    
    public function updatedSearch()
    {
        $this->resetPage($this->tableId . 'Page');
    }
}; ?>

<div>
    <div class="mb-4">
        <h1 class="text-xl font-bold">{{ $title }}</h1>
        
        <input 
            type="text" 
            wire:model.live.debounce.300ms="search" 
            placeholder="Buscar en tabla..."
            class="border rounded px-3 py-2 mt-2">
    </div>
    
    <table class="w-full border">
        <thead>
            <tr class="bg-gray-100">
                @foreach ($columns as $field => $label)
                    <th class="px-6 py-3 text-center border">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <livewire:components.row 
                    wire:key="row-{{ $tableId }}-{{ $item->id }}"
                    :item="$item" 
                    :columns="$columns"/>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="text-center py-4">
                        No hay registros
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>