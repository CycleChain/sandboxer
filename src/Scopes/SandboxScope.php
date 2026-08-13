<?php

namespace Cyclechain\Sandboxer\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Cyclechain\Sandboxer\SandboxManager;
use Cyclechain\Sandboxer\Storage\StorageManager;

class SandboxScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (!SandboxManager::isActive()) {
            return;
        }

        $excludedTables = array_merge(
            (array) config('sandboxer.excluded_tables', ['users']),
            ['sandbox_sessions', 'sandbox_storage']
        );

        if (in_array($model->getTable(), $excludedTables)) {
            return;
        }

        $sandboxId = SandboxManager::currentId();
        if (!$sandboxId) {
            return;
        }

        $storage = app(StorageManager::class);
        $tableName = $model->getTable();
        $modelClass = get_class($model);

        $callback = function ($results) use ($storage, $sandboxId, $tableName, $modelClass) {
            if ($results === null) {
                return $results;
            }

            $collection = $results instanceof Collection ? $results : collect($results);
            return $storage->applySandboxState($sandboxId, $collection, $tableName, $modelClass);
        };

        // Attach to builder
        $builder->afterQuery($callback);

        // Find original builder from applyScopes backtrace if cloned
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        foreach ($trace as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof Builder && $frame['object'] !== $builder) {
                $frame['object']->afterQuery($callback);
                break;
            }
        }
    }
}
