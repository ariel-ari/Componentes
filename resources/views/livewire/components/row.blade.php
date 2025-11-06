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
    @foreach ($columns as $field => $label)
        <td>{!! data_get($item, $field) !!}</td>
    @endforeach
</tr>