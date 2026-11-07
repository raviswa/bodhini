<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SmokeQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_queries_pass_after_a_school_is_installed(): void
    {
        $school = School::query()->firstOrFail();
        $person = User::factory()->create();
        Installation::query()->create([
            'lock_key' => 'application',
            'installed_by' => $person->id,
            'organization_id' => $school->organization_id,
            'school_id' => $school->id,
            'locale' => 'en',
            'demo_data_loaded' => false,
            'email_configured' => false,
            'installed_at' => now(),
        ]);
        school_context()->set($school, remember: false);
        Notice::factory()->create([
            'school_id' => $school->id,
            'active' => true,
            'start_date' => now()->subDay()->toDateString(),
            'stop_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(0, Artisan::call('skuul:smoke-queries'));
        $this->assertStringContainsString('Smoke queries passed', Artisan::output());
    }

    public function test_active_notices_bind_the_boolean_true(): void
    {
        $this->assertContains(true, Notice::query()->active()->getBindings());
    }
}
