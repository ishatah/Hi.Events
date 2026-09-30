<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\SendEventEmailMessagesService;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Log\Logger;
use Tests\TestCase;

class SendEventEmailMessagesServiceTest extends TestCase
{
    public function test_occurrence_filter_is_empty_for_a_message_queued_by_a_previous_release(): void
    {
        $messageData = (new ReflectionClass(SendMessageDTO::class))->newInstanceWithoutConstructor();

        $service = new SendEventEmailMessagesService(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(AttendeeRepositoryInterface::class),
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(MessageRepositoryInterface::class),
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(Logger::class),
            Mockery::mock(Dispatcher::class),
        );

        $occurrenceWhere = new ReflectionMethod($service, 'occurrenceWhere');

        $this->assertSame([], $occurrenceWhere->invoke($service, $messageData));
    }

    /**
     * Every path that reaches a real recipient has to stop on a test send.
     *
     * Asserted structurally rather than by sending, because the defect was one path out of
     * five missing the check while the other four had it: an organizer testing their wording
     * on an order message emailed the actual customer, and that cannot be taken back. A path
     * added later without the guard fails here.
     */
    public function test_every_recipient_path_stops_on_a_test_send(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Domain/Mail/SendEventEmailMessagesService.php')
        );

        $recipientMethods = [
            'emailAttendees',
            'sendOrderMessages',
            'sendProductMessages',
        ];

        foreach ($recipientMethods as $method) {
            $body = $this->methodBody($source, $method);

            $this->assertStringContainsString(
                'is_test',
                $body,
                sprintf('%s() sends to real recipients and must check is_test.', $method)
            );
        }
    }

    /**
     * Every method that sends to somebody other than the message sender must be listed in the
     * test above. This catches a new recipient path that nobody thought to guard.
     */
    public function test_no_recipient_path_is_left_unchecked(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Domain/Mail/SendEventEmailMessagesService.php')
        );

        preg_match_all('/private function (send\w+|email\w+)\(/', $source, $matches);

        $exempt = [
            // Sends only to the organizer who pressed the button, which is the whole point of
            // a test send.
            'sendEmailToMessageSender',
            // The single-recipient primitive every path above funnels through; it cannot know
            // whether its address is a real customer or the sender.
            'sendMessage',
            // Delegate to emailAttendees(), which holds the check.
            'sendAttendeeMessages',
            'sendTicketHolderMessages',
            'sendEventMessages',
        ];

        $guarded = ['emailAttendees', 'sendOrderMessages', 'sendProductMessages'];

        foreach ($matches[1] as $method) {
            $this->assertContains(
                $method,
                array_merge($exempt, $guarded),
                sprintf(
                    '%s() is a new send path. Either guard it on is_test or declare why it is '
                    .'exempt.',
                    $method
                )
            );
        }
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'function '.$method.'(');

        $this->assertNotFalse($start, sprintf('%s() was not found.', $method));

        $nextFunction = strpos($source, "\n    private function ", $start + 1);
        $end = $nextFunction !== false ? $nextFunction : strlen($source);

        return substr($source, $start, $end - $start);
    }
}
