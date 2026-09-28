import type {QueryClient} from "@tanstack/react-query";

export interface SsrRequestContext {
    queryClient: QueryClient;
    authToken?: string | null;
}

interface AsyncStore<T> {
    getStore(): T | undefined;

    run<R>(store: T, callback: () => R): R;
}

let storage: AsyncStore<SsrRequestContext> | null = null;

export const initSsrRequestContextStorage = (instance: AsyncStore<SsrRequestContext>) => {
    storage = instance;
};

export const runWithSsrRequestContext = <R>(context: SsrRequestContext, callback: () => R): R => {
    if (!storage) {
        throw new Error(
            "SSR request context storage is not initialised. Call initSsrRequestContextStorage() before rendering.",
        );
    }

    return storage.run(context, callback);
};

export const getSsrRequestContext = (): SsrRequestContext | undefined => storage?.getStore();
