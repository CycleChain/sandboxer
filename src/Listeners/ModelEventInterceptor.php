<?php

namespace Cyclechain\Sandboxer\Listeners;

use Illuminate\Support\Str;
use Cyclechain\Sandboxer\SandboxManager;
use Cyclechain\Sandboxer\Storage\StorageManager;
use Illuminate\Support\Facades\Event;

class ModelEventInterceptor
{
    protected array $listening = [
        'eloquent.retrieved*',
        'eloquent.creating*',
        'eloquent.updating*',
        'eloquent.deleting*',
    ];
    
    protected StorageManager $storage;
    protected array $processed = [];
    
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }
    
    public function subscribe($events)
    {
        foreach ($this->listening as $event) {
            $events->listen($event, [$this, 'handle']);
        }
    }
    
    public function handle($event, $models)
    {
        if (!SandboxManager::isActive()) {
            return;
        }
        
        $sandboxId = SandboxManager::currentId();
        
        if (!$sandboxId) {
            return;
        }
        
        [$model] = is_array($models) ? $models : [$models];
        
        if (!$model || !method_exists($model, 'getTable')) {
            return;
        }
        
        // Exclude certain tables from sandbox (like users for authentication)
        $excludedTables = config('sandboxer.excluded_tables', ['users']);
        if (in_array($model->getTable(), $excludedTables)) {
            return;
        }
        
        // Check if already processed to avoid infinite loops
        $key = get_class($model) . ':' . spl_object_id($model) . ':' . $event;
        if (isset($this->processed[$key])) {
            return;
        }
        $this->processed[$key] = true;
        
        // Clean up old entries to prevent memory leaks
        if (count($this->processed) > 1000) {
            $this->processed = [];
        }
        
        switch (true) {
            case str_contains($event, 'eloquent.retrieved'):
                $this->handleRetrieved($model, $sandboxId);
                break;
            case str_contains($event, 'eloquent.creating'):
                $this->handleCreating($model, $sandboxId);
                return false; // Prevent actual save
            case str_contains($event, 'eloquent.updating'):
                $this->handleUpdating($model, $sandboxId);
                return false; // Prevent actual update
            case str_contains($event, 'eloquent.deleting'):
                $this->handleDeleting($model, $sandboxId);
                return false; // Prevent actual delete
        }
    }
    
    protected function handleRetrieved($model, string $sandboxId): void
    {
        $sandboxData = $this->storage->findRecord(
            $sandboxId,
            $model->getTable(),
            $model->getKey()
        );
        
        if ($sandboxData) {
            if ($sandboxData->operation === 'DELETE') {
                // Bu kayıt silinmiş gibi davran
                return;
            }
            
            if ($sandboxData->operation === 'UPDATE' && $sandboxData->changed_fields) {
                // Değişiklikleri uygula
                $changes = is_string($sandboxData->changed_fields) 
                    ? json_decode($sandboxData->changed_fields, true) 
                    : $sandboxData->changed_fields;
                
                $model->forceFill($changes);
            }
        }
    }
    
    protected function handleCreating($model, string $sandboxId): void
    {
        // Model'in gerçek save edilmesini engelle - sadece sandbox'a kaydet
        $fakeId = $model->getKey() ?? $this->generateId();
        
        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => $fakeId,
            'operation' => 'INSERT',
            'data' => $model->getAttributes(),
            'sequence' => $this->getNextSequence($sandboxId)
        ]);
        
        // Fake bir ID set et ki model'in işlem hatası vermesin
        if (!$model->getKey()) {
            $model->setAttribute($model->getKeyName(), $fakeId);
        }
        
        // Sync attributes to change the internal state
        $model->syncOriginal();
        $model->exists = true;
        $model->wasRecentlyCreated = true;
    }
    
    protected function handleUpdating($model, string $sandboxId): void
    {
        $original = $model->getOriginal();
        $dirty = $model->getDirty();
        
        if (empty($dirty)) {
            return;
        }

        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'operation' => 'UPDATE',
            'data' => array_merge($original, $dirty),
            'changed_fields' => $dirty,
            'sequence' => $this->getNextSequence($sandboxId)
        ]);
        
        // Sync to make the model think it was updated
        $model->syncOriginal();
    }
    
    protected function handleDeleting($model, string $sandboxId): void
    {
        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'operation' => 'DELETE',
            'data' => $model->getAttributes(),
            'sequence' => $this->getNextSequence($sandboxId)
        ]);
    }
    
    protected function generateId(): string
    {
        return 'sandbox_' . Str::uuid()->toString();
    }
    
    protected function getNextSequence(string $sandboxId): int
    {
        $last = \DB::table('sandbox_storage')
            ->where('sandbox_id', $sandboxId)
            ->max('sequence');
        
        return ($last ?? 0) + 1;
    }
}
