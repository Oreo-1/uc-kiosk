<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodControllerTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake storage disk to avoid writing real files during tests
        Storage::fake('public');
        
        // Create a base vendor for tests
        $this->vendor = Vendor::create(['id' => 1, 'name' => 'Test Vendor']);
    }

    // ================= INDEX =================
    public function test_can_list_foods(): void
    {
        Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'Nasi Goreng', 'type' => 'FOOD',
            'price' => 15000, 'estimated_time' => 10, 'active' => 1
        ]);

        $response = $this->getJson('/api/foods');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['*' => ['id', 'vendor_id', 'name', 'type', 'price', 'estimated_time', 'active']],
                     'links', 'meta'
                 ]);
    }

    public function test_can_filter_foods_by_type(): void
    {
        Food::create(['id' => 1, 'vendor_id' => 1, 'name' => 'Food A', 'type' => 'FOOD', 'price' => 10000, 'estimated_time' => 10]);
        Food::create(['id' => 2, 'vendor_id' => 1, 'name' => 'Drink A', 'type' => 'DRINK', 'price' => 5000, 'estimated_time' => 5]);

        $response = $this->getJson('/api/foods?type=DRINK');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Drink A');
    }

    // ================= STORE =================
    public function test_can_store_food_with_image(): void
    {
        $file = UploadedFile::fake()->image('food.jpg');

        $response = $this->postJson('/api/foods', [
            'vendor_id' => 1,
            'name' => 'Mie Goreng',
            'type' => 'FOOD',
            'price' => 12000,
            'estimated_time' => 15,
            'active' => true,
            'image' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Food created successfully.')
                 ->assertJsonPath('data.name', 'Mie Goreng');

        $this->assertDatabaseHas('food', ['name' => 'Mie Goreng']);
        Storage::disk('public')->assertExists('foods/' . $file->hashName());
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/foods', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['vendor_id', 'name', 'type', 'price', 'estimated_time']);
    }

    public function test_store_validates_enum_values(): void
    {
        $response = $this->postJson('/api/foods', [
            'vendor_id' => 1, 'name' => 'Bad Type', 'type' => 'INVALID_TYPE',
            'price' => 10000, 'estimated_time' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Food::create(['id' => 1, 'vendor_id' => 1, 'name' => 'Unique', 'type' => 'FOOD', 'price' => 10000, 'estimated_time' => 10]);

        $response = $this->postJson('/api/foods', [
            'vendor_id' => 1, 'name' => 'Unique', 'type' => 'FOOD',
            'price' => 12000, 'estimated_time' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_vendor_exists(): void
    {
        $response = $this->postJson('/api/foods', [
            'vendor_id' => 999, 'name' => 'Test', 'type' => 'FOOD',
            'price' => 10000, 'estimated_time' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['vendor_id']);
    }

    // ================= SHOW =================
    public function test_can_show_food(): void
    {
        $food = Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'Sate', 'type' => 'FOOD',
            'price' => 20000, 'estimated_time' => 20, 'active' => 1
        ]);

        $response = $this->getJson("/api/foods/{$food->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', 1)
                 ->assertJsonPath('data.name', 'Sate');
    }

    // ================= UPDATE =================
    public function test_can_update_food(): void
    {
        $food = Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'Old Name', 'type' => 'FOOD',
            'price' => 10000, 'estimated_time' => 10, 'active' => 1
        ]);

        $response = $this->putJson("/api/foods/{$food->id}", [
            'name' => 'New Name',
            'price' => 15000,
            'estimated_time' => 12,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'New Name')
                 ->assertJsonPath('data.price', 15000);

        $this->assertDatabaseHas('food', ['id' => 1, 'name' => 'New Name']);
    }

    public function test_update_replaces_old_image(): void
    {
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $food = Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'Food', 'type' => 'FOOD',
            'price' => 10000, 'estimated_time' => 10, 'image' => 'foods/' . $oldFile->hashName(), 'active' => 1
        ]);
        Storage::disk('public')->put('foods/' . $oldFile->hashName(), 'content');

        $newFile = UploadedFile::fake()->image('new.jpg');
        $response = $this->putJson("/api/foods/{$food->id}", ['image' => $newFile]);

        $response->assertStatus(200);
        Storage::disk('public')->assertMissing('foods/' . $oldFile->hashName());
        Storage::disk('public')->assertExists('foods/' . $newFile->hashName());
    }

    public function test_update_validates_unique_name_ignoring_current(): void
    {
        $food = Food::create(['id' => 1, 'vendor_id' => 1, 'name' => 'Keep', 'type' => 'FOOD', 'price' => 10000, 'estimated_time' => 10]);

        // Updating without changing name should pass
        $response = $this->putJson("/api/foods/{$food->id}", ['name' => 'Keep', 'price' => 12000, 'estimated_time' => 10]);
        $response->assertStatus(200);
    }

    // ================= DESTROY =================
    public function test_can_destroy_food(): void
    {
        $food = Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'Delete Me', 'type' => 'FOOD',
            'price' => 10000, 'estimated_time' => 10, 'active' => 1
        ]);

        $response = $this->deleteJson("/api/foods/{$food->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Food deleted successfully.');

        $this->assertDatabaseMissing('food', ['id' => 1]);
    }

    public function test_destroy_removes_associated_image(): void
    {
        $file = UploadedFile::fake()->image('todelete.jpg');
        $food = Food::create([
            'id' => 1, 'vendor_id' => 1, 'name' => 'With Image', 'type' => 'FOOD',
            'price' => 10000, 'estimated_time' => 10, 'image' => 'foods/' . $file->hashName(), 'active' => 1
        ]);
        Storage::disk('public')->put('foods/' . $file->hashName(), 'content');

        $response = $this->deleteJson("/api/foods/{$food->id}");
        $response->assertStatus(200);

        Storage::disk('public')->assertMissing('foods/' . $file->hashName());
    }
}