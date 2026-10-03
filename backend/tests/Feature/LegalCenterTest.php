<?php

use App\Models\AuditLog;
use App\Models\IdempotencyKey;
use App\Models\LegalDocument;
use App\Models\LegalDocumentType;
use App\Models\Role;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Legal Center helpers (unique names — do not clash with the global
| createTestUser / createUserToken / uniqueEmail helpers defined in
| AccountDeletionLegalAcceptanceTest.php)
|--------------------------------------------------------------------------
*/

function lcCreateUser(array $overrides = []): User
{
    $defaults = [
        'name' => 'LC Test User',
        'email' => 'lc_'.Str::random(8).'@example.com',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'status' => 'active',
        'is_active' => 1,
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
        'total_xp' => 100,
        'current_level' => 3,
    ];

    return User::create(array_merge($defaults, $overrides));
}

function lcAdminUser(): User
{
    $user = lcCreateUser();
    $adminRoleId = Role::where('name', 'admin')->value('id');
    User::whereKey($user->id)->update(['role_id' => $adminRoleId]);

    return $user->fresh();
}

function lcMakeDoc(array $overrides = []): LegalDocument
{
    return LegalDocument::create(array_merge([
        'type' => 'lc-test-type',
        'slug' => 'lc-doc',
        'title' => 'LC Test Document',
        'short_description' => 'LC test short description.',
        'content' => '<h2>Alpha Section</h2><p>Real legal content for testing purposes.</p><h2>Beta Section</h2><p>More real content.</p>',
        'version' => 'lc-'.Str::random(6),
        'status' => LegalDocument::STATUS_DRAFT,
    ], $overrides));
}

function lcEnsureTestType(): void
{
    LegalDocumentType::firstOrCreate(
        ['slug' => 'lc-test-type'],
        ['label' => 'LC Test Type', 'is_active' => true, 'sort_order' => 99]
    );
}

afterEach(function () {
    LegalDocument::query()
        ->where('slug', 'like', 'lc-%')
        ->orWhere('type', 'lc-test-type')
        ->delete();

    LegalDocumentType::where('slug', 'lc-test-type')->delete();

    AuditLog::where('resource_type', 'legal_document')
        ->where('description', 'like', '%lc-%')
        ->delete();

    // Remove test users and their idempotency keys (the auto-generated
    // idempotency key includes the user id, so keys must die with users).
    $userIds = User::where('email', 'like', 'lc\_%@example.com')->pluck('id');
    if ($userIds->isNotEmpty()) {
        IdempotencyKey::whereIn('user_id', $userIds)->delete();
        User::whereIn('id', $userIds)->delete();
    }
});

/*
|--------------------------------------------------------------------------
| Public Legal Center
|--------------------------------------------------------------------------
*/

it('serves the Legal Center index', function () {
    $this->get('/legal')
        ->assertOk()
        ->assertSee('LEGAL CENTER')
        ->assertSee('rel="canonical"', false);
});

it('lists only published documents on the index', function () {
    lcMakeDoc(['slug' => 'lc-published-card', 'title' => 'LC Published Card Title', 'status' => LegalDocument::STATUS_PUBLISHED, 'published_at' => now()]);
    lcMakeDoc(['slug' => 'lc-draft-card', 'title' => 'LC Draft Card Title', 'status' => LegalDocument::STATUS_DRAFT]);

    $this->get('/legal')
        ->assertOk()
        ->assertSee('LC Published Card Title')
        ->assertDontSee('LC Draft Card Title');
});

it('serves a published document page with SEO tags', function () {
    lcMakeDoc(['slug' => 'lc-show', 'title' => 'LC Show Title', 'status' => LegalDocument::STATUS_PUBLISHED, 'published_at' => now()]);

    $this->get('/legal/lc-show')
        ->assertOk()
        ->assertSee('LC Show Title')
        ->assertSee('rel="canonical"', false)
        ->assertSee('name="description"', false);
});

