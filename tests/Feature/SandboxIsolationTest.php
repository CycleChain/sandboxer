<?php

namespace Cyclechain\Sandboxer\Tests\Feature;

use Cyclechain\Sandboxer\Tests\TestCase;
use Cyclechain\Sandboxer\Tests\Models\TestPost;
use Cyclechain\Sandboxer\Tests\Models\TestProduct;
use Cyclechain\Sandboxer\SandboxManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SandboxIsolationTest extends TestCase
{
    protected SandboxManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SandboxManager::class);
    }

    public function test_it_isolates_model_creation()
    {
        $request = Request::create('http://localhost/demo');
        $this->manager->initialize($request);

        $this->assertTrue(SandboxManager::isActive());
        $sandboxId = $this->manager->getCurrentId();
        $this->assertNotNull($sandboxId);

        $post = TestPost::create([
            'title' => 'Sandboxed Post',
            'content' => 'This post is isolated',
        ]);

        $this->assertTrue($post->exists);
        $this->assertNotEmpty($post->id);

        // Debug check storage
        $recordsInDb = DB::table('sandbox_storage')->where('sandbox_id', $sandboxId)->where('table_name', 'posts')->get();
        $this->assertCount(1, $recordsInDb, 'Expected 1 record in sandbox_storage DB');

        $storageRecords = app(\Cyclechain\Sandboxer\Storage\StorageManager::class)->getRecords($sandboxId, 'posts');
        $this->assertCount(1, $storageRecords, 'Expected 1 record from StorageManager::getRecords');

        // Verify real database has 0 rows
        $this->assertEquals(0, DB::table('posts')->count());

        // Verify sandbox_storage has 1 record
        $this->assertDatabaseHas('sandbox_storage', [
            'sandbox_id' => $this->manager->getCurrentId(),
            'table_name' => 'posts',
            'operation' => 'INSERT',
        ]);

        // Verify querying models retrieves the sandboxed post
        $posts = TestPost::all();
        $this->assertCount(1, $posts);
        $this->assertEquals('Sandboxed Post', $posts->first()->title);
    }

    public function test_it_isolates_model_updates()
    {
        $request = Request::create('http://localhost/demo');
        $this->manager->initialize($request);

        $post = TestPost::create([
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]);

        $post->update(['title' => 'Updated Title']);

        // Real database remains empty
        $this->assertEquals(0, DB::table('posts')->count());

        // Sandbox query reflects the updated title
        $retrieved = TestPost::find($post->id);
        $this->assertNotNull($retrieved);
        $this->assertEquals('Updated Title', $retrieved->title);
    }

    public function test_it_isolates_model_deletions()
    {
        $request = Request::create('http://localhost/demo');
        $this->manager->initialize($request);

        $post = TestPost::create([
            'title' => 'Post To Delete',
        ]);

        $this->assertCount(1, TestPost::all());

        $post->delete();

        // Real DB has 0 rows
        $this->assertEquals(0, DB::table('posts')->count());

        // Sandbox query returns 0 items
        $this->assertCount(0, TestPost::all());
    }

    public function test_it_merges_sandbox_operations_with_master_data()
    {
        // Seed real database
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Real Laptop', 'price' => 1000.00],
            ['id' => 2, 'name' => 'Real Phone', 'price' => 500.00],
        ]);

        // Activate sandbox
        $request = Request::create('http://localhost/demo');
        $this->manager->initialize($request);

        // 1. Update Real Laptop in sandbox
        $laptop = TestProduct::find(1);
        $laptop->update(['name' => 'Sandboxed Laptop', 'price' => 1200.00]);

        // 2. Delete Real Phone in sandbox
        $phone = TestProduct::find(2);
        $phone->delete();

        // 3. Create Sandboxed Tablet
        TestProduct::create(['name' => 'Sandboxed Tablet', 'price' => 300.00]);

        // VERIFY REAL DATABASE HAS NOT CHANGED
        $realLaptop = DB::table('products')->where('id', 1)->first();
        $this->assertEquals('Real Laptop', $realLaptop->name);
        $this->assertEquals(2, DB::table('products')->count());

        // VERIFY SANDBOX QUERY REFLECTS MERGED STATE
        $products = TestProduct::all();
        $this->assertCount(2, $products); // Sandboxed Laptop + Sandboxed Tablet (Real Phone is deleted)

        $productNames = $products->pluck('name')->all();
        $this->assertContains('Sandboxed Laptop', $productNames);
        $this->assertContains('Sandboxed Tablet', $productNames);
        $this->assertNotContains('Real Phone', $productNames);
    }
}
