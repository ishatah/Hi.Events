<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Rsvp;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Rsvp\RespondToInvitationRequest;
use HiEvents\Services\Domain\Rsvp\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Answering an invitation needs no account: the token in the emailed link is the credential,
 * which is why this route is throttled and why the token is stored only as a hash.
 */
class RespondToInvitationPublicAction extends BaseAction
{
    public function __construct(
        private readonly RsvpService $rsvpService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(RespondToInvitationRequest $request, string $token): JsonResponse
    {
        try {
            $result = $this->rsvpService->respond(
                token: $token,
                response: $request->validated('response'),
                partySize: (int) ($request->input('party_size') ?? 1),
                formData: $request->input('form_data'),
                notes: $request->input('notes'),
                ipAddress: $request->ip(),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['response' => $exception->getMessage()]);
        }

        return $this->jsonResponse($result->toArray());
    }
}
