<?php

namespace Tests\Unit\Services\Domain\Notification;

use HiEvents\Exceptions\InvalidPhoneNumberException;
use HiEvents\Services\Domain\Notification\PhoneNumberService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberServiceTest extends TestCase
{
    private PhoneNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PhoneNumberService;
    }

    #[DataProvider('qatariNumberProvider')]
    public function test_a_qatari_number_normalises_however_it_was_typed(string $input): void
    {
        $this->assertSame(
            '+97433123456',
            $this->service->toE164($input),
            'A number stored as somebody typed it cannot be matched against a suppression '
            .'list or handed to a provider.'
        );
    }

    public static function qatariNumberProvider(): array
    {
        return [
            'plain' => ['33123456'],
            'spaced' => ['3312 3456'],
            'dashed' => ['3312-3456'],
            'with country code' => ['97433123456'],
            'e164' => ['+97433123456'],
            'international access prefix' => ['0097433123456'],
            'bracketed' => ['(+974) 3312 3456'],
            'trunk zero' => ['033123456'],
        ];
    }

    public function test_a_number_already_carrying_its_country_code_is_not_prefixed_twice(): void
    {
        $this->assertSame('+97433123456', $this->service->toE164('97433123456'));
    }

    public function test_a_number_starting_with_the_calling_code_but_too_short_is_refused(): void
    {
        // 9741234 is seven digits: not a national Qatari number, and not one carrying its
        // own code either. Guessing which would produce a number nobody answers.
        $this->expectException(InvalidPhoneNumberException::class);
        $this->service->toE164('9741234');
    }

    public function test_the_default_country_decides_an_ambiguous_number(): void
    {
        $this->assertSame('+971551234567', $this->service->toE164('055 123 4567', 'AE'));
        $this->assertSame('+447700900123', $this->service->toE164('07700 900123', 'GB'));
    }

    public function test_a_wrong_length_number_is_refused_rather_than_sent_to(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->service->toE164('+9743312');
    }

    public function test_a_number_with_no_digits_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/no digits/');
        $this->service->toE164('not a phone number');
    }

    public function test_an_empty_number_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/required/');
        $this->service->toE164('   ');
    }

    public function test_an_unconfigured_country_is_refused_rather_than_guessed(): void
    {
        $this->expectExceptionMessageMatches('/No dialling code/');
        $this->service->toE164('12345678', 'ZZ');
    }

    public function test_validity_can_be_checked_without_catching(): void
    {
        $this->assertTrue($this->service->isValid('3312 3456'));
        $this->assertFalse($this->service->isValid('123'));
    }

    public function test_a_masked_number_shows_only_the_last_four_digits(): void
    {
        $this->assertSame(
            '+*******3456',
            $this->service->mask('+97433123456'),
            'Support needs to confirm which number was used; the delivery log should not '
            .'become a contact database.'
        );
    }

    public function test_a_very_short_number_masks_entirely(): void
    {
        $this->assertSame('+****', $this->service->mask('+974'));
    }

    // ---------------------------------------------------------------- segments

    public function test_a_short_english_message_is_one_segment(): void
    {
        $count = $this->service->segmentCount('Gate 3 has moved to Gate 5.');

        $this->assertSame('GSM-7', $count['encoding']);
        $this->assertSame(1, $count['segments']);
        $this->assertSame(160, $count['characters_per_segment']);
    }

    public function test_an_english_message_of_161_characters_is_two_segments(): void
    {
        $count = $this->service->segmentCount(str_repeat('a', 161));

        $this->assertSame(2, $count['segments']);
        $this->assertSame(
            153,
            $count['characters_per_segment'],
            'Concatenated messages lose seven characters per segment to the header.'
        );
    }

    public function test_arabic_forces_unicode_and_a_70_character_segment(): void
    {
        $count = $this->service->segmentCount('تم نقل البوابة 3 إلى البوابة 5');

        $this->assertSame('UCS-2', $count['encoding']);
        $this->assertSame(1, $count['segments']);
        $this->assertSame(70, $count['characters_per_segment']);
    }

    public function test_a_140_character_arabic_message_costs_three_segments(): void
    {
        $count = $this->service->segmentCount(str_repeat('ا', 140));

        $this->assertSame(
            3,
            $count['segments'],
            'The English equivalent is one segment, so Arabic templates must be written '
            .'short and the cost shown before sending.'
        );
    }

    public function test_one_arabic_character_makes_the_whole_message_unicode(): void
    {
        $mostlyEnglish = str_repeat('a', 100).'ا';

        $count = $this->service->segmentCount($mostlyEnglish);

        $this->assertSame('UCS-2', $count['encoding']);
        $this->assertSame(2, $count['segments']);
    }

    public function test_gsm7_extended_characters_cost_two_each(): void
    {
        $withBraces = str_repeat('{', 81);

        $this->assertSame(
            2,
            $this->service->segmentCount($withBraces)['segments'],
            'Extended characters take two septets, so 81 of them exceed one segment.'
        );
    }

    public function test_an_empty_message_is_no_segments(): void
    {
        $this->assertSame(0, $this->service->segmentCount('')['segments']);
    }

    public function test_a_newline_stays_within_gsm7(): void
    {
        $this->assertSame('GSM-7', $this->service->segmentCount("Gate moved.\nSee staff.")['encoding']);
    }

    public function test_an_emoji_forces_unicode(): void
    {
        $this->assertSame('UCS-2', $this->service->segmentCount('Gate moved 👍')['encoding']);
    }
}
