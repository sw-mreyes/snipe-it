<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\User;
use Tests\TestCase;

class CancelRequestTest extends TestCase
{
    public function test_user_can_cancel_their_own_pending_request(): void
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->create();
        CheckoutRequest::factory()->create(['requestable_id' => $asset->id, 'requestable_type' => Asset::class, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('account/request-item', ['itemType' => 'asset', 'itemId' => $asset->id]))
            ->assertRedirect();

        $this->assertNotNull(
            CheckoutRequest::where('requestable_id', $asset->id)
                ->where('user_id', $user->id)
                ->whereNotNull('canceled_at')
                ->first()
        );
    }

    public function test_non_admin_cannot_use_cancel_by_admin_to_cancel_another_users_request(): void
    {
        $asset = Asset::factory()->create();
        $victim = User::factory()->create();
        CheckoutRequest::factory()->create(['requestable_id' => $asset->id, 'requestable_type' => Asset::class, 'user_id' => $victim->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('account/request-item', [
                'itemType' => 'asset',
                'itemId' => $asset->id,
                'cancel_by_admin' => '1',
                'requestingUser' => $victim->id,
            ]))
            ->assertForbidden();

        $this->assertNull(
            CheckoutRequest::where('requestable_id', $asset->id)
                ->where('user_id', $victim->id)
                ->whereNotNull('canceled_at')
                ->first()
        );
    }

    public function test_user_with_own_pending_request_cannot_cancel_another_users_request_via_requestinguser_param(): void
    {
        $asset = Asset::factory()->create();
        $actor = User::factory()->create();
        $victim = User::factory()->create();

        CheckoutRequest::factory()->create(['requestable_id' => $asset->id, 'requestable_type' => Asset::class, 'user_id' => $actor->id]);
        CheckoutRequest::factory()->create(['requestable_id' => $asset->id, 'requestable_type' => Asset::class, 'user_id' => $victim->id]);

        $this->actingAs($actor)
            ->post(route('account/request-item', [
                'itemType' => 'asset',
                'itemId' => $asset->id,
                'cancel_by_admin' => '0',
                'requestingUser' => $victim->id,
            ]))
            ->assertRedirect();

        // Actor's own request is cancelled
        $this->assertNotNull(
            CheckoutRequest::where('requestable_id', $asset->id)
                ->where('user_id', $actor->id)
                ->whereNotNull('canceled_at')
                ->first()
        );

        // Victim's request is untouched
        $this->assertNull(
            CheckoutRequest::where('requestable_id', $asset->id)
                ->where('user_id', $victim->id)
                ->whereNotNull('canceled_at')
                ->first()
        );
    }

    public function test_admin_can_cancel_another_users_request_via_cancel_by_admin(): void
    {
        $asset = Asset::factory()->create();
        $victim = User::factory()->create();
        CheckoutRequest::factory()->create(['requestable_id' => $asset->id, 'requestable_type' => Asset::class, 'user_id' => $victim->id]);

        $this->actingAs(User::factory()->viewAssets()->create())
            ->post(route('account/request-item', [
                'itemType' => 'asset',
                'itemId' => $asset->id,
                'cancel_by_admin' => '1',
                'requestingUser' => $victim->id,
            ]))
            ->assertRedirect();

        $this->assertNotNull(
            CheckoutRequest::where('requestable_id', $asset->id)
                ->where('user_id', $victim->id)
                ->whereNotNull('canceled_at')
                ->first()
        );
    }
}