it('builds a table of contents from document headings', function () {
    lcMakeDoc(['slug' => 'lc-toc', 'status' => LegalDocument::STATUS_PUBLISHED, 'published_at' => now()]);

    $this->get('/legal/lc-toc')
        ->assertOk()
        ->assertSee('Table of contents')
        ->assertSee('id="alpha-section"', false)
        ->assertSee('id="beta-section"', false);
});

it('returns 404 for draft documents', function () {
    lcMakeDoc(['slug' => 'lc-hidden-draft', 'status' => LegalDocument::STATUS_DRAFT]);

    $this->get('/legal/lc-hidden-draft')->assertNotFound();
});

it('returns 404 for archived documents', function () {
    lcMakeDoc(['slug' => 'lc-hidden-archived', 'status' => LegalDocument::STATUS_ARCHIVED]);

    $this->get('/legal/lc-hidden-archived')->assertNotFound();
});

it('returns 404 for unknown slugs', function () {
    $this->get('/legal/definitely-not-a-real-document')->assertNotFound();
});

it('resolves legacy type identifiers', function () {
    $this->get('/legal/privacy_policy')->assertOk();
    $this->get('/legal/terms_conditions')->assertOk();
});

it('serves legacy /terms and /privacy-policy routes', function () {
    $this->get('/terms')->assertOk();
    $this->get('/privacy-policy')->assertOk();
});

it('sanitizes dangerous HTML when rendering', function () {
    lcMakeDoc([
        'slug' => 'lc-xss',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
        'content' => '<h2>Safe Heading</h2><script>alert("xss")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">bad</a><p>Visible text</p>',
    ]);

    $response = $this->get('/legal/lc-xss');

    $response->assertOk();
    $response->assertSee('Safe Heading');
    $response->assertSee('Visible text');
    // The shared landing layout ships its own <script>, so scope script
    // assertions to the payload instead of the whole page.
    $response->assertDontSee('alert("xss")', false);
    $response->assertDontSee('src="x"', false);
    $response->assertDontSee('onerror="alert(1)"', false);
    $response->assertDontSee('javascript:', false);
});

/*
|--------------------------------------------------------------------------
| Admin authorization
|--------------------------------------------------------------------------
*/

it('redirects guests away from admin legal documents', function () {
    $this->get(route('admin.legal-documents.index'))->assertRedirect();
});

it('forbids regular users from the admin legal documents', function () {
    $this->actingAs(lcCreateUser())
        ->get(route('admin.legal-documents.index'))
        ->assertForbidden();
});

it('allows admins to view the legal documents index', function () {
    $this->actingAs(lcAdminUser())
        ->get(route('admin.legal-documents.index'))
        ->assertOk();
});

