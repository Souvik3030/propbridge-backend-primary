<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role; // Add this
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create roles manually since seeder is not loaded
     */
    protected function setupRoles()
    {
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'agent', 'guard_name' => 'web']);
    }

    /** @test */
    public function superadmin_can_create_a_pending_invitation()
    {
        $this->setupRoles(); // Create roles first
        Queue::fake();

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        
        $company = Company::factory()->create();

        $response = $this->actingAs($superadmin)->postJson('/api/auth/invitations', [
            'email' => 'testadmin@emaar.com',
            'role' => 'admin',
            'company_id' => $company->id,
            'is_send_now' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invitations', [
            'email' => 'testadmin@emaar.com',
            'sent_at' => null 
        ]);
    }

    /** @test */
    public function unauthorized_user_cannot_send_invitation()
    {
        $this->setupRoles();
        
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $response = $this->actingAs($agent)->postJson('/api/auth/invitations', [
            'email' => 'hack@system.com',
            'role' => 'admin',
            'company_id' => 'some-id',
            'is_send_now' => true,
        ]);

        $response->assertStatus(403);
    }
}