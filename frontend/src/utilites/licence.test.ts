import {describe, expect, it, vi, beforeEach, afterEach} from 'vitest';

const getConfigMock = vi.fn();

vi.mock('./config.ts', () => ({
    getConfig: (key: string) => getConfigMock(key),
}));

const loadHelper = async () => {
    const module = await import('./helpers.ts');
    return module.iHavePurchasedALicence;
};

/**
 * Hi.Events is AGPL-3.0. Section 7(b) requires the "Powered by" notice to be retained
 * unless a commercial licence has been bought, and this flag is the only thing standing
 * between the two. Env values arrive as strings, so the documented
 * VITE_I_HAVE_PURCHASED_A_LICENCE=false was truthy and hid the notice — the opposite of
 * what setting it to false means.
 */
describe('iHavePurchasedALicence', () => {
    beforeEach(() => {
        getConfigMock.mockReset();
        vi.resetModules();
    });

    afterEach(() => {
        vi.resetModules();
    });

    it.each([
        ['false', 'the documented way to say no licence'],
        ['0', 'a falsey-looking string'],
        ['', 'an empty value'],
        ['no', 'an unrecognised value'],
        ['FALSE', 'an upper-case negative'],
    ])('keeps the attribution when the flag is %j (%s)', async (value) => {
        getConfigMock.mockReturnValue(value);

        const iHavePurchasedALicence = await loadHelper();

        expect(iHavePurchasedALicence()).toBe(false);
    });

    it('keeps the attribution when the flag is unset', async () => {
        getConfigMock.mockReturnValue(undefined);

        const iHavePurchasedALicence = await loadHelper();

        expect(iHavePurchasedALicence()).toBe(false);
    });

    it.each([
        ['true', 'the documented affirmative'],
        ['1', 'a numeric affirmative'],
    ])('hides the attribution only on an explicit %j (%s)', async (value) => {
        getConfigMock.mockReturnValue(value);

        const iHavePurchasedALicence = await loadHelper();

        expect(iHavePurchasedALicence()).toBe(true);
    });

});
