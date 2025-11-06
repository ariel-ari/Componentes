<?php
/**
 * Formulario de Prueba para Searchable Select
 * Archivo: resources/views/livewire/sales-form.blade.php
 */
use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    // Campos del formulario
    public $user_id = null;
    public $amount = '';
    public $description = '';
    
    // Array para simular guardado
    public $sales = [];
    
    // Mensajes
    public $successMessage = '';
    public $errorMessage = '';
    
    public function mount()
    {
        // Cargar ventas de sesión (simular base de datos)
        $this->sales = session('sales', []);
    }
    
    public function save()
    {
        // Limpiar mensajes previos
        $this->successMessage = '';
        $this->errorMessage = '';
        
        // Validar campos normales
        $validated = $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|min:3|max:255',
        ], [
            'amount.required' => 'El monto es obligatorio',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'description.required' => 'La descripción es obligatoria',
            'description.min' => 'La descripción debe tener al menos 3 caracteres',
        ]);
        
        // Validar el select buscable
        if (empty($this->user_id)) {
            $this->errorMessage = 'Debe seleccionar un usuario';
            return;
        }
        
        // Obtener el usuario seleccionado
        $user = User::find($this->user_id);
        
        if (!$user) {
            $this->errorMessage = 'Usuario no encontrado';
            return;
        }
        
        // Crear la venta (simulada)
        $sale = [
            'id' => count($this->sales) + 1,
            'user_id' => $this->user_id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'amount' => $this->amount,
            'description' => $this->description,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
        
        // Agregar al array de ventas
        $this->sales[] = $sale;
        
        // Guardar en sesión (simular base de datos)
        session(['sales' => $this->sales]);
        
        // Mensaje de éxito
        $this->successMessage = "✅ Venta registrada correctamente - ID: {$sale['id']} - Usuario: {$user->name} - Monto: \${$this->amount}";
        
        // Log en consola del servidor
        logger()->info('Nueva venta registrada', $sale);
        
        // Dispatch evento para JavaScript
        $this->dispatch('sale-registered', sale: $sale);
        
        // Limpiar formulario
        $this->reset(['user_id', 'amount', 'description']);
    }
    
    public function deleteSale($index)
    {
        if (isset($this->sales[$index])) {
            $deletedSale = $this->sales[$index];
            unset($this->sales[$index]);
            $this->sales = array_values($this->sales); // Reindexar
            session(['sales' => $this->sales]);
            
            $this->successMessage = "🗑️ Venta #{$deletedSale['id']} eliminada";
            logger()->info('Venta eliminada', $deletedSale);
        }
    }
    
    public function clearAll()
    {
        $this->sales = [];
        session()->forget('sales');
        $this->successMessage = '🧹 Todas las ventas han sido eliminadas';
    }
}; 
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                📊 Registro de Ventas (Prueba)
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Formulario para probar el Searchable Select Component
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Formulario -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                    Nueva Venta
                </h2>
                
                <!-- Mensajes de éxito -->
                @if($successMessage)
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 rounded-lg flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $successMessage }}</span>
                    </div>
                @endif
                
                <!-- Mensajes de error -->
                @if($errorMessage)
                    <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 rounded-lg flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif
                
                <form wire:submit.prevent="save" class="space-y-6">
                    
                    <!-- Searchable Select - Usuario -->
                    <div>
                        <livewire:components.searchable-select
                            name="user_id"
                            label="Usuario / Cliente"
                            placeholder="Buscar por nombre o email..."
                            :source="User::class"
                            option-value="id"
                            option-label="name"
                            :search-columns="['name', 'email']"
                            wire:model.live="user_id"
                            :required="true"
                            hint="Escribe al menos 2 caracteres para buscar"
                        />
                    </div>
                    
                    <!-- Monto -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Monto *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                            <input 
                                type="number" 
                                step="0.01"
                                wire:model="amount"
                                class="w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                placeholder="0.00"
                            >
                        </div>
                        @error('amount') 
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                        @enderror
                    </div>
                    
                    <!-- Descripción -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Descripción *
                        </label>
                        <textarea 
                            wire:model="description"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="Describe la venta..."
                        ></textarea>
                        @error('description') 
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                        @enderror
                    </div>
                    
                    <!-- Botones -->
                    <div class="flex gap-3">
                        <button 
                            type="submit"
                            class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="save">
                                💾 Guardar Venta
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Guardando...
                            </span>
                        </button>
                        
                        <button 
                            type="button"
                            wire:click="$refresh"
                            class="px-6 py-3 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium rounded-lg transition-colors"
                        >
                            🔄 Limpiar
                        </button>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Ventas Registradas -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        Ventas Registradas ({{ count($sales) }})
                    </h2>
                    @if(count($sales) > 0)
                        <button 
                            wire:click="clearAll"
                            wire:confirm="¿Estás seguro de eliminar todas las ventas?"
                            class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                        >
                            🗑️ Limpiar todo
                        </button>
                    @endif
                </div>
                
                @if(count($sales) === 0)
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            No hay ventas registradas
                        </p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            Completa el formulario para agregar una venta
                        </p>
                    </div>
                @else
                    <div class="space-y-3 max-h-[600px] overflow-y-auto">
                        @foreach($sales as $index => $sale)
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="px-2 py-1 text-xs font-semibold bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">
                                                #{{ $sale['id'] }}
                                            </span>
                                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                                ${{ number_format($sale['amount'], 2) }}
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-1 text-sm">
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                👤 {{ $sale['user_name'] }}
                                            </p>
                                            <p class="text-gray-600 dark:text-gray-400">
                                                📧 {{ $sale['user_email'] }}
                                            </p>
                                            <p class="text-gray-700 dark:text-gray-300">
                                                📝 {{ $sale['description'] }}
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-400 text-xs">
                                                🕐 {{ $sale['created_at'] }}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <button 
                                        wire:click="deleteSale({{ $index }})"
                                        wire:confirm="¿Eliminar esta venta?"
                                        class="ml-3 p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg transition-colors"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            
        </div>
        
        <!-- Console Output (Simulado) -->
        <div class="mt-6 bg-gray-900 rounded-lg shadow-lg p-6" x-data="{ logs: [] }" @sale-registered.window="logs.unshift($event.detail.sale)">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
                <h3 class="text-lg font-semibold text-white">Console Output</h3>
            </div>
            
            <div class="bg-black rounded p-4 font-mono text-sm text-green-400 max-h-64 overflow-y-auto">
                <template x-if="logs.length === 0">
                    <p class="text-gray-500">Waiting for sales registration...</p>
                </template>
                
                <template x-for="(log, index) in logs" :key="index">
                    <div class="mb-2 border-b border-gray-800 pb-2">
                        <p class="text-yellow-400">[<span x-text="new Date().toLocaleTimeString()"></span>] Sale Registered:</p>
                        <pre class="text-xs text-green-300 mt-1" x-text="JSON.stringify(log, null, 2)"></pre>
                    </div>
                </template>
            </div>
        </div>
        
    </div>
</div>

<script>
// Escuchar eventos de venta registrada
document.addEventListener('livewire:initialized', () => {
    Livewire.on('sale-registered', (event) => {
        console.log('🎉 Nueva venta registrada:', event.sale);
        console.table(event.sale);
    });
});
</script>