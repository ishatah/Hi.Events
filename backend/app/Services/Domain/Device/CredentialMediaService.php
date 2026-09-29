<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * The physical media carrying a credential.
 *
 * A credential may be carried by several media over its life — a printed QR, a badge inlay,
 * a replacement wristband after the first is lost — and the history of which tag was live
 * when matters for clone detection.
 *
 * UIDs are stored hashed. A tag UID is readable by anyone who walks past with a phone, so
 * the stored value is not a secret; hashing it means a database copy does not hand somebody
 * a list of tags to clone, and lookup still works because the reader hashes what it read.
 *
 * @see docs/arzo-master-plan/36-rfid-nfc.md
 */
class CredentialMediaService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function encode(
        int $credentialId,
        string $mediaType,
        ?string $uid = null,
        ?int $encodedByUserId = null,
        ?int $encodedOnDeviceId = null,
    ): int {
        $credential = $this->databaseManager->table('credentials')
            ->where('id', $credentialId)
            ->first();

        if ($credential === null) {
            throw new ResourceConflictException(__('The credential could not be found.'));
        }

        if ((string) $credential->status !== 'ACTIVE') {
            throw new ResourceConflictException(
                __('Media can only be encoded for an active credential.')
            );
        }

        $uidHash = $uid !== null ? hash('sha256', $uid) : null;

        try {
            return (int) $this->databaseManager->table('credential_media')->insertGetId([
                'short_id' => 'cm_'.Str::lower(Str::random(20)),
                'credential_id' => $credentialId,
                'media_type' => $mediaType,
                'uid_hash' => $uidHash,
                'status' => 'ACTIVE',
                'encoded_at' => now(),
                'encoded_by' => $encodedByUserId,
                'encoded_on_device_id' => $encodedOnDeviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The index caught a tag already live against another credential. Two active
            // credentials behind one wristband is a cloned badge, which is exactly what this
            // subsystem exists to prevent.
            throw new ResourceConflictException(
                __('That tag is already active on another credential.')
            );
        }
    }

    /**
     * Replaces a lost tag, linking the new one to what it replaces.
     *
     * The chain is what makes a later clone detectable: a tag that turns up after being
     * replaced is a copy, not a re-issue.
     *
     * @throws ResourceConflictException
     */
    public function replace(
        int $mediaId,
        string $newUid,
        string $reason,
        ?int $encodedByUserId = null,
        ?int $encodedOnDeviceId = null,
    ): int {
        if (trim($reason) === '') {
            throw new ResourceConflictException(__('Replacing media requires a reason.'));
        }

        return $this->databaseManager->transaction(function () use (
            $mediaId,
            $newUid,
            $reason,
            $encodedByUserId,
            $encodedOnDeviceId
        ): int {
            $existing = $this->databaseManager->table('credential_media')
                ->where('id', $mediaId)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw new ResourceConflictException(__('That media could not be found.'));
            }

            if ((string) $existing->status !== 'ACTIVE') {
                throw new ResourceConflictException(__('That media is not active.'));
            }

            $this->deactivateRow((int) $existing->id, $reason);

            $newId = $this->encode(
                credentialId: (int) $existing->credential_id,
                mediaType: (string) $existing->media_type,
                uid: $newUid,
                encodedByUserId: $encodedByUserId,
                encodedOnDeviceId: $encodedOnDeviceId,
            );

            $this->databaseManager->table('credential_media')
                ->where('id', $newId)
                ->update(['replaces_media_id' => $existing->id, 'updated_at' => now()]);

            return $newId;
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function deactivate(int $mediaId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new ResourceConflictException(__('Deactivating media requires a reason.'));
        }

        $existing = $this->databaseManager->table('credential_media')
            ->where('id', $mediaId)
            ->first();

        if ($existing === null) {
            throw new ResourceConflictException(__('That media could not be found.'));
        }

        if ((string) $existing->status !== 'ACTIVE') {
            throw new ResourceConflictException(__('That media is already inactive.'));
        }

        $this->deactivateRow($mediaId, $reason);
    }

    /**
     * Resolves a tag read by a reader to its credential.
     *
     * Returns null for a deactivated tag rather than its old credential: a replaced
     * wristband presented at a door is not an admission.
     */
    public function resolveByUid(string $uid): ?object
    {
        return $this->databaseManager->table('credential_media')
            ->join('credentials', 'credentials.id', '=', 'credential_media.credential_id')
            ->where('credential_media.uid_hash', hash('sha256', $uid))
            ->where('credential_media.status', 'ACTIVE')
            ->select([
                'credentials.*',
                'credential_media.id as media_id',
                'credential_media.media_type',
            ])
            ->first();
    }

    /**
     * @return array<int, object>
     */
    public function historyFor(int $credentialId): array
    {
        return $this->databaseManager->table('credential_media')
            ->where('credential_id', $credentialId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function deactivateRow(int $mediaId, string $reason): void
    {
        $this->databaseManager->table('credential_media')
            ->where('id', $mediaId)
            ->update([
                'status' => 'DEACTIVATED',
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'updated_at' => now(),
            ]);
    }
}
