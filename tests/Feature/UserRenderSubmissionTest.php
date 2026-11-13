<?php

use App\Filament\Resources\UserRenders\Pages\ListUserRenders;
use App\Models\User;
use App\Models\UserRender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('The GD extension is required to build test images.');
    }
    Storage::fake('local');
    Storage::fake('public');
    RateLimiter::clear('user-render-uploads:127.0.0.1');
});

function pngBytes(int $width = 64, int $height = 48): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 162, 151, 120));
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function jpegWithExif(string $marker): string
{
    $image = imagecreatetruecolor(32, 32);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();
    $payload = "Exif\0\0".$marker;

    // APP1 segment right after the start-of-image marker, where cameras put EXIF
    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
}

function submission(array $overrides = [], ?UploadedFile $image = null): array
{
    return array_merge([
        'name' => 'My Shuttle',
        'description' => 'Test render',
        'image' => $image ?? UploadedFile::fake()->createWithContent('render.png', pngBytes()),
        'form_token' => encrypt(now()->subSeconds(30)->timestamp),
        'website' => '',
    ], $overrides);
}

function pendingRender(array $attributes = []): UserRender
{
    $path = 'user-renders/'.Str::random(10).'.webp';
    Storage::disk('local')->put($path, 'image');

    return UserRender::create(array_merge([
        'name' => 'Existing',
        'image' => $path,
        'size' => 5,
        'status' => 'pending',
    ], $attributes));
}

it('stores a re-encoded image privately and shows it through the secret link', function () {
    $this->post('/user-renders', submission())->assertRedirect();

    $render = UserRender::sole();
    expect($render->status)->toBe('pending')
        ->and($render->image)->toEndWith('.webp')
        ->and($render->size)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($render->image);
    Storage::disk('public')->assertMissing($render->image);

    $this->get(route('user-renders.show', $render->slug))->assertOk()
        ->assertSee(route('user-renders.image', $render->slug), false)
        ->assertDontSee('/storage/'.$render->image, false);

    $image = $this->get(route('user-renders.image', $render->slug))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(imagecreatefromstring($image->streamedContent()))->not->toBeFalse();
});

it('keeps only pixels: appended data and EXIF are dropped', function () {
    $polyglot = UploadedFile::fake()->createWithContent('render.png', pngBytes().'<?php echo "POLYGLOT"; ?>');
    $this->post('/user-renders', submission([], $polyglot))->assertRedirect();

    $photo = UploadedFile::fake()->createWithContent('photo.jpg', jpegWithExif('GPS-MARKER-12345'));
    $this->post('/user-renders', submission(['name' => 'Photo'], $photo))->assertRedirect();

    foreach (UserRender::all() as $render) {
        $bytes = Storage::disk('local')->get($render->image);
        expect($bytes)->not->toContain('POLYGLOT')
            ->and($bytes)->not->toContain('GPS-MARKER')
            ->and(substr($bytes, 8, 4))->toBe('WEBP')
            ->and(imagecreatefromstring($bytes))->not->toBeFalse();
    }
});

it('rejects files that are not images or are too large', function () {
    $fake = UploadedFile::fake()->createWithContent('render.png', 'not an image at all');
    $this->post('/user-renders', submission([], $fake))->assertSessionHasErrors('image');

    config(['user-renders.max_side' => 100]);
    $big = UploadedFile::fake()->createWithContent('render.png', pngBytes(101, 10));
    $this->post('/user-renders', submission([], $big))->assertSessionHasErrors('image');

    expect(UserRender::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects automated submissions without storing anything', function (array $overrides) {
    $this->post('/user-renders', submission($overrides))->assertSessionHasErrors('form');

    expect(UserRender::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'honeypot filled' => [fn () => ['website' => 'https://spam.example']],
    'sent right after opening' => [fn () => ['form_token' => encrypt(now()->timestamp)]],
    'no form token' => [fn () => ['form_token' => 'forged']],
]);

it('accepts five uploads per hour from one address', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/user-renders', submission(['name' => "Render {$i}"]))->assertSessionHasNoErrors();
    }

    $this->post('/user-renders', submission(['name' => 'Sixth']))->assertSessionHasErrors('image');
    expect(UserRender::count())->toBe(5);

    $this->travel(61)->minutes();
    $this->post('/user-renders', submission(['name' => 'Next hour']))->assertSessionHasNoErrors();
});

it('answers 429 to a flood of attempts', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->post('/user-renders', submission(['website' => 'bot']));
    }

    $this->post('/user-renders', submission())->assertStatus(429);
    expect(UserRender::count())->toBe(0);
});

it('closes uploads while the moderation queue is over its quota', function () {
    config(['user-renders.pending_quota_mb' => 1]);
    pendingRender(['size' => 1024 * 1024]);

    $this->post('/user-renders', submission())->assertSessionHasErrors('image');
    expect(UserRender::count())->toBe(1);
});

it('publishes the file on approval and hides it again on rejection', function () {
    $render = pendingRender();

    $render->update(['status' => 'approved']);
    Storage::disk('public')->assertExists($render->image);
    Storage::disk('local')->assertMissing($render->image);
    expect($render->imageUrl())->toBe(Storage::disk('public')->url($render->image));

    $render->update(['status' => 'rejected']);
    Storage::disk('local')->assertExists($render->image);
    Storage::disk('public')->assertMissing($render->image);
    expect($render->rejected_at)->not->toBeNull()
        ->and($render->imageUrl())->toBeNull();

    $this->get(route('user-renders.image', $render->slug))->assertNotFound();
    $this->get(route('user-renders.show', $render->slug))->assertOk()->assertSee('Rejected');

    $this->actingAs(User::factory()->create())
        ->get(route('user-renders.image', $render->slug))->assertOk();
});

it('moves files from the admin approve and reject actions', function () {
    $this->actingAs(User::factory()->create());
    $approved = pendingRender();
    $rejected = pendingRender();

    Livewire::test(ListUserRenders::class)
        ->callTableAction('approve', $approved)
        ->callTableAction('reject', $rejected);

    Storage::disk('public')->assertExists($approved->image);
    Storage::disk('local')->assertExists($rejected->image);
    expect($approved->fresh()->status)->toBe('approved')
        ->and($rejected->fresh()->status)->toBe('rejected');
});

it('removes images of renders rejected more than a week ago and relocates misplaced files', function () {
    $old = pendingRender();
    $old->update(['status' => 'rejected']);
    $old->forceFill(['rejected_at' => now()->subDays(8)])->saveQuietly();

    $recent = pendingRender();
    $recent->update(['status' => 'rejected']);

    // a file left on the public disk by the old upload code
    $legacy = pendingRender();
    Storage::disk('local')->move($legacy->image, 'tmp');
    Storage::disk('public')->put($legacy->image, Storage::disk('local')->get('tmp'));
    $legacy->forceFill(['size' => null])->saveQuietly();

    $this->artisan('user-renders:cleanup')->assertSuccessful();

    $oldPath = $old->image;
    expect($old->fresh()->image)->toBeNull();
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($recent->image);
    Storage::disk('public')->assertMissing($legacy->image);
    Storage::disk('local')->assertExists($legacy->image);
    expect($legacy->fresh()->size)->toBe(5);

    $this->get(route('user-renders.show', $old->slug))->assertOk()->assertSee('Rejected');
});

it('deletes the file together with the render', function () {
    $render = pendingRender();
    $render->delete();

    Storage::disk('local')->assertMissing($render->image);
});
