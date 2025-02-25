<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $account = Account::create(['name' => 'Acme Corporation']);

        $this->user = User::factory()->make([
            'account_id' => $account->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'owner' => true,
            'role' => 'admin',
        ]);
    }

    public function test_can_view_organizations()
    {
        $this->user->account->organizations()->saveMany(
            Organization::factory()->count(5)->make()
        );

        $this->actingAs($this->user)
            ->get('/organizations')
            ->assertStatus(200)
            ->assertInertia(function (Assert $page) {
                $page->component('Organizations/Index');
                $page->has('organizations.data', 5, function (Assert $page) {
                    $page->hasAll(['id', 'name', 'phone', 'city', 'deleted_at']);
                });
            });
    }

    public function test_can_search_for_organizations()
    {
        $this->user->account->organizations()->saveMany(
            Organization::factory()->count(5)->make()
        )->first()->update(['name' => 'Some Big Fancy Company Name']);

        $this->actingAs($this->user)
            ->get('/organizations?search=Some Big Fancy Company Name')
            ->assertStatus(200)
            ->assertInertia(function (Assert $page) {
                $page->where('filters.search', 'Some Big Fancy Company Name');
                $page->has('organizations.data', 1, function (Assert $page) {
                    $page->where('name', 'Some Big Fancy Company Name')->etc();
                });
            });
    }

    public function test_cannot_view_deleted_organizations()
    {
        $this->user->account->organizations()->saveMany(
            Organization::factory()->count(5)->make()
        )->first()->delete();

        $this->actingAs($this->user)
            ->get('/organizations')
            ->assertStatus(200)
            ->assertInertia(function (Assert $page) {
                $page->has('organizations.data', 4);
            });
    }

    public function test_can_filter_to_view_deleted_organizations()
    {
        $this->user->account->organizations()->saveMany(
            Organization::factory()->count(5)->make()
        )->first()->delete();

        $this->actingAs($this->user)
            ->get('/organizations?trashed=with')
            ->assertStatus(200)
            ->assertInertia(function (Assert $page) {
                $page->where('filters.trashed', 'with');
                $page->has('organizations.data', 5);
            });
    }

    // Role based tests

    public function test_admin_has_full_access_of_organization() 
    {
		$this->user->role = 'admin';
        $this->actingAs($this->user);

        // view access
        $this->get('/organizations')->assertStatus(200);

		// store access
        $data = ['name' => 'New Org', 'phone' => '866.733.2162', 'city' => 'New York'];
		$response = $this->post('/organizations', $data);

		$response->assertRedirect(route('organizations'))
             ->assertSessionHas('success', 'Organization created.');

		// update access
		$organization = Organization::factory()->create([
			'account_id' => $this->user->account_id
		]);
        $updateData = ['name' => 'Updated Org'];
        $response = $this->put("/organizations/{$organization->id}", $updateData);
		$response->assertRedirect()->assertSessionHas('success', 'Organization updated.');

		// delete access
		$response = $this->delete("/organizations/{$organization->id}");
		$response->assertRedirect()->assertSessionHas('success', 'Organization deleted.');
    }
}
