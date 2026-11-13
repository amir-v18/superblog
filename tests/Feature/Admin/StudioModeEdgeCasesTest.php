<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\StudioModeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

/**
 * The few admin spots with set palette colors keep their classes and only get extra light-mode classes.
 */
class StudioModeEdgeCasesTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_the_login_error_keeps_its_classes_and_gets_light_error_colors(): void
    {
        $classes = 'mt-5 rounded-lg bg-studio-brand p-3 text-sm text-red-800 studio-light:bg-studio-bad-bg studio-light:text-studio-bad studio-light:border studio-light:border-studio-bad/30';

        // Same markup in both modes: the extra classes only take effect under the light attribute.
        foreach ([null, 'light'] as $mode) {
            $request = $this->withSession(['error' => 'Inloggningen misslyckades.']);
            if ($mode) {
                $request = $request->withCookie(StudioModeController::COOKIE, $mode);
            }

            $request->get(route('login'))->assertOk()
                ->assertSee('<p role="alert" class="'.$classes.'">Inloggningen misslyckades.</p>', false);
        }
    }

    public function test_the_unpublish_button_keeps_its_classes_and_gets_a_stronger_light_edge(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published']);

        $this->actingAs($admin)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))->assertOk()
            ->assertSee('class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 studio-focus-ring studio-light:border-studio-bad">Avpublicera</button>', false);
    }
}