it('forbids non-admins from previewing drafts', function () {
    $doc = lcMakeDoc(['slug' => 'lc-preview-guard']);

    $this->actingAs(lcCreateUser())
        ->get(route('admin.legal-documents.preview', $doc->id))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Admin workflow: create, update, publish, archive, delete
|--------------------------------------------------------------------------
*/

it('creates a draft with sanitized content', function () {
    lcEnsureTestType();

    $response = $this->actingAs(lcAdminUser())->post(route('admin.legal-documents.store'), [
        'type' => 'lc-test-type',
        'slug' => 'lc-created',
        'title' => 'LC Created Document',
        'short_description' => 'Created via test.',
        'content' => '<p>Hello</p><script>alert(1)</script><p onmouseover="steal()">World</p>',
        'version' => '1.0',
    ]);

    $response->assertRedirect();

    $doc = LegalDocument::where('slug', 'lc-created')->first();
    expect($doc)->not->toBeNull();
    expect($doc->status)->toBe(LegalDocument::STATUS_DRAFT);
    expect($doc->is_published)->toBeFalse();
    expect($doc->content)->toContain('<p>Hello</p>');
    expect($doc->content)->not->toContain('<script');
    expect($doc->content)->not->toContain('onmouseover');
});

it('rejects invalid slug formats on create', function () {
    lcEnsureTestType();

    $this->actingAs(lcAdminUser())
        ->post(route('admin.legal-documents.store'), [
            'type' => 'lc-test-type',
            'slug' => 'Invalid Slug!',
            'title' => 'LC Invalid Slug',
            'content' => '<p>Content</p>',
        ])
        ->assertSessionHasErrors('slug');
});

it('allows only one document per type', function () {
    lcEnsureTestType();
    lcMakeDoc(['type' => 'lc-test-type', 'slug' => 'lc-already-exists']);

    $this->actingAs(lcAdminUser())
        ->post(route('admin.legal-documents.store'), [
            'type' => 'lc-test-type',
            'slug' => 'lc-second-doc',
            'title' => 'LC Second Doc',
            'content' => '<p>Content</p>',
        ])
        ->assertSessionHasErrors('type');
});

it('refuses to update a published version directly', function () {
    $doc = lcMakeDoc(['slug' => 'lc-published-immutable', 'status' => LegalDocument::STATUS_PUBLISHED, 'published_at' => now()]);

    $this->actingAs(lcAdminUser())
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => 'LC Hacked Title',
            'content' => '<p>Changed</p>',
        ])
        ->assertForbidden();
});

