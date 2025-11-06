<?php
use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public $nameFilter = 'a';
}; ?>

<div>
    <!-- Tabla 1 -->
    <livewire:components.datatable
        tableId="users-all"
        modelClass="App\Models\User"
        scopeMethod="search"
        title="Usuarios Registrados"
        :columns="[
            'id' => 'ID',
            'name' => 'Nombre',
            'email' => 'Correo',
            'created_at' => 'Fecha de creacion'
        ]"
        :filters="[$nameFilter]"
        :perPage="5"/>
</div>