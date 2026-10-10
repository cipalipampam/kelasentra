<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubjectWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_subjects_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_update_subject(): void
    {
        $subject = Subject::create([
            'code' => 'TEST-1',
            'name' => 'Test Subject',
            'color_code' => '#123456',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.subjects.update', $subject->id), [
            'code' => 'TEST-1',
            'name' => 'Test Subject Updated',
            'cluster' => 'mipa',
            'color_code' => '#654321',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Test Subject Updated',
            'cluster' => 'mipa',
            'color_code' => '#654321',
        ]);
    }

    public function test_admin_can_deactivate_subject(): void
    {
        $subject = Subject::create([
            'code' => 'TEST-ACT',
            'name' => 'Test Subject Active',
            'cluster' => 'bahasa',
            'color_code' => '#123456',
            'is_active' => true,
        ]);

        // When checkbox is unchecked in browser, is_active is omitted from form payload
        $response = $this->actingAs($this->admin)->put(route('admin.subjects.update', $subject->id), [
            'code' => 'TEST-ACT',
            'name' => 'Test Subject Active',
            'cluster' => 'bahasa',
            'color_code' => '#123456',
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_subject_without_schedule(): void
    {
        $subject = Subject::create([
            'code' => 'TEST-DEL',
            'name' => 'Test Delete',
            'color_code' => '#123456',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        // Riwayat mata pelajaran tetap tersimpan: nonaktif, bukan hilang.
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
    }
}
