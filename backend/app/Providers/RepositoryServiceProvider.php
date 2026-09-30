<?php

declare(strict_types=1);

namespace HiEvents\Providers;

use HiEvents\Repository\Eloquent\AccessGrantRepository;
use HiEvents\Repository\Eloquent\AccessLogRepository;
use HiEvents\Repository\Eloquent\AccessPointRepository;
use HiEvents\Repository\Eloquent\AccessRuleRepository;
use HiEvents\Repository\Eloquent\AccountAttributionRepository;
use HiEvents\Repository\Eloquent\AccountConfigurationRepository;
use HiEvents\Repository\Eloquent\AccountDeletionRequestRepository;
use HiEvents\Repository\Eloquent\AccountMessagingTierRepository;
use HiEvents\Repository\Eloquent\AccountRepository;
use HiEvents\Repository\Eloquent\AccountUserRepository;
use HiEvents\Repository\Eloquent\AccreditationRepository;
use HiEvents\Repository\Eloquent\AccreditationTypeRepository;
use HiEvents\Repository\Eloquent\AccreditationTypeRuleRepository;
use HiEvents\Repository\Eloquent\AffiliateRepository;
use HiEvents\Repository\Eloquent\AnnouncementRepository;
use HiEvents\Repository\Eloquent\AnnouncementUserRepository;
use HiEvents\Repository\Eloquent\ApiKeyRepository;
use HiEvents\Repository\Eloquent\AttendeeCheckInRepository;
use HiEvents\Repository\Eloquent\AttendeeRepository;
use HiEvents\Repository\Eloquent\BadgePrintJobRepository;
use HiEvents\Repository\Eloquent\BadgeRepository;
use HiEvents\Repository\Eloquent\BadgeTemplateRepository;
use HiEvents\Repository\Eloquent\BoothAssignmentRepository;
use HiEvents\Repository\Eloquent\BoothRepository;
use HiEvents\Repository\Eloquent\BuildingRepository;
use HiEvents\Repository\Eloquent\CapacityAssignmentRepository;
use HiEvents\Repository\Eloquent\CheckInListRepository;
use HiEvents\Repository\Eloquent\CompanyRepository;
use HiEvents\Repository\Eloquent\CredentialRepository;
use HiEvents\Repository\Eloquent\DeviceRepository;
use HiEvents\Repository\Eloquent\EmailTemplateRepository;
use HiEvents\Repository\Eloquent\EventDailyStatisticRepository;
use HiEvents\Repository\Eloquent\EventExhibitorRepository;
use HiEvents\Repository\Eloquent\EventLocationRepository;
use HiEvents\Repository\Eloquent\EventOccurrenceDailyStatisticRepository;
use HiEvents\Repository\Eloquent\EventOccurrenceRepository;
use HiEvents\Repository\Eloquent\EventOccurrenceStatisticRepository;
use HiEvents\Repository\Eloquent\EventRepository;
use HiEvents\Repository\Eloquent\EventSettingsRepository;
use HiEvents\Repository\Eloquent\EventSpamCheckRepository;
use HiEvents\Repository\Eloquent\EventStatisticRepository;
use HiEvents\Repository\Eloquent\EventUserRepository;
use HiEvents\Repository\Eloquent\EventVenueRepository;
use HiEvents\Repository\Eloquent\ExhibitorStaffRepository;
use HiEvents\Repository\Eloquent\FloorRepository;
use HiEvents\Repository\Eloquent\ImageRepository;
use HiEvents\Repository\Eloquent\InvitationRepository;
use HiEvents\Repository\Eloquent\InvoiceRepository;
use HiEvents\Repository\Eloquent\LeadCaptureRepository;
use HiEvents\Repository\Eloquent\LeadConsentRepository;
use HiEvents\Repository\Eloquent\LeadRepository;
use HiEvents\Repository\Eloquent\LocationRepository;
use HiEvents\Repository\Eloquent\MessageRepository;
use HiEvents\Repository\Eloquent\OrderApplicationFeeRepository;
use HiEvents\Repository\Eloquent\OrderAuditLogRepository;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use HiEvents\Repository\Eloquent\OrderPaymentPlatformFeeRepository;
use HiEvents\Repository\Eloquent\OrderRefundRepository;
use HiEvents\Repository\Eloquent\OrderRepository;
use HiEvents\Repository\Eloquent\OrganizerConfigurationRepository;
use HiEvents\Repository\Eloquent\OrganizerRepository;
use HiEvents\Repository\Eloquent\OrganizerSettingsRepository;
use HiEvents\Repository\Eloquent\OrganizerStripePlatformRepository;
use HiEvents\Repository\Eloquent\OrganizerVatSettingRepository;
use HiEvents\Repository\Eloquent\OutgoingMessageRepository;
use HiEvents\Repository\Eloquent\PasswordResetRepository;
use HiEvents\Repository\Eloquent\PasswordResetTokenRepository;
use HiEvents\Repository\Eloquent\PermissionRepository;
use HiEvents\Repository\Eloquent\PermissionRolePermissionRepository;
use HiEvents\Repository\Eloquent\PermissionRoleRepository;
use HiEvents\Repository\Eloquent\PersonRepository;
use HiEvents\Repository\Eloquent\ProductCategoryRepository;
use HiEvents\Repository\Eloquent\ProductOccurrenceVisibilityRepository;
use HiEvents\Repository\Eloquent\ProductPriceOccurrenceOverrideRepository;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use HiEvents\Repository\Eloquent\ProductRepository;
use HiEvents\Repository\Eloquent\PromoCodeRepository;
use HiEvents\Repository\Eloquent\QuestionAndAnswerViewRepository;
use HiEvents\Repository\Eloquent\QuestionAnswerRepository;
use HiEvents\Repository\Eloquent\QuestionRepository;
use HiEvents\Repository\Eloquent\RoomRepository;
use HiEvents\Repository\Eloquent\RsvpResponseRepository;
use HiEvents\Repository\Eloquent\SeatRepository;
use HiEvents\Repository\Eloquent\SessionAttendanceRepository;
use HiEvents\Repository\Eloquent\SessionProductRepository;
use HiEvents\Repository\Eloquent\SessionRegistrationRepository;
use HiEvents\Repository\Eloquent\SessionRepository;
use HiEvents\Repository\Eloquent\SessionSpeakerRepository;
use HiEvents\Repository\Eloquent\SessionWaitlistEntryRepository;
use HiEvents\Repository\Eloquent\SpeakerRepository;
use HiEvents\Repository\Eloquent\SponsorshipEntitlementRepository;
use HiEvents\Repository\Eloquent\SponsorshipPackageRepository;
use HiEvents\Repository\Eloquent\SponsorshipRepository;
use HiEvents\Repository\Eloquent\StripeCustomerRepository;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\StripePayoutsRepository;
use HiEvents\Repository\Eloquent\TaxAndFeeRepository;
use HiEvents\Repository\Eloquent\TicketLookupTokenRepository;
use HiEvents\Repository\Eloquent\TrackRepository;
use HiEvents\Repository\Eloquent\UserRepository;
use HiEvents\Repository\Eloquent\VenueRepository;
use HiEvents\Repository\Eloquent\WaitlistEntryRepository;
use HiEvents\Repository\Eloquent\WebhookLogRepository;
use HiEvents\Repository\Eloquent\WebhookRepository;
use HiEvents\Repository\Eloquent\ZoneRepository;
use HiEvents\Repository\Interfaces\AccessGrantRepositoryInterface;
use HiEvents\Repository\Interfaces\AccessLogRepositoryInterface;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountMessagingTierRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\AccreditationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;
use HiEvents\Repository\Interfaces\AccreditationTypeRuleRepositoryInterface;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use HiEvents\Repository\Interfaces\ApiKeyRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\BadgePrintJobRepositoryInterface;
use HiEvents\Repository\Interfaces\BadgeRepositoryInterface;
use HiEvents\Repository\Interfaces\BadgeTemplateRepositoryInterface;
use HiEvents\Repository\Interfaces\BoothAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\BoothRepositoryInterface;
use HiEvents\Repository\Interfaces\BuildingRepositoryInterface;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\CompanyRepositoryInterface;
use HiEvents\Repository\Interfaces\CredentialRepositoryInterface;
use HiEvents\Repository\Interfaces\DeviceRepositoryInterface;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventExhibitorRepositoryInterface;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventVenueRepositoryInterface;
use HiEvents\Repository\Interfaces\ExhibitorStaffRepositoryInterface;
use HiEvents\Repository\Interfaces\FloorRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\InvitationRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\LeadCaptureRepositoryInterface;
use HiEvents\Repository\Interfaces\LeadConsentRepositoryInterface;
use HiEvents\Repository\Interfaces\LeadRepositoryInterface;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderApplicationFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\PermissionRepositoryInterface;
use HiEvents\Repository\Interfaces\PermissionRolePermissionRepositoryInterface;
use HiEvents\Repository\Interfaces\PermissionRoleRepositoryInterface;
use HiEvents\Repository\Interfaces\PersonRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAndAnswerViewRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;
use HiEvents\Repository\Interfaces\RsvpResponseRepositoryInterface;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionAttendanceRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionProductRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionRegistrationRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionSpeakerRepositoryInterface;
use HiEvents\Repository\Interfaces\SessionWaitlistEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;
use HiEvents\Repository\Interfaces\SponsorshipEntitlementRepositoryInterface;
use HiEvents\Repository\Interfaces\SponsorshipPackageRepositoryInterface;
use HiEvents\Repository\Interfaces\SponsorshipRepositoryInterface;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePayoutsRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @todo - find a way to auto-bind these
     */
    private static array $interfaceToConcreteMap = [
        UserRepositoryInterface::class => UserRepository::class,
        AccountRepositoryInterface::class => AccountRepository::class,
        AccountAttributionRepositoryInterface::class => AccountAttributionRepository::class,
        AccountDeletionRequestRepositoryInterface::class => AccountDeletionRequestRepository::class,
        EventRepositoryInterface::class => EventRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        AttendeeRepositoryInterface::class => AttendeeRepository::class,
        AffiliateRepositoryInterface::class => AffiliateRepository::class,
        OrderItemRepositoryInterface::class => OrderItemRepository::class,
        QuestionRepositoryInterface::class => QuestionRepository::class,
        QuestionAnswerRepositoryInterface::class => QuestionAnswerRepository::class,
        StripePaymentsRepositoryInterface::class => StripePaymentsRepository::class,
        PromoCodeRepositoryInterface::class => PromoCodeRepository::class,
        MessageRepositoryInterface::class => MessageRepository::class,
        PasswordResetTokenRepositoryInterface::class => PasswordResetTokenRepository::class,
        PasswordResetRepositoryInterface::class => PasswordResetRepository::class,
        TaxAndFeeRepositoryInterface::class => TaxAndFeeRepository::class,
        ImageRepositoryInterface::class => ImageRepository::class,
        ProductPriceRepositoryInterface::class => ProductPriceRepository::class,
        EventStatisticRepositoryInterface::class => EventStatisticRepository::class,
        EventSpamCheckRepositoryInterface::class => EventSpamCheckRepository::class,
        EventDailyStatisticRepositoryInterface::class => EventDailyStatisticRepository::class,
        EventSettingsRepositoryInterface::class => EventSettingsRepository::class,
        OrganizerRepositoryInterface::class => OrganizerRepository::class,
        AccountUserRepositoryInterface::class => AccountUserRepository::class,
        CapacityAssignmentRepositoryInterface::class => CapacityAssignmentRepository::class,
        StripeCustomerRepositoryInterface::class => StripeCustomerRepository::class,
        CheckInListRepositoryInterface::class => CheckInListRepository::class,
        AttendeeCheckInRepositoryInterface::class => AttendeeCheckInRepository::class,
        ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
        InvoiceRepositoryInterface::class => InvoiceRepository::class,
        OrderRefundRepositoryInterface::class => OrderRefundRepository::class,
        WebhookRepositoryInterface::class => WebhookRepository::class,
        WebhookLogRepositoryInterface::class => WebhookLogRepository::class,
        OrderApplicationFeeRepositoryInterface::class => OrderApplicationFeeRepository::class,
        OrderAuditLogRepositoryInterface::class => OrderAuditLogRepository::class,
        OrderPaymentPlatformFeeRepositoryInterface::class => OrderPaymentPlatformFeeRepository::class,
        StripePayoutsRepositoryInterface::class => StripePayoutsRepository::class,
        AccountConfigurationRepositoryInterface::class => AccountConfigurationRepository::class,
        QuestionAndAnswerViewRepositoryInterface::class => QuestionAndAnswerViewRepository::class,
        OutgoingMessageRepositoryInterface::class => OutgoingMessageRepository::class,
        OrganizerSettingsRepositoryInterface::class => OrganizerSettingsRepository::class,
        EmailTemplateRepositoryInterface::class => EmailTemplateRepository::class,
        OrganizerStripePlatformRepositoryInterface::class => OrganizerStripePlatformRepository::class,
        OrganizerVatSettingRepositoryInterface::class => OrganizerVatSettingRepository::class,
        OrganizerConfigurationRepositoryInterface::class => OrganizerConfigurationRepository::class,
        TicketLookupTokenRepositoryInterface::class => TicketLookupTokenRepository::class,
        AccountMessagingTierRepositoryInterface::class => AccountMessagingTierRepository::class,
        AnnouncementRepositoryInterface::class => AnnouncementRepository::class,
        AnnouncementUserRepositoryInterface::class => AnnouncementUserRepository::class,
        WaitlistEntryRepositoryInterface::class => WaitlistEntryRepository::class,
        EventOccurrenceRepositoryInterface::class => EventOccurrenceRepository::class,
        EventOccurrenceStatisticRepositoryInterface::class => EventOccurrenceStatisticRepository::class,
        EventOccurrenceDailyStatisticRepositoryInterface::class => EventOccurrenceDailyStatisticRepository::class,
        ProductOccurrenceVisibilityRepositoryInterface::class => ProductOccurrenceVisibilityRepository::class,
        ProductPriceOccurrenceOverrideRepositoryInterface::class => ProductPriceOccurrenceOverrideRepository::class,
        LocationRepositoryInterface::class => LocationRepository::class,
        EventLocationRepositoryInterface::class => EventLocationRepository::class,
        VenueRepositoryInterface::class => VenueRepository::class,
        BuildingRepositoryInterface::class => BuildingRepository::class,
        FloorRepositoryInterface::class => FloorRepository::class,
        ZoneRepositoryInterface::class => ZoneRepository::class,
        AccessPointRepositoryInterface::class => AccessPointRepository::class,
        RoomRepositoryInterface::class => RoomRepository::class,
        SeatRepositoryInterface::class => SeatRepository::class,
        BoothRepositoryInterface::class => BoothRepository::class,
        EventVenueRepositoryInterface::class => EventVenueRepository::class,
        ApiKeyRepositoryInterface::class => ApiKeyRepository::class,
        CompanyRepositoryInterface::class => CompanyRepository::class,
        EventExhibitorRepositoryInterface::class => EventExhibitorRepository::class,
        ExhibitorStaffRepositoryInterface::class => ExhibitorStaffRepository::class,
        BoothAssignmentRepositoryInterface::class => BoothAssignmentRepository::class,
        LeadRepositoryInterface::class => LeadRepository::class,
        LeadCaptureRepositoryInterface::class => LeadCaptureRepository::class,
        LeadConsentRepositoryInterface::class => LeadConsentRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
        PermissionRoleRepositoryInterface::class => PermissionRoleRepository::class,
        PermissionRolePermissionRepositoryInterface::class => PermissionRolePermissionRepository::class,
        EventUserRepositoryInterface::class => EventUserRepository::class,
        TrackRepositoryInterface::class => TrackRepository::class,
        SpeakerRepositoryInterface::class => SpeakerRepository::class,
        SessionRepositoryInterface::class => SessionRepository::class,
        SessionSpeakerRepositoryInterface::class => SessionSpeakerRepository::class,
        SessionProductRepositoryInterface::class => SessionProductRepository::class,
        SessionRegistrationRepositoryInterface::class => SessionRegistrationRepository::class,
        SessionAttendanceRepositoryInterface::class => SessionAttendanceRepository::class,
        SessionWaitlistEntryRepositoryInterface::class => SessionWaitlistEntryRepository::class,
        PersonRepositoryInterface::class => PersonRepository::class,
        AccreditationTypeRepositoryInterface::class => AccreditationTypeRepository::class,
        AccreditationTypeRuleRepositoryInterface::class => AccreditationTypeRuleRepository::class,
        AccreditationRepositoryInterface::class => AccreditationRepository::class,
        CredentialRepositoryInterface::class => CredentialRepository::class,
        AccessRuleRepositoryInterface::class => AccessRuleRepository::class,
        AccessGrantRepositoryInterface::class => AccessGrantRepository::class,
        AccessLogRepositoryInterface::class => AccessLogRepository::class,
        BadgeTemplateRepositoryInterface::class => BadgeTemplateRepository::class,
        BadgeRepositoryInterface::class => BadgeRepository::class,
        BadgePrintJobRepositoryInterface::class => BadgePrintJobRepository::class,
        DeviceRepositoryInterface::class => DeviceRepository::class,
        InvitationRepositoryInterface::class => InvitationRepository::class,
        SponsorshipRepositoryInterface::class => SponsorshipRepository::class,
        SponsorshipEntitlementRepositoryInterface::class => SponsorshipEntitlementRepository::class,
        SponsorshipPackageRepositoryInterface::class => SponsorshipPackageRepository::class,
        RsvpResponseRepositoryInterface::class => RsvpResponseRepository::class,
    ];

    public function register(): void
    {
        foreach (self::$interfaceToConcreteMap as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
