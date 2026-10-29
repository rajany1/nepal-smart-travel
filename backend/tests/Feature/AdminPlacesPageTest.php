<?php

use App\Models\Role;
use App\Models\User;
use App\Services\PlacesCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Places command center
|--------------------------------------------------------------------------
|
| Pins the enhanced places list: global KPIs/tabs render, status/category/
| district/source/search filters and per-page work, bulk edit accepts an
| array of ids, single-place mutations still work, category CRUD survives,
| reviews can be inspected/deleted, moderators keep access and regular users
| cannot reach the admin area.
|
| Fixtures use the live DB and are removed in afterEach.
|
*/

beforeEach(function () {
    $GLOBALS['apt_user_ids'] = [];
    Cache::forget('admin:places:insights:v'.PlacesCache::version());
});

afterEach(function () {
    $placeIds = DB::table('places')
        ->where('name', 'like', 'APT %')
        ->pluck('id')
        ->all();

    if ($placeIds !== []) {
        DB::table('place_images')->whereIn('place_id', $placeIds)->delete();
        DB::table('place_reviews')->whereIn('place_id', $placeIds)->delete();
        DB::table('moderation_queues')
            ->where('content_type', 'place')
            ->whereIn('content_id', $placeIds)
            ->delete();
        DB::table('places')->whereIn('id', $placeIds)->delete();
    }

    DB::table('place_categories')->where('name', 'like', 'APT Cat %')->delete();

    $userIds = $GLOBALS['apt_user_ids'] ?? [];
    if ($userIds !== []) {
        DB::table('audit_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    $GLOBALS['apt_user_ids'] = [];
    Cache::forget('admin:places:insights:v'.PlacesCache::version());
});

function aptUser(string $role = 'admin'): User
{
    $user = User::create([
        'name' => 'APT '.ucfirst($role),
        'email' => 'apt_'.Str::random(12).'@example.com',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'status' => 'active',
        'is_active' => 1,
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
        'total_xp' => 0,
        'current_level' => 1,
    ]);

    User::whereKey($user->id)->update(['role_id' => Role::where('name', $role)->value('id')]);
    $GLOBALS['apt_user_ids'][] = $user->id;

    return $user->fresh();
}

function aptCategory(): int
{
    return DB::table('place_categories')->insertGetId([
        'name' => 'APT Cat '.Str::random(8),
        'icon' => 'fa-map-pin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function aptPlace(array $overrides = []): int
{
    if (!isset($overrides['category_id'])) {
        $overrides['category_id'] = aptCategory();
    }

    return DB::table('places')->insertGetId(array_merge([
        'uuid' => Str::uuid()->toString(),
        'created_by' => null,
        'name' => 'APT place '.Str::random(8),
        'description' => 'APT places command center fixture.',
        'address' => 'APT address',
        'district' => 'Kathmandu',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'average_rating' => 0,
        'total_reviews' => 0,
        'is_verified' => 0,
        'is_featured' => 0,
        'is_active' => 0,
        'source' => 'admin',
        'image_discovery_status' => 'none',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('renders the places command center with global KPIs, tabs and tools', function () {
    $admin = aptUser('admin');
    $category = aptCategory();
    $verifiedId = aptPlace([
        'name' => 'APT verified cafe',
        'category_id' => $category,
        'is_verified' => 1,
        'is_active' => 1,
    ]);
    $pendingId = aptPlace([
        'name' => 'APT pending homestay',
        'category_id' => $category,
        'district' => 'Kaski',
        'source' => 'user_submitted',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.places'));

    $response->assertOk()
        ->assertSee('Places command center', false)
        ->assertSee('Directory size', false)
        ->assertSee('Place directory', false)
        ->assertSee('Add a place', false)
        ->assertSee('Import from OpenStreetMap', false)
        ->assertSee('OSM Live (Nepal)', false)
        ->assertSee('Corrections', false)
        ->assertSee('Pending review', false)
        ->assertSee('Manage all categories', false)
        ->assertSee('placesPageData', false)
        ->assertSee('places = currentPlaces();', false)
        ->assertSee('APT verified cafe', false)
        ->assertSee('APT pending homestay', false);

    $insights = $response->viewData('insights');
    expect($insights['counts']['all'])->toBeGreaterThanOrEqual(2)
        ->and($insights['counts']['image_status'])->toHaveKeys([
            'done', 'partial', 'pending', 'running', 'none', 'failed',
        ])
        ->and($insights['trend']['labels'])->toHaveCount(30)
        ->and($insights['kpis'])->toHaveCount(4)
        ->and($insights['categories'])->not->toBeEmpty()
        ->and($insights['districts'])->not->toBeEmpty()
        ->and($insights['sources'])->not->toBeEmpty()
        ->and($response->viewData('canManagePlaces'))->toBeTrue()
        ->and($response->viewData('places')->total())->toBeGreaterThanOrEqual(2)
        ->and($verifiedId)->toBeInt()
        ->and($pendingId)->toBeInt();
});

it('filters places by status, category, district, source, search and per page', function () {
    $admin = aptUser('admin');
    $categoryA = aptCategory();
    $categoryB = aptCategory();

    aptPlace([
        'name' => 'APT active verified',
        'category_id' => $categoryA,
        'district' => 'Kathmandu',
        'source' => 'admin',
        'is_verified' => 1,
        'is_active' => 1,
    ]);
    aptPlace([
        'name' => 'APT featured stay',
        'category_id' => $categoryB,
        'district' => 'Chitwan',
        'source' => 'seed',
        'is_featured' => 1,
        'is_active' => 1,
    ]);
    aptPlace([
        'name' => 'APT pending review',
        'category_id' => $categoryA,
        'district' => 'Kaski',
        'source' => 'user_submitted',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.places', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('APT pending review', false)
        ->assertDontSee('APT active verified', false)
        ->assertDontSee('APT featured stay', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['status' => 'verified']))
        ->assertOk()
        ->assertSee('APT active verified', false)
        ->assertDontSee('APT pending review', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['status' => 'featured']))
        ->assertOk()
        ->assertSee('APT featured stay', false)
        ->assertDontSee('APT active verified', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('APT pending review', false)
        ->assertDontSee('APT active verified', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['search' => 'APT pending review']))
        ->assertOk()
        ->assertSee('APT pending review', false)
        ->assertDontSee('APT active verified', false)
        ->assertDontSee('APT featured stay', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['category_id' => $categoryA]))
        ->assertOk()
        ->assertSee('APT active verified', false)
        ->assertSee('APT pending review', false)
        ->assertDontSee('APT featured stay', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['district' => 'Kaski']))
        ->assertOk()
        ->assertSee('APT pending review', false)
        ->assertDontSee('APT active verified', false);

    $this->actingAs($admin)
        ->get(route('admin.places', ['source' => 'seed']))
        ->assertOk()
        ->assertSee('APT featured stay', false)
        ->assertDontSee('APT active verified', false);

    $response = $this->actingAs($admin)->get(route('admin.places', ['per_page' => 25]));
    expect($response->viewData('places')->perPage())->toBe(25);

    $invalid = $this->actingAs($admin)->get(route('admin.places', ['per_page' => 999]));
    expect($invalid->viewData('places')->perPage())->toBe(15);

    aptPlace(['name' => 'APT sort alpha', 'category_id' => $categoryA]);
    aptPlace(['name' => 'APT sort beta', 'category_id' => $categoryA]);
    aptPlace(['name' => 'APT sort gamma', 'category_id' => $categoryA]);

    $sorted = $this->actingAs($admin)->get(route('admin.places', [
        'search' => 'APT sort',
        'sort' => 'name',
        'direction' => 'asc',
    ]));

    $sorted->assertOk()->assertSee('Name A-Z', false);
    expect(collect($sorted->viewData('places')->items())->pluck('name')->all())
        ->toBe(['APT sort alpha', 'APT sort beta', 'APT sort gamma']);
});

it('bulk updates and deletes selected places with an array of ids', function () {
    $admin = aptUser('admin');
    $category = aptCategory();
    $target = aptCategory();

    $first = aptPlace(['name' => 'APT bulk one', 'category_id' => $category]);
    $second = aptPlace(['name' => 'APT bulk two', 'category_id' => $category]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.bulk-update'), [
            'ids' => [$first, $second],
            'category_id' => $target,
            'is_verified' => '1',
            'is_active' => '1',
            'is_featured' => '1',
        ])
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', '2 places updated');

    $this->assertDatabaseHas('places', [
        'id' => $first,
        'category_id' => $target,
        'is_verified' => 1,
        'is_active' => 1,
        'is_featured' => 1,
    ]);
    $this->assertDatabaseHas('places', [
        'id' => $second,
        'category_id' => $target,
        'is_verified' => 1,
        'is_active' => 1,
        'is_featured' => 1,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.bulk-delete'), ['ids' => [$first, $second]])
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', '2 places deleted');

    $this->assertDatabaseMissing('places', ['id' => $first]);
    $this->assertDatabaseMissing('places', ['id' => $second]);
});

it('approves, features and deletes a place, and rejects another to moderation', function () {
    $admin = aptUser('admin');
    $placeId = aptPlace(['name' => 'APT approve feature delete']);
    $rejectId = aptPlace(['name' => 'APT reject candidate']);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.approve', $placeId))
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place approved and published.');

    $this->assertDatabaseHas('places', [
        'id' => $placeId,
        'is_verified' => 1,
        'is_active' => 1,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.feature', $placeId))
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place featured');

    $this->assertDatabaseHas('places', ['id' => $placeId, 'is_featured' => 1]);

    $response = $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.feature', $placeId));

    $this->assertDatabaseHas('places', ['id' => $placeId, 'is_featured' => 0]);

    $response->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place unfeatured');

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.reject', $rejectId), ['reason' => 'APT rejection reason'])
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place rejected and removed.');

    $this->assertDatabaseMissing('places', ['id' => $rejectId]);
    $this->assertDatabaseHas('moderation_queues', [
        'content_type' => 'place',
        'content_id' => $rejectId,
        'status' => 'rejected',
        'rejection_reason' => 'APT rejection reason',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.delete', $placeId))
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place deleted');

    $this->assertDatabaseMissing('places', ['id' => $placeId]);
});

it('updates a place and can uncheck verified and active', function () {
    $admin = aptUser('admin');
    $category = aptCategory();
    $placeId = aptPlace([
        'name' => 'APT editable place',
        'category_id' => $category,
        'is_verified' => 1,
        'is_active' => 1,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.update', $placeId), [
            'name' => 'APT editable place renamed',
            'category_id' => $category,
            'latitude' => 27.71,
            'longitude' => 85.31,
            // The edit form posts a hidden 0 before each checkbox so an
            // unchecked box still submits an explicit false value.
            'is_verified' => '0',
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Place updated');

    $this->assertDatabaseHas('places', [
        'id' => $placeId,
        'name' => 'APT editable place renamed',
        'is_verified' => 0,
        'is_active' => 0,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.update', $placeId), [
            'name' => 'APT editable place renamed',
            'category_id' => $category,
            'latitude' => 27.71,
            'longitude' => 85.31,
            'is_verified' => '1',
            'is_active' => '1',
        ])
        ->assertSessionHas('success', 'Place updated');

    $this->assertDatabaseHas('places', [
        'id' => $placeId,
        'is_verified' => 1,
        'is_active' => 1,
    ]);
});

it('creates, renames and deletes an empty place category', function () {
    $admin = aptUser('admin');
    $name = 'APT Cat CRUD '.Str::random(6);

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.categories'), ['name' => $name, 'icon' => 'fa-tag'])
        ->assertSessionHas('success', 'Category created');

    $categoryId = DB::table('place_categories')->where('name', $name)->value('id');
    expect($categoryId)->not->toBeNull();

    $response = $this->actingAs($admin)
        ->from(route('admin.places'))
        ->put(route('admin.places.categories'), [
            'id' => $categoryId,
            'name' => $name.' renamed',
            'icon' => 'fa-tags',
        ]);

    $this->assertDatabaseHas('place_categories', [
        'id' => $categoryId,
        'name' => $name.' renamed',
        'icon' => 'fa-tags',
    ]);

    $response->assertSessionHas('success', 'Category updated');

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->delete(route('admin.places.categories'), ['id' => $categoryId])
        ->assertSessionHas('success', 'Category deleted');

    $this->assertDatabaseMissing('place_categories', ['id' => $categoryId]);
});

it('lists and deletes place reviews for a place', function () {
    $admin = aptUser('admin');
    $placeId = aptPlace(['name' => 'APT review place']);
    $reviewId = DB::table('place_reviews')->insertGetId([
        'uuid' => Str::uuid()->toString(),
        'place_id' => $placeId,
        'user_id' => $admin->id,
        'title' => 'APT review title',
        'description' => 'APT review description.',
        'rating' => 4,
        'moderation_status' => 'approved',
        'helpful_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.places.reviews', $placeId), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('place.name', 'APT review place')
        ->assertJsonPath('reviews.0.title', 'APT review title');

    $this->actingAs($admin)
        ->from(route('admin.places'))
        ->post(route('admin.places.reviews.delete', $reviewId))
        ->assertRedirect(route('admin.places'))
        ->assertSessionHas('success', 'Review deleted');

    $this->assertDatabaseMissing('place_reviews', ['id' => $reviewId]);
});

it('lets moderators use the places command center and blocks regular users', function () {
    $moderator = aptUser('moderator');
    aptPlace(['name' => 'APT moderator can see']);

    $this->actingAs($moderator)
        ->get(route('admin.places'))
        ->assertOk()
        ->assertSee('Places command center', false)
        ->assertSee('APT moderator can see', false)
        ->assertSee('Add a place', false)
        ->assertSee('Approve and publish', false);

    $user = aptUser('user');

    $this->actingAs($user)
        ->get(route('admin.places'))
        ->assertForbidden();
});
