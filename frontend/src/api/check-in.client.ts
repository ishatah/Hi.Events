import {publicApi} from "./public-client";
import {
    Attendee,
    AttendeeDetailPublic,
    CheckInList,
    CheckInListStats,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam, PublicCheckIn,
    QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper";

export const publicCheckInClient = {
    getCheckInList: async (checkInListShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<CheckInList>>(`/check-in-lists/${checkInListShortId}`);
        return response.data;
    },
    getCheckInListStats: async (checkInListShortId: IdParam, eventOccurrenceId?: number | null) => {
        const qs = eventOccurrenceId ? `?event_occurrence_id=${eventOccurrenceId}` : '';
        const response = await publicApi.get<GenericDataResponse<CheckInListStats>>(`/check-in-lists/${checkInListShortId}/stats${qs}`);
        return response.data;
    },
    getCheckInListAttendees: async (checkInListShortId: IdParam, pagination: QueryFilters) => {
        const response = await publicApi.get<GenericPaginatedResponse<Attendee>>(`/check-in-lists/${checkInListShortId}/attendees` + queryParamsHelper.buildQueryString(pagination));
        return response.data;
    },
    getCheckInListAttendee: async (checkInListShortId: IdParam, attendeeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<Attendee>>(`/check-in-lists/${checkInListShortId}/attendees/${attendeeShortId}`);
        return response.data;
    },
    getCheckInListAttendeeDetail: async (checkInListShortId: IdParam, attendeeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<AttendeeDetailPublic>>(`/check-in-lists/${checkInListShortId}/attendees/${attendeeShortId}/detail`);
        return response.data;
    },
    // A scanned ticket code goes in and the lookup key comes back. The code is never returned
    // by any endpoint, so a leaked list link cannot be turned back into working tickets.
    resolveScannedTicket: async (checkInListShortId: IdParam, ticketCode: string) => {
        const response = await publicApi.post<GenericDataResponse<{short_id: string}>>(
            `/check-in-lists/${checkInListShortId}/resolve-ticket`,
            {ticket_code: ticketCode},
        );
        return response.data;
    },
    createCheckIn: async (checkInListShortId: IdParam, attendeeShortId: IdParam, action: 'check-in' | 'check-in-and-mark-order-as-paid') => {
        const response = await publicApi.post<GenericDataResponse<PublicCheckIn[]>>(`/check-in-lists/${checkInListShortId}/check-ins`, {
            "attendees": [
                {
                    "short_id": attendeeShortId,
                    "action": action
                }
            ]
        });
        return response.data;
    },
    deleteCheckIn: async (checkInListShortId: IdParam, checkInShortId: IdParam) => {
        const response = await publicApi.delete<GenericDataResponse<PublicCheckIn>>(`/check-in-lists/${checkInListShortId}/check-ins/${checkInShortId}`);
        return response.data;
    },
};
