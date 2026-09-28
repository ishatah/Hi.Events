import {AsyncLocalStorage} from "node:async_hooks";
import {beforeAll, describe, expect, it} from "vitest";
import {
    getSsrRequestContext,
    initSsrRequestContextStorage,
    runWithSsrRequestContext,
    type SsrRequestContext,
} from "./ssrRequestContext";

const context = (authToken?: string | null): SsrRequestContext => ({
    // The query client is opaque to this module; only identity matters here.
    queryClient: {} as SsrRequestContext["queryClient"],
    authToken,
});

const delay = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms));

describe("ssrRequestContext", () => {
    beforeAll(() => {
        initSsrRequestContextStorage(new AsyncLocalStorage<SsrRequestContext>());
    });

    it("exposes no context outside a request", () => {
        expect(getSsrRequestContext()).toBeUndefined();
    });

    it("exposes the context inside a request", () => {
        runWithSsrRequestContext(context("token-a"), () => {
            expect(getSsrRequestContext()?.authToken).toBe("token-a");
        });
    });

    it("keeps concurrent requests isolated across an await", async () => {
        // This is the regression test for the SSR token leak: the old code mutated a
        // module-level axios default, so a slow render picked up a later request's token.
        const read = async (token: string, holdMs: number) =>
            runWithSsrRequestContext(context(token), async () => {
                await delay(holdMs);
                return getSsrRequestContext()?.authToken;
            });

        const [slow, fast] = await Promise.all([read("token-a", 30), read("token-b", 1)]);

        expect(slow).toBe("token-a");
        expect(fast).toBe("token-b");
    });

    it("keeps an anonymous request anonymous alongside an authenticated one", async () => {
        const read = async (token: string | undefined, holdMs: number) =>
            runWithSsrRequestContext(context(token), async () => {
                await delay(holdMs);
                return getSsrRequestContext()?.authToken;
            });

        const [anonymous, authenticated] = await Promise.all([
            read(undefined, 25),
            read("token-c", 1),
        ]);

        expect(anonymous).toBeUndefined();
        expect(authenticated).toBe("token-c");
    });

    it("propagates through nested async calls", async () => {
        const result = await runWithSsrRequestContext(context("token-d"), async () => {
            await delay(1);
            return (async () => {
                await delay(1);
                return getSsrRequestContext()?.authToken;
            })();
        });

        expect(result).toBe("token-d");
    });

    it("does not leak the context after the request settles", async () => {
        await runWithSsrRequestContext(context("token-e"), async () => {
            await delay(1);
        });

        expect(getSsrRequestContext()).toBeUndefined();
    });
});
