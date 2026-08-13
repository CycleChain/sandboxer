<?php

namespace Cyclechain\Sandboxer\Listeners;

use Illuminate\Support\Str;
use Cyclechain\Sandboxer\SandboxManager;
use Cyclechain\Sandboxer\Storage\StorageManager;
use Cyclechain\Sandboxer\Scopes\SandboxScope;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;

class ModelEventInterceptor
{
    protected StorageManager $storage;
    protected array $processed = [];
    protected array $pretendingConnections = [];
    
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }
    
    public function subscribe($events)
    {
        $events->listen('eloquent.booting:*', [$this, 'handleBooting']);
        
        $events->listen('eloquent.creating*', [$this, 'handleCreating']);
        $events->listen('eloquent.created*', [$this, 'handleCreated']);
        
        $events->listen('eloquent.updating*', [$this, 'handleUpdating']);
        $events->listen('eloquent.updated*', [$this, 'handleUpdated']);
        
        $events->listen('eloquent.deleting*', [$this, 'handleDeleting']);
        $events->listen('eloquent.deleted*', [$this, 'handleDeleted']);
    }

    public function handleBooting($event, $models): void
    {
        [$model] = is_array($models) ? $models : [$models];
        if ($model && $model instanceof Model) {
            $modelClass = get_class($model);
            if (method_exists($modelClass, 'addGlobalScope')) {
                $modelClass::addGlobalScope(new SandboxScope());
            }
        }
    }

    public function handleCreating($event, $models)
    {
        if (!$this->shouldIntercept($event, $models, $model, $sandboxId)) {
            return;
        }

        $fakeId = $model->getKey() ?? $this->generateId();
        if (!$model->getKey()) {
            $model->setAttribute($model->getKeyName(), $fakeId);
        }

        $model->__sandbox_fake_id = (string) $fakeId;

        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => (string) $fakeId,
            'operation' => 'INSERT',
            'data' => $model->getAttributes(),
            'sequence' => $this->getNextSequence($sandboxId)
        ]);

        $this->enablePretendMode($model);
    }

    public function handleCreated($event, $models): void
    {
        [$model] = is_array($models) ? $models : [$models];
        if ($model && $model instanceof Model) {
            if (isset($model->__sandbox_fake_id)) {
                $model->setAttribute($model->getKeyName(), $model->__sandbox_fake_id);
                $model->syncOriginal();
                unset($model->__sandbox_fake_id);
            }
            $this->disablePretendMode($model);
        }
    }

    public function handleUpdating($event, $models)
    {
        if (!$this->shouldIntercept($event, $models, $model, $sandboxId)) {
            return;
        }

        $original = $model->getOriginal();
        $dirty = $model->getDirty();
        
        if (empty($dirty)) {
            return;
        }

        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => (string) $model->getKey(),
            'operation' => 'UPDATE',
            'data' => array_merge($original, $dirty),
            'changed_fields' => $dirty,
            'sequence' => $this->getNextSequence($sandboxId)
        ]);

        $this->enablePretendMode($model);
    }

    public function handleUpdated($event, $models): void
    {
        [$model] = is_array($models) ? $models : [$models];
        if ($model && $model instanceof Model) {
            $this->disablePretendMode($model);
        }
    }

    public function handleDeleting($event, $models)
    {
        if (!$this->shouldIntercept($event, $models, $model, $sandboxId)) {
            return;
        }

        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => $model->getTable(),
            'record_id' => (string) $model->getKey(),
            'operation' => 'DELETE',
            'data' => $model->getAttributes(),
            'sequence' => $this->getNextSequence($sandboxId)
        ]);

        $this->enablePretendMode($model);
    }

    public function handleDeleted($event, $models): void
    {
        [$model] = is_array($models) ? $models : [$models];
        if ($model && $model instanceof Model) {
            $this->disablePretendMode($model);
        }
    }

    protected function shouldIntercept($event, $models, &$model, &$sandboxId): bool
    {
        if (!SandboxManager::isActive()) {
            return false;
        }

        $sandboxId = SandboxManager::currentId();
        if (!$sandboxId) {
            return false;
        }

        [$model] = is_array($models) ? $models : [$models];
        if (!$model || !($model instanceof Model) || !method_exists($model, 'getTable')) {
            return false;
        }

        $excludedTables = array_merge(
            (array) config('sandboxer.excluded_tables', ['users']),
            ['sandbox_sessions', 'sandbox_storage']
        );

        if (in_array($model->getTable(), $excludedTables)) {
            return false;
        }

        $modelClass = get_class($model);
        if (method_exists($modelClass, 'addGlobalScope')) {
            $modelClass::addGlobalScope(new SandboxScope());
        }

        return true;
    }

    protected function enablePretendMode(Model $model): void
    {
        $connection = $model->getConnection();
        $connName = $connection->getName();
        $ref = new \ReflectionClass($connection);
        if ($ref->hasProperty('pretending')) {
            $prop = $ref->getProperty('pretending');
            $prop->setAccessible(true);
            $prop->setValue($connection, true);
            $this->pretendingConnections[$connName] = true;
        }
    }

    protected function disablePretendMode(Model $model): void
    {
        $connection = $model->getConnection();
        $connName = $connection->getName();
        if (isset($this->pretendingConnections[$connName])) {
            $ref = new \ReflectionClass($connection);
            if ($ref->hasProperty('pretending')) {
                $prop = $ref->getProperty('pretending');
                $prop->setAccessible(true);
                $prop->setValue($connection, false);
            }
            unset($this->pretendingConnections[$connName]);
        }
    }

    protected function generateId(): string
    {
        return (string) (time() . sprintf('%04d', mt_rand(1000, 9999)));
    }

    protected function getNextSequence(string $sandboxId): int
    {
        $last = \DB::table('sandbox_storage')
            ->where('sandbox_id', $sandboxId)
            ->max('sequence');
        
        return ($last ?? 0) + 1;
    }
}
