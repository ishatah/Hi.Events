<?php

use HiEvents\Services\Infrastructure\Realtime\RealtimeChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Per-event operational channels.
 *
 * Subscribing to a channel is a read of live operational data, so each is gated on the
 * same permission the equivalent REST endpoint requires. The mapping lives in the
 * authorizer so a new channel cannot be added without deciding who may hear it.
 */
foreach (RealtimeChannelAuthorizer::channelPermissions() as $channel => $permission) {
    Broadcast::channel(
        sprintf('event.{eventId}.%s', $channel),
        function ($user, $eventId) use ($permission): bool {
            return app(RealtimeChannelAuthorizer::class)
                ->canListenToEvent((int) $user->id, (int) $eventId, $permission);
        }
    );
}
