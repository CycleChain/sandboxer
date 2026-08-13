<?php

namespace Cyclechain\Sandboxer\Tests\Unit;

use Cyclechain\Sandboxer\Tests\TestCase;
use Cyclechain\Sandboxer\Storage\StorageManager;

class StorageManagerTest extends TestCase
{
    protected StorageManager $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = app(StorageManager::class);
    }

    public function test_it_stores_and_retrieves_records()
    {
        $sandboxId = 'test-sandbox-123';
        
        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => 'posts',
            'record_id' => 'sandbox_1',
            'operation' => 'INSERT',
            'data' => ['id' => 'sandbox_1', 'title' => 'Test Title'],
            'sequence' => 1,
        ]);

        $records = $this->storage->getRecords($sandboxId, 'posts');

        $this->assertCount(1, $records);
        $record = $records->first();
        $this->assertEquals('INSERT', $record->operation);

        $opData = is_string($record->data) ? json_decode($record->data, true) : (array) $record->data;
        $this->assertEquals('Test Title', $opData['title']);
    }

    public function test_it_applies_sandbox_state_to_collection()
    {
        $sandboxId = 'test-sandbox-456';
        
        $this->storage->store([
            'sandbox_id' => $sandboxId,
            'table_name' => 'posts',
            'record_id' => 'sandbox_1',
            'operation' => 'INSERT',
            'data' => ['id' => 'sandbox_1', 'title' => 'Sandboxed Post'],
            'sequence' => 1,
        ]);

        $masterData = collect();
        $result = $this->storage->applySandboxState($sandboxId, $masterData, 'posts');

        $this->assertCount(1, $result);
        $this->assertEquals('Sandboxed Post', $result->first()->title);
    }
}
