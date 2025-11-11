<?php

use Livewire\Volt\Component;

new class extends Component {
    public $item;
    public array $columns = [];

    public function mount($item = null, array $columns = [])
    {
        $this->item = $item;
        $this->columns = $columns;
    }
}; ?>

<tr>
    <td class="px-4 py-2">
        <input type="checkbox" value="{{ $item->id }}" wire:model.live="selected" class="form-checkbox h-4 w-4 text-blue-600 dark:text-blue-500 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
    </td>
    @foreach ($columns as $field => $label)
        <td>{!! data_get($item, $field) !!}</td>
    @endforeach
</tr>