<?php

namespace Cyclechain\Sandboxer\Storage;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StorageManager
{
    public function store(array $data): void
    {
        $cacheKey = $this->getCacheKey(
            $data['sandbox_id'],
            $data['table_name'],
            $data['record_id']
        );
        
        // Prepare data for database - encode JSON fields
        $insertData = array_merge($data, [
            'id' => Str::uuid()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Encode JSON fields
        if (isset($insertData['data']) && is_array($insertData['data'])) {
            $insertData['data'] = json_encode($insertData['data']);
        }
        
        if (isset($insertData['changed_fields']) && is_array($insertData['changed_fields'])) {
            $insertData['changed_fields'] = json_encode($insertData['changed_fields']);
        }
        
        // Database'e persist et
        DB::table('sandbox_storage')->insert($insertData);

        // Cache'e yaz
        if (config('sandboxer.cache.enabled')) {
            Cache::put($cacheKey, (object) $insertData, config('sandboxer.cache.ttl', 3600));
            $this->addKeyToCacheIndex($data['sandbox_id'], $data['table_name'], $data['record_id']);
        }
    }
    
    public function getRecords(string $sandboxId, string $table, array $conditions = []): Collection
    {
        $query = DB::table('sandbox_storage')
            ->where('sandbox_id', $sandboxId)
            ->where('table_name', $table)
            ->orderBy('sequence');
        
        foreach ($conditions as $field => $value) {
            $query->whereJsonContains("data->{$field}", $value);
        }
        
        return $query->get();
    }
    
    public function findRecord(string $sandboxId, string $table, string $recordId)
    {
        $cacheKey = $this->getCacheKey($sandboxId, $table, $recordId);
        
        // Önce cache'den bak
        if (config('sandboxer.cache.enabled')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }
        
        // Database'den getir
        $record = DB::table('sandbox_storage')
            ->where('sandbox_id', $sandboxId)
            ->where('table_name', $table)
            ->where('record_id', (string) $recordId)
            ->orderByDesc('sequence')
            ->first();
        
        // Cache'e kaydet
        if ($record && config('sandboxer.cache.enabled')) {
            Cache::put($cacheKey, $record, config('sandboxer.cache.ttl', 3600));
        }
        
        return $record;
    }
    
    public function applySandboxState(string $sandboxId, Collection $masterData, string $table, ?string $modelClass = null): Collection
    {
        $sandboxOps = $this->getRecords($sandboxId, $table);
        
        if ($sandboxOps->isEmpty()) {
            return $masterData;
        }

        // Replay all operations in sequence order
        $sortedOps = $sandboxOps->sortBy('sequence');

        foreach ($sortedOps as $op) {
            $recordId = (string) $op->record_id;
            $opData = is_string($op->data) ? json_decode($op->data, true) : (array) $op->data;
            
            switch ($op->operation) {
                case 'DELETE':
                    $masterData = $masterData->reject(function ($item) use ($recordId) {
                        $itemId = is_object($item) ? ($item->id ?? (method_exists($item, 'getKey') ? $item->getKey() : null)) : ($item['id'] ?? null);
                        return (string) $itemId === (string) $recordId;
                    });
                    break;
                    
                case 'UPDATE':
                    $changedFields = is_string($op->changed_fields) 
                        ? json_decode($op->changed_fields, true) 
                        : (array) ($op->changed_fields ?? []);

                    $found = false;
                    $masterData = $masterData->map(function ($item) use ($recordId, $changedFields, &$found) {
                        $itemId = is_object($item) ? ($item->id ?? (method_exists($item, 'getKey') ? $item->getKey() : null)) : ($item['id'] ?? null);
                        if ((string) $itemId === (string) $recordId) {
                            $found = true;
                            if (is_object($item) && method_exists($item, 'forceFill')) {
                                $item->forceFill($changedFields);
                                $item->syncOriginal();
                                return $item;
                            } elseif (is_object($item)) {
                                return (object) array_merge((array) $item, $changedFields);
                            } else {
                                return array_merge((array) $item, $changedFields);
                            }
                        }
                        return $item;
                    });

                    if (!$found && !empty($opData)) {
                        $masterData->push((object) array_merge($opData, $changedFields));
                    }
                    break;
                    
                case 'INSERT':
                case 'SNAPSHOT':
                case 'AUTH':
                    // Check if already present in masterData
                    $exists = $masterData->contains(function ($item) use ($recordId) {
                        $itemId = is_object($item) ? ($item->id ?? (method_exists($item, 'getKey') ? $item->getKey() : null)) : ($item['id'] ?? null);
                        return (string) $itemId === (string) $recordId;
                    });

                    if (!$exists) {
                        $masterData->push((object) $opData);
                    }
                    break;
            }
        }

        return $masterData->values();
    }
    
    protected function getCacheKey(string $sandboxId, string $table, string $recordId): string
    {
        $prefix = config('sandboxer.cache.prefix', 'sandbox');
        return "{$prefix}:{$sandboxId}:{$table}:{$recordId}";
    }

    protected function addKeyToCacheIndex(string $sandboxId, string $table, string $recordId): void
    {
        $indexKey = "{$sandboxId}:{$table}:index";
        $keys = Cache::get($indexKey, []);
        if (!in_array($recordId, $keys)) {
            $keys[] = $recordId;
            Cache::put($indexKey, $keys, config('sandboxer.cache.ttl', 3600));
        }
    }
    
    protected function getCachedRecords(string $sandboxId, string $table): Collection
    {
        $indexKey = "{$sandboxId}:{$table}:index";
        $keys = Cache::get($indexKey, []);
        
        if (empty($keys)) {
            return collect();
        }

        $records = collect();
        foreach ($keys as $recordId) {
            $cacheKey = $this->getCacheKey($sandboxId, $table, $recordId);
            $record = Cache::get($cacheKey);
            if ($record) {
                $records->push($record);
            }
        }

        return $records;
    }
    
    protected function filterByConditions(Collection $data, array $conditions): Collection
    {
        if (empty($conditions)) {
            return $data;
        }
        
        return $data->filter(function ($record) use ($conditions) {
            $recordData = is_string($record->data) 
                ? json_decode($record->data, true) 
                : (array) $record->data;
            
            foreach ($conditions as $field => $value) {
                if (!isset($recordData[$field]) || $recordData[$field] != $value) {
                    return false;
                }
            }
            
            return true;
        });
    }
}
