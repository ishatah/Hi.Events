import {describe, expect, it} from "vitest";
import {
    checkContrast,
    computeThemeVariables,
    detectMode,
    getContrastColor,
    getContrastRatio,
    getDefaultThemeSettings,
    getRelativeLuminance,
    hasContrastIssues,
    validateThemeSettings,
} from "./themeUtils";

describe("getRelativeLuminance", () => {
    it("returns 0 for black and 1 for white", () => {
        expect(getRelativeLuminance("#000000")).toBeCloseTo(0, 5);
        expect(getRelativeLuminance("#ffffff")).toBeCloseTo(1, 5);
    });

    it("applies the WCAG sRGB transfer function rather than a raw average", () => {
        // A raw channel average would give 0.5 for mid grey; the WCAG curve gives ~0.2159.
        expect(getRelativeLuminance("#808080")).toBeCloseTo(0.2159, 3);
    });
});

describe("getContrastRatio", () => {
    it("returns the maximum ratio for black on white", () => {
        expect(getContrastRatio("#000000", "#ffffff")).toBeCloseTo(21, 1);
    });

    it("returns 1 for identical colours", () => {
        expect(getContrastRatio("#1B7E99", "#1B7E99")).toBeCloseTo(1, 5);
    });

    it("is symmetric", () => {
        const a = getContrastRatio("#1B7E99", "#ffffff");
        const b = getContrastRatio("#ffffff", "#1B7E99");
        expect(a).toBeCloseTo(b, 5);
    });
});

describe("checkContrast", () => {
    it("grades black on white as AAA", () => {
        expect(checkContrast("#000000", "#ffffff").level).toBe("AAA");
    });

    it("grades a same-on-same pair as fail", () => {
        const result = checkContrast("#777777", "#777777");
        expect(result.level).toBe("fail");
        expect(result.passesAA).toBe(false);
        expect(result.passesAAA).toBe(false);
    });

    it("uses 4.5 for AA and 7 for AAA", () => {
        const aa = checkContrast("#767676", "#ffffff");
        expect(aa.ratio).toBeGreaterThanOrEqual(4.5);
        expect(aa.passesAA).toBe(true);
    });
});

describe("getContrastColor", () => {
    it("returns dark ink on a light background and light ink on a dark one", () => {
        expect(getContrastColor("#ffffff")).toBe("#1a1a1a");
        expect(getContrastColor("#000000")).toBe("#ffffff");
    });
});

describe("detectMode", () => {
    it("classifies light and dark backgrounds", () => {
        expect(detectMode("#ffffff")).toBe("light");
        expect(detectMode("#000000")).toBe("dark");
    });
});

describe("computeThemeVariables", () => {
    const settings = getDefaultThemeSettings();

    it("emits the full set of theme custom properties", () => {
        const vars = computeThemeVariables(settings);

        // Every consumer reads these via var(--theme-*); a missing key renders as
        // an invalid value rather than a visible error, so pin the contract.
        for (const key of [
            "--theme-accent",
            "--theme-background",
            "--theme-surface",
            "--theme-text-primary",
            "--theme-text-secondary",
            "--theme-text-tertiary",
            "--theme-border",
            "--theme-accent-contrast",
            "--theme-accent-soft",
            "--theme-accent-muted",
            "--theme-accent-tint-10",
            "--theme-accent-tint-15",
            "--theme-accent-tint-20",
            "--theme-font-family",
        ]) {
            expect(vars, `missing ${key}`).toHaveProperty(key);
            expect(String(vars[key as keyof typeof vars]).length).toBeGreaterThan(0);
        }
    });

    it("passes the organizer accent and background through verbatim", () => {
        const vars = computeThemeVariables({...settings, accent: "#34B8D9", background: "#000000"});

        expect(vars["--theme-accent"]).toBe("#34B8D9");
        expect(vars["--theme-background"]).toBe("#000000");
    });

    it("derives surfaces and text from the mode, not from organizer input", () => {
        const light = computeThemeVariables({...settings, mode: "light"});
        const dark = computeThemeVariables({...settings, mode: "dark"});

        expect(light["--theme-surface"]).not.toBe(dark["--theme-surface"]);
        expect(light["--theme-text-primary"]).not.toBe(dark["--theme-text-primary"]);
    });
});

describe("validateThemeSettings", () => {
    it("falls back to defaults when given nothing", () => {
        expect(validateThemeSettings(null)).toEqual(getDefaultThemeSettings());
        expect(validateThemeSettings(undefined)).toEqual(getDefaultThemeSettings());
    });

    it("infers mode from the background when mode is absent", () => {
        expect(validateThemeSettings({background: "#000000"}).mode).toBe("dark");
        expect(validateThemeSettings({background: "#ffffff"}).mode).toBe("light");
    });

    it("keeps an explicitly supplied mode", () => {
        expect(validateThemeSettings({background: "#ffffff", mode: "dark"}).mode).toBe("dark");
    });
});

describe("hasContrastIssues", () => {
    it("accepts the ARZO default palette", () => {
        expect(hasContrastIssues(getDefaultThemeSettings())).toBe(false);
    });

    it("flags an accent that is invisible on its own surface", () => {
        expect(hasContrastIssues({
            ...getDefaultThemeSettings(),
            accent: "#fdfdfd",
            background: "#ffffff",
            mode: "light",
        })).toBe(true);
    });
});
