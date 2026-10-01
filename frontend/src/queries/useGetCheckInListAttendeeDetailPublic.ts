import {useQuery} from "@tanstack/react-query";
import {publicCheckInClient} from "../api/check-in.client";
import {IdParam} from "../types.ts";

export const GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY = "getCheckInListAttendeeDetailPublic";

export const useGetCheckInListAttendeeDetailPublic = (
    checkInListShortId: IdParam,
    attendeeShortId: IdParam | null,
) => {
    return useQuery({
        queryKey: [GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY, checkInListShortId, attendeeShortId],
        queryFn: () => publicCheckInClient.getCheckInListAttendeeDetail(checkInListShortId, attendeeShortId!),
        enabled: !!checkInListShortId && !!attendeeShortId,
        retry: false,
    });
};
