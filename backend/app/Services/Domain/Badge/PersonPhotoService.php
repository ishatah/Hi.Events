<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Imagick;

/**
 * Stores the photo captured at an accreditation desk.
 *
 * Deliberately not routed through ImageStorageService. That stores every image with public
 * visibility under a filename derived from the original, which is right for an event cover
 * and wrong for a photograph of a person's face: it would sit on a guessable public URL.
 * A badge photo is personal data under PDPL and GDPR, so it is written to a private path
 * under a random name and only ever read server-side by the badge renderer.
 *
 * @see docs/arzo-master-plan/23-accreditation.md
 * @see docs/arzo-master-plan/65-privacy-gdpr.md
 */
class PersonPhotoService
{
    private const MAX_EDGE_PIXELS = 1200;

    private const JPEG_QUALITY = 88;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly FilesystemFactory $filesystemFactory,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function capture(int $personId, UploadedFile $photo): int
    {
        $person = $this->databaseManager->table('persons')
            ->where('id', $personId)
            ->whereNull('deleted_at')
            ->first();

        if ($person === null) {
            throw new ResourceConflictException(__('The person could not be found.'));
        }

        $encoded = $this->normalise($photo);

        // A random name, not the uploaded one: an original filename can carry the person's
        // own name, which would leak it through the storage path.
        $path = sprintf('person-photos/%d/%s.jpg', $personId, Str::lower(Str::random(40)));

        $this->disk()->put($path, $encoded, 'private');

        return $this->databaseManager->transaction(function () use ($person, $personId, $path, $encoded): int {
            $imageId = (int) $this->databaseManager->table('images')->insertGetId([
                'entity_id' => $personId,
                'entity_type' => 'HiEvents\\DomainObjects\\PersonDomainObject',
                'type' => 'PERSON_PHOTO',
                'filename' => basename($path),
                'disk' => config('filesystems.default'),
                'path' => $path,
                'size' => strlen($encoded),
                'mime_type' => 'image/jpeg',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->databaseManager->table('persons')
                ->where('id', $personId)
                ->update(['photo_image_id' => $imageId, 'updated_at' => now()]);

            // Replacing a photo removes the file it replaced. Faces should not accumulate
            // in storage after the record stops pointing at them.
            if ($person->photo_image_id !== null) {
                $this->deleteImage((int) $person->photo_image_id);
            }

            return $imageId;
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function remove(int $personId): void
    {
        $imageId = $this->databaseManager->table('persons')
            ->where('id', $personId)
            ->value('photo_image_id');

        if ($imageId === null) {
            throw new ResourceConflictException(__('This person has no photo.'));
        }

        $this->databaseManager->transaction(function () use ($personId, $imageId): void {
            $this->databaseManager->table('persons')
                ->where('id', $personId)
                ->update(['photo_image_id' => null, 'updated_at' => now()]);

            $this->deleteImage((int) $imageId);
        });
    }

    /**
     * Resized and re-encoded rather than stored as uploaded.
     *
     * Re-encoding drops EXIF, which on a phone photo carries the GPS coordinates of where
     * it was taken — a location trail nobody consented to when they stood at a desk. It also
     * bounds a 12MP capture that would otherwise be embedded whole in every badge PDF.
     */
    private function normalise(UploadedFile $photo): string
    {
        $image = new Imagick($photo->getRealPath());

        // Read the orientation before flattening. mergeImageLayers returns a new object
        // that does not carry the tag, so asking it afterwards reports UNDEFINED and a
        // phone portrait prints sideways on the badge.
        $orientation = $image->getImageOrientation();

        // Flatten onto white: a transparent PNG would otherwise get a black background
        // when encoded as JPEG, which on a badge looks like a censored photo.
        $image->setImageBackgroundColor('white');
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        if (max($image->getImageWidth(), $image->getImageHeight()) > self::MAX_EDGE_PIXELS) {
            $image->resizeImage(
                self::MAX_EDGE_PIXELS,
                self::MAX_EDGE_PIXELS,
                Imagick::FILTER_LANCZOS,
                1,
                true
            );
        }

        // stripImage removes EXIF wholesale, so the rotation is baked into the pixels
        // first rather than left as a tag nothing will read.
        $this->applyOrientation($image, $orientation);
        $image->stripImage();

        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(self::JPEG_QUALITY);

        $encoded = $image->getImageBlob();

        $image->clear();

        return $encoded;
    }

    private function applyOrientation(Imagick $image, int $orientation): void
    {
        match ($orientation) {
            Imagick::ORIENTATION_BOTTOMRIGHT => $image->rotateImage('#000', 180),
            Imagick::ORIENTATION_RIGHTTOP => $image->rotateImage('#000', 90),
            Imagick::ORIENTATION_LEFTBOTTOM => $image->rotateImage('#000', -90),
            default => null,
        };

        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private function deleteImage(int $imageId): void
    {
        $image = $this->databaseManager->table('images')->where('id', $imageId)->first();

        if ($image === null) {
            return;
        }

        if ($image->path !== null && $this->disk()->exists($image->path)) {
            $this->disk()->delete($image->path);
        }

        $this->databaseManager->table('images')->where('id', $imageId)->delete();
    }

    private function disk(): Filesystem
    {
        return $this->filesystemFactory->disk(config('filesystems.default'));
    }
}
