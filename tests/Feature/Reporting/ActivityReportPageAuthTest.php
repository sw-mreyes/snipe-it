<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Tests\TestCase;

/**
 * The activity report page (reports/activity) is reachable by two
 * viewer classes with different UI:
 *   - reports.view holders get the full page with CSV export and
 *     the general search / filter / sort endpoint.
 *   - Scoped viewers (canViewUsersAndCheckoutables, no reports.view)
 *     arrive through the dashboard Recent Activity widget's View-all
 *     button, land on the same template with admin-shaped UI stripped,
 *     and read from the narrow api.dashboard.activity endpoint.
 *
 * These tests pin both paths so a future permission change does not
 * quietly 403 the widget click-through or leak the export button.
 */
class ActivityReportPageAuthTest extends TestCase
{
    public function test_permissionless_user_is_forbidden()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.activity'))
            ->assertForbidden();
    }

    public function test_reports_view_holder_reaches_page()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports.activity'))
            ->assertOk();
    }

    public function test_scoped_viewer_reaches_page()
    {
        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('reports.activity'))
            ->assertOk();
    }

    public function test_reports_view_holder_sees_csv_export_button()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertSee(route('reports.activity.post'));
    }

    public function test_scoped_viewer_also_sees_csv_export_button()
    {
        // Scoped viewers get an export gated to the same viewable
        // types they see on the page. Filtering happens inside
        // postActivityReport.
        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertSee(route('reports.activity.post'));
    }

    public function test_scoped_viewer_page_points_at_narrow_dashboard_endpoint()
    {
        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertSee(route('api.dashboard.activity'))
            ->assertDontSee(route('api.activity.index'));
    }

    public function test_reports_view_holder_page_points_at_general_endpoint()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertSee(route('api.activity.index'));
    }

    public function test_permissionless_user_cannot_hit_csv_export_endpoint()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('reports.activity.post'))
            ->assertForbidden();
    }

    public function test_scoped_viewer_csv_export_only_contains_rows_for_viewable_types()
    {
        // A components-only viewer's CSV must contain rows for
        // Component actionlogs and omit rows for Asset / License /
        // Consumable / Accessory actionlogs. Same viewable-type
        // filter the on-page endpoint applies.
        $component = \App\Models\Component::factory()->create();
        $asset = \App\Models\Asset::factory()->create();

        \App\Models\Actionlog::factory()->create([
            'item_type' => \App\Models\Component::class,
            'item_id' => $component->id,
            'action_type' => 'checkout',
            'note' => 'COMPONENT_ROW_SENTINEL',
        ]);
        \App\Models\Actionlog::factory()->create([
            'item_type' => \App\Models\Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'ASSET_ROW_SENTINEL',
        ]);

        $body = $this->actingAs(User::factory()->viewComponents()->create())
            ->post(route('reports.activity.post'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('COMPONENT_ROW_SENTINEL', $body);
        $this->assertStringNotContainsString('ASSET_ROW_SENTINEL', $body);
    }

    public function test_reports_view_holder_csv_export_contains_every_type()
    {
        // reports.view holders keep the full unfiltered CSV. Guard
        // against a future refactor that accidentally applies the
        // scoped-viewer filter to admin exports.
        $component = \App\Models\Component::factory()->create();
        $asset = \App\Models\Asset::factory()->create();

        \App\Models\Actionlog::factory()->create([
            'item_type' => \App\Models\Component::class,
            'item_id' => $component->id,
            'action_type' => 'checkout',
            'note' => 'COMPONENT_ROW_SENTINEL',
        ]);
        \App\Models\Actionlog::factory()->create([
            'item_type' => \App\Models\Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'ASSET_ROW_SENTINEL',
        ]);

        $body = $this->actingAs(User::factory()->canViewReports()->create())
            ->post(route('reports.activity.post'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('COMPONENT_ROW_SENTINEL', $body);
        $this->assertStringContainsString('ASSET_ROW_SENTINEL', $body);
    }

    public function test_scoped_viewer_breadcrumb_does_not_link_to_reports_index()
    {
        // reports.index is admin-shaped. A scoped viewer's activity
        // report breadcrumb must not include a "Reports" crumb whose
        // link would 403 on click. Uses the URL with the closing "
        // so plain substring assertions don't collide with the
        // reports.activity URL (which contains reports.index's URL
        // as a prefix).
        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertDontSee(route('reports.index').'"', false);
    }

    public function test_reports_view_holder_breadcrumb_links_to_reports_index()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports.activity'))
            ->assertOk()
            ->assertSee(route('reports.index').'"', false);
    }

    public function test_scoped_viewer_unaccepted_acceptance_breadcrumb_does_not_link_to_reports_index()
    {
        // Same fix retroactively applied to the acceptances report
        // widened in an earlier batch.
        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('reports/unaccepted_assets'))
            ->assertOk()
            ->assertDontSee(route('reports.index').'"', false);
    }

    public function test_reports_view_holder_unaccepted_acceptance_breadcrumb_links_to_reports_index()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports/unaccepted_assets'))
            ->assertOk()
            ->assertSee(route('reports.index').'"', false);
    }
}