it('forks a draft when editing a published document', function () {
    $admin = lcAdminUser();
    $published = lcMakeDoc([
        'slug' => 'lc-fork',
        'title' => 'LC Fork Source',
        'version' => '2.0',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.legal-documents.edit', $published->id));
    $response->assertRedirect();

    $draft = LegalDocument::where('slug', 'lc-fork')
        ->where('status', LegalDocument::STATUS_DRAFT)
        ->first();

    expect($draft)->not->toBeNull();
    expect($draft->id)->not->toBe($published->id);
    expect($draft->version)->toBe('2.1');
    expect($published->fresh()->status)->toBe(LegalDocument::STATUS_PUBLISHED);

    $this->actingAs($admin)->get($response->headers->get('Location'))->assertOk();
});

it('publishes a draft and archives the previous version', function () {
    $admin = lcAdminUser();
    $old = lcMakeDoc([
        'slug' => 'lc-pub-flow',
        'title' => 'LC Old Version',
        'version' => '1.0',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $new = lcMakeDoc([
        'slug' => 'lc-pub-flow',
        'title' => 'LC New Version',
        'version' => '1.1',
        'status' => LegalDocument::STATUS_DRAFT,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.legal-documents.publish', $new->id))
        ->assertSessionHas('success');

    expect($new->fresh()->status)->toBe(LegalDocument::STATUS_PUBLISHED);
    expect($old->fresh()->status)->toBe(LegalDocument::STATUS_ARCHIVED);
    expect($old->fresh()->is_published)->toBeFalse();

    $this->get('/legal/lc-pub-flow')
        ->assertOk()
        ->assertSee('LC New Version')
        ->assertDontSee('LC Old Version');
});

it('blocks publishing placeholder-only content', function () {
    $doc = lcMakeDoc([
        'slug' => 'lc-placeholder',
        'content' => LegalDocument::PLACEHOLDER,
        'status' => LegalDocument::STATUS_DRAFT,
    ]);

    $this->actingAs(lcAdminUser())
        ->post(route('admin.legal-documents.publish', $doc->id))
        ->assertSessionHas('error');

    expect($doc->fresh()->status)->toBe(LegalDocument::STATUS_DRAFT);
});

it('hides archived documents from the public', function () {
    $admin = lcAdminUser();
    $doc = lcMakeDoc([
        'slug' => 'lc-archive-flow',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    $this->get('/legal/lc-archive-flow')->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.legal-documents.archive', $doc->id))
        ->assertSessionHas('success');

    expect($doc->fresh()->status)->toBe(LegalDocument::STATUS_ARCHIVED);
    $this->get('/legal/lc-archive-flow')->assertNotFound();
});

it('unpublishes back to draft and hides the document', function () {
    $admin = lcAdminUser();
    $doc = lcMakeDoc([
        'slug' => 'lc-unpublish-flow',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    $this->get('/legal/lc-unpublish-flow')->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.legal-documents.unpublish', $doc->id))
        ->assertSessionHas('success');

    expect($doc->fresh()->status)->toBe(LegalDocument::STATUS_DRAFT);
    $this->get('/legal/lc-unpublish-flow')->assertNotFound();
});

it('deletes draft versions', function () {
    $draft = lcMakeDoc(['slug' => 'lc-deletable']);

    $this->actingAs(lcAdminUser())
        ->post(route('admin.legal-documents.delete', $draft->id))
        ->assertSessionHas('success');

    expect(LegalDocument::find($draft->id))->toBeNull();
});

// NOTE: kept as a separate test (not paired with draft deletion) because the
// web IdempotencyMiddleware auto-keys on user+route+body — two empty POSTs to
// the same route from one user would replay the first response.
it('protects published versions from deletion', function () {
    $published = lcMakeDoc([
        'slug' => 'lc-protected',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    $this->actingAs(lcAdminUser())
        ->post(route('admin.legal-documents.delete', $published->id))
        ->assertSessionHas('error');

    expect(LegalDocument::find($published->id))->not->toBeNull();
});

it('shows version history for a document', function () {
    $admin = lcAdminUser();
    $v1 = lcMakeDoc([
        'slug' => 'lc-versions',
        'version' => '1.0',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $v2 = lcMakeDoc(['slug' => 'lc-versions', 'version' => '1.1']);

    $this->actingAs($admin)
        ->get(route('admin.legal-documents.versions', $v2->id))
        ->assertOk()
        ->assertSee('LC Test Document')
        ->assertSee('1.0')
        ->assertSee('1.1');
});

it('previews drafts as an admin', function () {
    $doc = lcMakeDoc(['slug' => 'lc-preview', 'title' => 'LC Preview Title']);

    $this->actingAs(lcAdminUser())
        ->get(route('admin.legal-documents.preview', $doc->id))
        ->assertOk()
        ->assertSee('LC Preview Title');
});

it('updates a draft through the admin form', function () {
    $doc = lcMakeDoc(['slug' => 'lc-updatable', 'title' => 'LC Before Update']);

    $this->actingAs(lcAdminUser())
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => 'LC After Update',
            'short_description' => 'Updated summary.',
            'content' => '<p>Updated <strong>content</strong>.</p>',
            'version' => '1.0',
        ])
        ->assertSessionHas('success');

    $doc->refresh();
    expect($doc->title)->toBe('LC After Update');
    expect($doc->short_description)->toBe('Updated summary.');
    expect($doc->status)->toBe(LegalDocument::STATUS_DRAFT);
});

it('saves edits and publishes in one step when the edit form action is publish', function () {
    $doc = lcMakeDoc(['slug' => 'lc-one-step', 'title' => 'LC Before Publish']);

    $this->actingAs(lcAdminUser())
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => 'LC Live Now',
            'short_description' => 'Published in one step.',
            'content' => '<p>One step live content.</p>',
            'version' => '1.0',
            'action' => 'publish',
        ])
        ->assertRedirect(route('admin.legal-documents.index'));

    $doc->refresh();
    expect($doc->status)->toBe(LegalDocument::STATUS_PUBLISHED);
    expect($doc->title)->toBe('LC Live Now');

    $this->get('/legal/lc-one-step')
        ->assertOk()
        ->assertSee('LC Live Now');
});

/*
|--------------------------------------------------------------------------
| Sanitizer
|--------------------------------------------------------------------------
*/

it('strips scripts, event handlers and unsafe link schemes', function () {
    $clean = HtmlSanitizer::clean(
        '<p>ok</p>'
        .'<script>alert(1)</script>'
        .'<a href="javascript:alert(1)">bad</a>'
        .'<img src="x" onerror="alert(1)">'
        .'<a href="https://example.com">good</a>'
        .'<h2>Heading</h2>'
        .'<ul><li>item</li></ul>'
    );

    expect($clean)->toContain('ok');
    expect($clean)->not->toContain('<script');
    expect($clean)->not->toContain('javascript:');
    expect($clean)->not->toContain('<img');
    expect($clean)->toContain('https://example.com');
    expect($clean)->toContain('<h2>Heading</h2>');
    expect($clean)->toContain('<li>item</li>');
});

it('returns an empty string for empty input', function () {
    expect(HtmlSanitizer::clean(null))->toBe('');
    expect(HtmlSanitizer::clean(''))->toBe('');
    expect(HtmlSanitizer::clean('   '))->toBe('');
});

it('keeps safe link schemes and relative paths while rejecting dangerous ones', function () {
    $clean = HtmlSanitizer::clean(
        '<a href="/legal/privacy">rel</a>'
        .'<a href="mailto:support@example.com">mail</a>'
        .'<a href="http://example.com">http</a>'
        .'<a href="data:text/html,x">data</a>'
        .'<a href="vbscript:msgbox(1)">vb</a>'
    );

    expect($clean)->toContain('href="/legal/privacy"');
    expect($clean)->toContain('mailto:support@example.com');
    expect($clean)->toContain('http://example.com');
    expect($clean)->not->toContain('data:text/html');
    expect($clean)->not->toContain('vbscript:');
});

/*
|--------------------------------------------------------------------------
| Reference URL (admin-managed external/reference link)
|--------------------------------------------------------------------------
*/

it('validates reference url schemes with isSafeUrl', function () {
    expect(HtmlSanitizer::isSafeUrl('https://example.com/path'))->toBeTrue();
    expect(HtmlSanitizer::isSafeUrl('http://example.com'))->toBeTrue();
    expect(HtmlSanitizer::isSafeUrl('mailto:a@example.com'))->toBeTrue();
    expect(HtmlSanitizer::isSafeUrl('/legal/terms'))->toBeTrue();

    expect(HtmlSanitizer::isSafeUrl('javascript:alert(1)'))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl('JavaScript:alert(1)'))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl('data:text/html,x'))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl('vbscript:msgbox(1)'))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl('//evil.example.com'))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl(null))->toBeFalse();
    expect(HtmlSanitizer::isSafeUrl(''))->toBeFalse();
});

it('stores a safe https reference url when creating a draft', function () {
    lcEnsureTestType();

    $this->actingAs(lcAdminUser())->post(route('admin.legal-documents.store'), [
        'type' => 'lc-test-type',
        'slug' => 'lc-ref-create',
        'title' => 'LC Ref Create',
        'content' => '<p>Content</p>',
        'reference_url' => 'https://example.com/external-ref',
    ])->assertRedirect();

    $doc = LegalDocument::where('slug', 'lc-ref-create')->first();
    expect($doc)->not->toBeNull();
    expect($doc->reference_url)->toBe('https://example.com/external-ref');
});

it('accepts mailto and site-relative reference urls on update', function () {
    $doc = lcMakeDoc(['slug' => 'lc-ref-update']);
    $admin = lcAdminUser();

    // Distinct _idempotency_key values: in feature tests the auto key hashes
    // an empty raw body, so two PUTs from one user to one route would replay.
    $this->actingAs($admin)
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => $doc->title,
            'content' => $doc->content,
            'reference_url' => 'mailto:support@example.com',
            '_idempotency_key' => 'lc-ref-update-mailto',
        ])
        ->assertSessionHas('success');

    expect($doc->fresh()->reference_url)->toBe('mailto:support@example.com');

    $this->actingAs($admin)
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => $doc->title,
            'content' => $doc->content,
            'reference_url' => '/legal/privacy',
            '_idempotency_key' => 'lc-ref-update-relative',
        ])
        ->assertSessionHas('success');

    expect($doc->fresh()->reference_url)->toBe('/legal/privacy');
});

it('rejects dangerous reference url schemes', function () {
    $doc = lcMakeDoc(['slug' => 'lc-ref-danger']);
    $admin = lcAdminUser();

    $cases = [
        'lc-danger-js' => 'javascript:alert(1)',
        'lc-danger-data' => 'data:text/html,x',
        'lc-danger-vb' => 'vbscript:msgbox(1)',
    ];

    foreach ($cases as $key => $bad) {
        $this->actingAs($admin)
            ->put(route('admin.legal-documents.update', $doc->id), [
                'title' => $doc->title,
                'content' => $doc->content,
                'reference_url' => $bad,
                '_idempotency_key' => $key,
            ])
            ->assertSessionHasErrors('reference_url');
    }

    expect($doc->fresh()->reference_url)->toBeNull();
});

it('rejects protocol-relative reference urls', function () {
    $doc = lcMakeDoc(['slug' => 'lc-ref-protocol']);

    $this->actingAs(lcAdminUser())
        ->put(route('admin.legal-documents.update', $doc->id), [
            'title' => $doc->title,
            'content' => $doc->content,
            'reference_url' => '//evil.example.com/phish',
        ])
        ->assertSessionHasErrors('reference_url');

    expect($doc->fresh()->reference_url)->toBeNull();
});

it('renders a safe reference link on the public document page', function () {
    lcMakeDoc([
        'slug' => 'lc-ref-render',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
        'reference_url' => 'https://example.com/official-ref',
    ]);

    $this->get('/legal/lc-ref-render')
        ->assertOk()
        ->assertSee('href="https://example.com/official-ref"', false);
});

it('does not render an unsafe stored reference url', function () {
    lcMakeDoc([
        'slug' => 'lc-ref-unsafe',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
        'reference_url' => 'javascript:alert(1)',
    ]);

    $response = $this->get('/legal/lc-ref-unsafe');

    $response->assertOk();
    $response->assertDontSee('javascript:', false);
});

it('copies the reference url when forking a draft from a published document', function () {
    $admin = lcAdminUser();
    lcMakeDoc([
        'slug' => 'lc-ref-fork',
        'version' => '3.0',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
        'reference_url' => 'https://example.com/fork-ref',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.legal-documents.edit', LegalDocument::where('slug', 'lc-ref-fork')->value('id')))
        ->assertRedirect();

    $draft = LegalDocument::where('slug', 'lc-ref-fork')
        ->where('status', LegalDocument::STATUS_DRAFT)
        ->first();

    expect($draft)->not->toBeNull();
    expect($draft->reference_url)->toBe('https://example.com/fork-ref');
});

/*
|--------------------------------------------------------------------------
| Landing-page shell integration (shared navbar + footer)
|--------------------------------------------------------------------------
*/

it('renders legal pages inside the shared landing-page shell', function () {
    lcMakeDoc([
        'slug' => 'lc-shell',
        'status' => LegalDocument::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    foreach (['/legal', '/legal/lc-shell'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertSee('class="site-header"', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee('images/oripori_logo.svg', false)
            ->assertSee('images/oripori_logo_wordmark.png', false)
            ->assertDontSee('images/oripori_logo.png', false)
            ->assertDontSee('k-nav', false);
    }
});

it('links the landing and shared footers to the canonical legal urls', function () {
    $terms = route('web.legal.show', 'terms');
    $privacy = route('web.legal.show', 'privacy');

    $this->get('/')
        ->assertOk()
        ->assertSee($terms, false)
        ->assertSee($privacy, false);
});
