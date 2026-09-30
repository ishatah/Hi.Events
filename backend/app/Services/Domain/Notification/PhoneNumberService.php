<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Notification;

use HiEvents\Exceptions\InvalidPhoneNumberException;

/**
 * Normalises phone numbers to E.164 and counts SMS segments.
 *
 * Phone capture is step one for any SMS or WhatsApp work, not the provider: a provider
 * integration is useless while the platform holds numbers in whatever shape somebody typed.
 * A number stored as "3312 3456" cannot be matched against a suppression list, deduplicated,
 * or handed to a provider.
 *
 * Deliberately not a libphonenumber port. The full metadata set is large, updates constantly,
 * and what is needed here is E.164 shape plus a default country — not carrier or geocoding
 * lookups. The GCC dialling plans below are fixed-length and stable.
 *
 * @see docs/arzo-master-plan/43-sms-notifications.md
 */
class PhoneNumberService
{
    private const GSM7_SEGMENT = 160;

    private const GSM7_CONCATENATED_SEGMENT = 153;

    private const UCS2_SEGMENT = 70;

    private const UCS2_CONCATENATED_SEGMENT = 67;

    /**
     * National number lengths by calling code, for the markets ARZO operates in. A number
     * that is the wrong length for its country is a typo, and sending to it wastes money on
     * a message nobody receives.
     *
     * @var array<string, array{min: int, max: int}>
     */
    private const NATIONAL_LENGTHS = [
        '974' => ['min' => 8, 'max' => 8],   // Qatar
        '971' => ['min' => 8, 'max' => 9],   // UAE
        '966' => ['min' => 9, 'max' => 9],   // Saudi Arabia
        '965' => ['min' => 8, 'max' => 8],   // Kuwait
        '973' => ['min' => 8, 'max' => 8],   // Bahrain
        '968' => ['min' => 8, 'max' => 8],   // Oman
        '20' => ['min' => 9, 'max' => 10],   // Egypt
        '44' => ['min' => 9, 'max' => 10],   // United Kingdom
        '1' => ['min' => 10, 'max' => 10],   // NANP
    ];

    /**
     * @var array<string, string>
     */
    private const COUNTRY_CALLING_CODES = [
        'QA' => '974',
        'AE' => '971',
        'SA' => '966',
        'KW' => '965',
        'BH' => '973',
        'OM' => '968',
        'EG' => '20',
        'GB' => '44',
        'US' => '1',
    ];

