import type * as express from "express";
import ReactDOMServer from "react-dom/server";
import {dehydrate, QueryClient} from "@tanstack/react-query";

import {router} from "./router";
import {App} from "./App";
import {AsyncLocalStorage} from "node:async_hooks";
import {initSsrRequestContextStorage, runWithSsrRequestContext} from "./utilites/ssrRequestContext.ts";
import {createStaticHandler, createStaticRouter, StaticRouterProvider} from "react-router";
import {dynamicActivateLocale} from "./locales.ts";
import {generateThemeColors} from "./utilites/themeColors.ts";

initSsrRequestContextStorage(new AsyncLocalStorage());

const themeColors = generateThemeColors();

const getLocale = (req: express.Request): string => {
    if (req.cookies.locale) {
        return req.cookies.locale;
    }

    const acceptLanguage = req.headers['accept-language'];
    return acceptLanguage ? acceptLanguage.split(',')[0].split('-')[0] : 'en';
}

export async function render(params: {
    req: express.Request;
    res: express.Response;
}) {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000, // 60 seconds - prevents immediate refetch on client
                refetchOnWindowFocus: false,
                networkMode: "always",
            },
            mutations: {
                networkMode: 'always',
            }
        },
    });

    const helmetContext = {};

    const {appHtml, dehydratedState, context} = await runWithSsrRequestContext(
        {queryClient, authToken: params.req.cookies.token},
        async () => {
            const {query, dataRoutes} = createStaticHandler(router);
            const remixRequest = createFetchRequest(params.req, params.res);
            const routerContext = await query(remixRequest);

            if (routerContext instanceof Response) {
                throw routerContext;
            }

            await dynamicActivateLocale(getLocale(params.req));

            const routerWithContext = createStaticRouter(dataRoutes, routerContext);

            const html = ReactDOMServer.renderToString(
                <App
                    queryClient={queryClient}
                    helmetContext={helmetContext}
                    locale={getLocale(params.req)}
                    themeColors={themeColors}
                >
                    <StaticRouterProvider
                        router={routerWithContext}
                        context={routerContext}
                    />
                </App>
            );

            return {
                appHtml: html,
                dehydratedState: dehydrate(queryClient),
                context: routerContext,
            };
        },
    );

    return {
        appHtml: appHtml,
        dehydratedState,
        helmetContext,
        themeColors,
        statusCode: context.statusCode,
        renderErrors: Object.values(context.errors ?? {}),
    };
}

export function createFetchRequest(
    req: express.Request,
    res: express.Response
): Request {
    const origin = `${req.protocol}://${req.get("host")}`;
    const url = new URL(req.originalUrl || req.url, origin);
    const controller = new AbortController();
    res.on("close", () => controller.abort());

    const headers = new Headers();

    for (const [key, values] of Object.entries(req.headers)) {
        if (values) {
            if (Array.isArray(values)) {
                for (const value of values) {
                    headers.append(key, value);
                }
            } else {
                headers.set(key, values);
            }
        }
    }

    const init: RequestInit = {
        method: req.method,
        headers,
        signal: controller.signal,
    };

    if (req.method !== "GET" && req.method !== "HEAD") {
        init.body = req.body;
    }

    return new Request(url.href, init);
}
