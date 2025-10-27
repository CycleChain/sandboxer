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

        // Cache'e yaz (tag olmadan) - keep original array for cache
        if (config('sandboxer.cache.enabled')) {
            Cache::put($cacheKey, $data, config('sandboxer.cache.ttl'));
        }
    }
    
    public function getRecords(string $sandboxId, string $table, array $conditions = []): Collection
    {
        // Önce cache'den dene
        if (config('sandboxer.cache.enabled')) {
            $cached = $this->getCachedRecords($sandboxId, $table);
            if ($cached->isNotEmpty()) {
                return $this->filterByConditions($cached, $conditions);
            }
        }
        
        // Database'den getir
        $query = DB::table('sandbox_storage')
            ->where('sandbox_id', $sandboxId)
            ->where('table_name', $table)
            ->orderBy('sequence');
        
        // Conditions uygula
        foreach ($conditions as $field => $value) {
            $query->whereJsonContains("data->{$field}", $value);
        }
        
        $records = $query->get();
        
        // Cache'e kaydet
        if (config('sandboxer.cache.enabled')) {
            foreach ($records as $record) {
                $cacheKey = $this->getCacheKey($sandboxId, $table, $record->record_id);
                Cache::put(
                    $cacheKey,
                    $record,
                    config('sandboxer.cache.ttl')
                );
            }
        }
        
        return $records;
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
            ->where('record_id', $recordId)
            ->orderByDesc('sequence')
            ->first();
        
        // Cache'e kaydet
        if ($record && config('sandboxer.cache.enabled')) {
            Cache::put($cacheKey, $record, config('sandboxer.cache.ttl'));
        }
        
        return $record;
    }
    
    public function applySandboxState(string $sandboxId, Collection $masterData, string $table): Collection
    {
        $sandboxOps = $this->getRecords($sandboxId, $table);
        
        // Operation'ları sırayla uygula
        foreach ($sandboxOps->groupBy('record_id') as $recordId => $operations) {
            $lastOp = $operations->sortByDesc('sequence')->first();
            
            switch ($lastOp->operation) {
                case 'DELETE':
                    $masterData = $masterData->reject(fn($item) => $item->id == $recordId);
                    break;
                    
                case 'UPDATE':
                    $masterData = $masterData->map(function ($item) use ($recordId, $lastOp) {
                        if ($item->id == $recordId) {
                            return (object) array_merge(
                                (array) $item,
                                json_decode($lastOp->changed_fields, true) ?? []
                            );
                        }
                        return $item;
                    });
                    break;
                    
                case 'INSERT':
                case 'SNAPSHOT':
                case 'AUTH':
                    $data = is_string($lastOp->data) 
                        ? json_decode($lastOp->data, true) 
                        : $lastOp->data;
                    $masterData->push((object) $data);
                    break;
            }
        }

        return $masterData;
    }
    
    protected function getCacheKey(string $sandboxId, string $table, string $recordId): string
    {
        $prefix = config('sandboxer.cache.prefix', 'sandbox');
        return "{$prefix}:{$sandboxId}:{$table}:{$recordId}";
    }
    
    protected function getCachedRecords(string $sandboxId, string $table): Collection
    {
        // Cache tag desteği olmadan, doğrudan database'den çalışıyoruz
        // Cached records için database query kullanıyoruz
        return collect();
    }
    
    protected function filterByConditions(Collection $data, array $conditions): Collection
    {
        if (empty($conditions)) {
            return $data;
        }
        
        return $data->filter(function ($record) use ($conditions) {
            $recordData = is_string($record->data) 
                ? json_decode($record->data, true) 
                : $record->data;
            
            foreach ($conditions as $field => $value) {
                if (!isset($recordData[$field]) || $recordData[$field] != $value) {
                    return false;
                }
            }
            
            return true;
        });
    }
}