    /**
     * @throws InvalidPhoneNumberException
     */
    public function toE164(string $input, string $defaultCountry = 'QA'): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new InvalidPhoneNumberException(__('A phone number is required.'));
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            throw new InvalidPhoneNumberException(
                __('That phone number contains no digits.')
            );
        }

        if ($hasPlus) {
            return $this->assertValid('+'.$digits);
        }

        // "00974..." is the same as "+974..." — the international access prefix, not part of
        // the number.
        if (str_starts_with($digits, '00')) {
            return $this->assertValid('+'.substr($digits, 2));
        }

        $callingCode = $this->callingCodeFor($defaultCountry);

        // A number already carrying its own country code must not have it prefixed again:
        // "97433123456" is a Qatari number written in full, not a national one.
        if (str_starts_with($digits, $callingCode)
            && $this->isPlausibleNational($callingCode, substr($digits, strlen($callingCode)))
        ) {
            return $this->assertValid('+'.$digits);
        }

        return $this->assertValid('+'.$callingCode.$this->stripTrunkPrefix($digits));
    }

    public function isValid(string $input, string $defaultCountry = 'QA'): bool
    {
        try {
            $this->toE164($input, $defaultCountry);

            return true;
        } catch (InvalidPhoneNumberException) {
            return false;
        }
    }

    /**
     * Enough for support to confirm which number was used, without the delivery log becoming
     * a contact database.
     */
    public function mask(string $e164): string
    {
        $digits = preg_replace('/\D+/', '', $e164) ?? '';

        if (strlen($digits) <= 4) {
            return '+****';
        }

        return '+'.str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }

    /**
     * An SMS segment holds 160 GSM-7 characters but only 70 in UCS-2, which any Arabic
     * character forces. A 140-character Arabic message is three billed segments where the
     * English equivalent is one, so the send UI must show this before anybody presses send.
     *
     * @return array{encoding: string, characters: int, segments: int, characters_per_segment: int}
     */
    public function segmentCount(string $body): array
    {
        $isUnicode = ! $this->isGsm7($body);
        $characters = $this->characterCount($body, $isUnicode);

        $single = $isUnicode ? self::UCS2_SEGMENT : self::GSM7_SEGMENT;
        $concatenated = $isUnicode ? self::UCS2_CONCATENATED_SEGMENT : self::GSM7_CONCATENATED_SEGMENT;

        if ($characters === 0) {
            $segments = 0;
        } elseif ($characters <= $single) {
            $segments = 1;
        } else {
            $segments = (int) ceil($characters / $concatenated);
        }

        return [
            'encoding' => $isUnicode ? 'UCS-2' : 'GSM-7',
            'characters' => $characters,
            'segments' => $segments,
            'characters_per_segment' => $segments > 1 ? $concatenated : $single,
        ];
    }

    private function characterCount(string $body, bool $isUnicode): int
    {
        if (! $isUnicode) {
            // These take two GSM-7 septets each, so a message of 80 braces is two segments.
            $extended = preg_match_all('/[\^{}\\\\\[\]~|€]/u', $body);

            return mb_strlen($body) + ($extended !== false ? $extended : 0);
        }

        return mb_strlen($body, 'UTF-8');
    }

    private function isGsm7(string $body): bool
    {
        $gsm7 = '@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?'
            .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
            ."\n\r"
            .'^{}\\[~]|€';

        $length = mb_strlen($body, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            if (! str_contains($gsm7, mb_substr($body, $i, 1, 'UTF-8'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidPhoneNumberException
     */
    private function assertValid(string $e164): string
    {
        $digits = substr($e164, 1);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidPhoneNumberException(
                __('That phone number is not a valid length.')
            );
        }

        // PHP coerces numeric-string array keys to integers, so the calling code arrives
        // here as an int and must be cast back before any string comparison.
        foreach (self::NATIONAL_LENGTHS as $callingCode => $lengths) {
            $callingCode = (string) $callingCode;

            if (! str_starts_with($digits, $callingCode)) {
                continue;
            }

            $nationalLength = strlen($digits) - strlen($callingCode);

            if ($nationalLength < $lengths['min'] || $nationalLength > $lengths['max']) {
                throw new InvalidPhoneNumberException(
                    __('That does not look like a valid :code number.', ['code' => '+'.$callingCode])
                );
            }

            return $e164;
        }

        return $e164;
    }

    private function isPlausibleNational(string $callingCode, string $national): bool
    {
        $lengths = self::NATIONAL_LENGTHS[$callingCode] ?? null;

        if ($lengths === null) {
            return true;
        }

        return strlen($national) >= $lengths['min'] && strlen($national) <= $lengths['max'];
    }

    /**
     * A leading zero is a domestic trunk prefix, not part of the number: "055 123 4567" in
     * the UAE is "+971551234567", not "+9710551234567".
     */
    private function stripTrunkPrefix(string $digits): string
    {
        return ltrim($digits, '0') !== '' ? ltrim($digits, '0') : $digits;
    }

    /**
     * @throws InvalidPhoneNumberException
     */
    private function callingCodeFor(string $country): string
    {
        $code = self::COUNTRY_CALLING_CODES[strtoupper($country)] ?? null;

        if ($code === null) {
            throw new InvalidPhoneNumberException(
                __('No dialling code is configured for :country.', ['country' => $country])
            );
        }

        return $code;
    }
}
