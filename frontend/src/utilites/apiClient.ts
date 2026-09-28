import {api} from "../api/client.ts";
import {publicApi} from "../api/public-client.ts";

/**
 * Sets the Authorization header on the shared axios instances.
 *
 * Browser-only. The axios instances are module singletons, so on the server this would be shared
 * across concurrent requests. SSR sources its token from the per-request AsyncLocalStorage context
 * instead — see `ssrRequestContext.ts` and the request interceptors in `api/client.ts` and
 * `api/public-client.ts`.
 */
export const setAuthToken = (token?: string | undefined | null) => {
    if (!token) {
        delete api.defaults.headers.common['Authorization'];
        delete publicApi.defaults.headers.common['Authorization'];
        return;
    }

    api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    publicApi.defaults.headers.common['Authorization'] = `Bearer ${token}`;
};
