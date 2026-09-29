<?php

namespace Tests\Feature\Services\Domain\Badge;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Badge\PersonPhotoService;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Imagick;
use ReflectionMethod;
use Tests\TestCase;

class PersonPhotoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PersonPhotoService $service;

    private int $accountId;

    private int $personId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PersonPhotoService::class);

        $user = User::factory()->withAccount()->create();
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->personId = $this->makePerson();
    }

    public function test_a_photo_is_stored_privately(): void
    {
        $imageId = $this->service->capture($this->personId, $this->photo());

        $path = DB::table('images')->where('id', $imageId)->value('path');

        $this->assertSame(
            'private',
            $this->disk()->getVisibility($path),
            'A photograph of a face must not be publicly readable.'
        );
    }

    public function test_the_stored_filename_does_not_leak_the_uploaded_name(): void
    {
        $imageId = $this->service->capture(
            $this->personId,
            $this->photo(name: 'ibrahim-passport-scan.jpg')
        );

        $path = (string) DB::table('images')->where('id', $imageId)->value('path');

        $this->assertStringNotContainsString(
            'passport',
            $path,
            'An uploaded filename can carry the person\'s own name.'
        );
        $this->assertStringNotContainsString('ibrahim', $path);
    }

    public function test_exif_is_stripped(): void
    {
        $imageId = $this->service->capture($this->personId, $this->photoWithExif());

        $stored = new Imagick;
        $stored->readImageBlob($this->contentsOf($imageId));

        $this->assertFalse(
            (bool) $stored->getImageProperty('exif:GPSLatitude'),
            'A phone photo carries the coordinates of where it was taken.'
        );
        $this->assertFalse((bool) $stored->getImageProperty('exif:Make'));
    }

    public function test_an_oversized_photo_is_bounded(): void
    {
        $imageId = $this->service->capture($this->personId, $this->photo(width: 4000, height: 3000));

        $stored = new Imagick;
        $stored->readImageBlob($this->contentsOf($imageId));

        $this->assertLessThanOrEqual(1200, $stored->getImageWidth());
        $this->assertLessThanOrEqual(1200, $stored->getImageHeight());
    }

    public function test_the_person_points_at_the_photo(): void
    {
        $imageId = $this->service->capture($this->personId, $this->photo());

        $this->assertSame(
            $imageId,
            (int) DB::table('persons')->where('id', $this->personId)->value('photo_image_id')
        );
    }

    public function test_replacing_a_photo_removes_the_previous_file(): void
    {
        $first = $this->service->capture($this->personId, $this->photo());
        $firstPath = (string) DB::table('images')->where('id', $first)->value('path');

        $second = $this->service->capture($this->personId, $this->photo());

        $this->assertNotSame($first, $second);
        $this->assertFalse(
            $this->disk()->exists($firstPath),
            'Faces should not accumulate in storage after nothing points at them.'
        );
        $this->assertSame(0, DB::table('images')->where('id', $first)->count());
    }

    public function test_removing_a_photo_deletes_the_file_and_clears_the_person(): void
    {
        $imageId = $this->service->capture($this->personId, $this->photo());
        $path = (string) DB::table('images')->where('id', $imageId)->value('path');

        $this->service->remove($this->personId);

        $this->assertFalse($this->disk()->exists($path));
        $this->assertNull(DB::table('persons')->where('id', $this->personId)->value('photo_image_id'));
    }

    public function test_removing_a_photo_that_does_not_exist_is_refused(): void
    {
        $this->expectException(ResourceConflictException::class);

        $this->service->remove($this->personId);
    }

    public function test_capturing_for_an_unknown_person_is_refused(): void
    {
        $this->expectExceptionMessage('The person could not be found.');

        $this->service->capture(99999999, $this->photo());
    }

    /**
     * Rotation is asserted against the logic directly. A synthetic JPEG does not reliably
     * carry an EXIF orientation tag through Imagick, so a fixture-based test here would be
     * measuring the fixture rather than the behaviour.
     */
    public function test_a_phone_portrait_is_rotated_upright(): void
    {
        $method = new ReflectionMethod($this->service, 'applyOrientation');

        $cases = [
            [Imagick::ORIENTATION_TOPLEFT, 400, 300],
            [Imagick::ORIENTATION_RIGHTTOP, 300, 400],
            [Imagick::ORIENTATION_BOTTOMRIGHT, 400, 300],
            [Imagick::ORIENTATION_LEFTBOTTOM, 300, 400],
        ];

        foreach ($cases as [$orientation, $expectedWidth, $expectedHeight]) {
            $image = new Imagick;
            $image->newImage(400, 300, new \ImagickPixel('red'));
            $image->setImageFormat('jpeg');

            $method->invoke($this->service, $image, $orientation);

            $this->assertSame($expectedWidth, $image->getImageWidth(), 'orientation '.$orientation);
            $this->assertSame($expectedHeight, $image->getImageHeight(), 'orientation '.$orientation);
            $this->assertSame(Imagick::ORIENTATION_TOPLEFT, $image->getImageOrientation());
        }
    }

    public function test_a_transparent_png_does_not_become_a_black_rectangle(): void
    {
        $image = new Imagick;
        $image->newImage(400, 300, new \ImagickPixel('transparent'));
        $image->setImageFormat('png');

        $path = tempnam(sys_get_temp_dir(), 'photo').'.png';
        file_put_contents($path, $image->getImageBlob());

        $imageId = $this->service->capture(
            $this->personId,
            new UploadedFile($path, 'photo.png', 'image/png', null, true)
        );

        $stored = new Imagick;
        $stored->readImageBlob($this->contentsOf($imageId));

        $pixel = $stored->getImagePixelColor(10, 10)->getColor();

        $this->assertGreaterThan(
            200,
            $pixel['r'],
            'Transparency must flatten onto white, not black; a black badge photo reads as censored.'
        );
    }

    private function contentsOf(int $imageId): string
    {
        $path = (string) DB::table('images')->where('id', $imageId)->value('path');

        return (string) $this->disk()->get($path);
    }

    private function disk(): Filesystem
    {
        return app(FilesystemFactory::class)->disk(config('filesystems.default'));
    }

    private function photo(string $name = 'photo.jpg', int $width = 800, int $height = 600): UploadedFile
    {
        $image = new Imagick;
        $image->newImage($width, $height, new \ImagickPixel('skyblue'));
        $image->setImageFormat('jpeg');

        $path = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        file_put_contents($path, $image->getImageBlob());

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function photoWithExif(): UploadedFile
    {
        $image = new Imagick;
        $image->newImage(800, 600, new \ImagickPixel('skyblue'));
        $image->setImageFormat('jpeg');
        $image->setImageProperty('exif:GPSLatitude', '25/1, 17/1, 0/1');
        $image->setImageProperty('exif:Make', 'TestPhone');

        $path = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        file_put_contents($path, $image->getImageBlob());

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Photo',
            'last_name' => 'Subject',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
