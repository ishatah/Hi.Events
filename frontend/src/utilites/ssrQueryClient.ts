import { QueryClient } from "@tanstack/react-query";
import {isSsr} from "./helpers.ts";
import {queryClient} from "./queryClient.ts";
import {getSsrRequestContext} from "./ssrRequestContext.ts";

export function getQueryClient(): QueryClient {
    if (isSsr()) {
        const contextClient = getSsrRequestContext()?.queryClient;

        if (contextClient) {
            return contextClient;
        }
    }

    return queryClient;
}
