<?php

use HiEvents\Http\Actions\AccessLog\GetAccessLogsAction;
use HiEvents\Http\Actions\AccessLog\RecordAccessScanAction;
use HiEvents\Http\Actions\AccessLog\SimulateAccessScanAction;
use HiEvents\Http\Actions\AccessPoint\CreateAccessPointAction;
use HiEvents\Http\Actions\AccessPoint\DeleteAccessPointAction;
use HiEvents\Http\Actions\AccessPoint\GetAccessPointAction;
use HiEvents\Http\Actions\AccessPoint\GetAccessPointsAction;
use HiEvents\Http\Actions\AccessPoint\UpdateAccessPointAction;
use HiEvents\Http\Actions\AccessRule\CreateAccessRuleAction;
use HiEvents\Http\Actions\AccessRule\DeleteAccessRuleAction;
use HiEvents\Http\Actions\AccessRule\GetAccessRuleAction;
use HiEvents\Http\Actions\AccessRule\GetAccessRulesAction;
use HiEvents\Http\Actions\AccessRule\UpdateAccessRuleAction;
use HiEvents\Http\Actions\Accounts\CreateAccountAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\CancelAccountDeletionAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\GetAccountDeletionStatusAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\RequestAccountDeletionAction;
use HiEvents\Http\Actions\Accounts\GetAccountAction;
use HiEvents\Http\Actions\Accounts\UpdateAccountAction;
use HiEvents\Http\Actions\Accreditation\ApproveAccreditationAction;
use HiEvents\Http\Actions\Accreditation\GetAccreditationAuditTrailAction;
use HiEvents\Http\Actions\Accreditation\IssueAccreditationCredentialAction;
use HiEvents\Http\Actions\Accreditation\RejectAccreditationAction;
use HiEvents\Http\Actions\Accreditation\SubmitAccreditationAction;
use HiEvents\Http\Actions\AccreditationType\CreateAccreditationTypeAction;
use HiEvents\Http\Actions\AccreditationType\DeleteAccreditationTypeAction;
use HiEvents\Http\Actions\AccreditationType\GetAccreditationTypeAction;
use HiEvents\Http\Actions\AccreditationType\GetAccreditationTypesAction;
use HiEvents\Http\Actions\AccreditationType\UpdateAccreditationTypeAction;
use HiEvents\Http\Actions\Admin\Accounts\GetAccountAction as GetAdminAccountAction;
use HiEvents\Http\Actions\Admin\Accounts\GetAllAccountsAction as GetAllAdminAccountsAction;
use HiEvents\Http\Actions\Admin\Accounts\UpdateAccountMessagingTierAction;
use HiEvents\Http\Actions\Admin\Accounts\UpdateAccountVerificationAction;
use HiEvents\Http\Actions\Admin\Announcements\CreateAnnouncementAction;
use HiEvents\Http\Actions\Admin\Announcements\DeleteAnnouncementAction;
use HiEvents\Http\Actions\Admin\Announcements\GetAllAnnouncementsAction;
use HiEvents\Http\Actions\Admin\Announcements\UpdateAnnouncementAction;
use HiEvents\Http\Actions\Admin\Attribution\GetUtmAttributionStatsAction;
use HiEvents\Http\Actions\Admin\Configurations\CreateConfigurationAction;
use HiEvents\Http\Actions\Admin\Configurations\DeleteConfigurationAction;
use HiEvents\Http\Actions\Admin\Configurations\GetAllConfigurationsAction;
use HiEvents\Http\Actions\Admin\Configurations\UpdateConfigurationAction;
use HiEvents\Http\Actions\Admin\DeletionRequests\AdminCancelAccountDeletionAction;
use HiEvents\Http\Actions\Admin\DeletionRequests\AdminExecuteAccountDeletionAction;
use HiEvents\Http\Actions\Admin\DeletionRequests\AdminRequestAccountDeletionAction;
use HiEvents\Http\Actions\Admin\DeletionRequests\GetAllAccountDeletionRequestsAction;
use HiEvents\Http\Actions\Admin\Events\GetAllEventsAction as GetAllAdminEventsAction;
use HiEvents\Http\Actions\Admin\Events\GetUpcomingEventsAction;
use HiEvents\Http\Actions\Admin\FailedJobs\DeleteAllFailedJobsAction;
use HiEvents\Http\Actions\Admin\FailedJobs\DeleteFailedJobAction;
use HiEvents\Http\Actions\Admin\FailedJobs\GetAllFailedJobsAction;
use HiEvents\Http\Actions\Admin\FailedJobs\RetryAllFailedJobsAction;
use HiEvents\Http\Actions\Admin\FailedJobs\RetryFailedJobAction;
use HiEvents\Http\Actions\Admin\GetMessagingTiersAction;
use HiEvents\Http\Actions\Admin\GetSystemInfoAction;
use HiEvents\Http\Actions\Admin\Messages\ApproveMessageAction;
use HiEvents\Http\Actions\Admin\Messages\GetAllMessagesAction as GetAllAdminMessagesAction;
use HiEvents\Http\Actions\Admin\Messages\RejectMessageAction;
use HiEvents\Http\Actions\Admin\Orders\GetAllOrdersAction;
use HiEvents\Http\Actions\Admin\Organizers\AssignOrganizerConfigurationAction;
use HiEvents\Http\Actions\Admin\Organizers\UpdateOrganizerConfigurationAction;
use HiEvents\Http\Actions\Admin\Organizers\UpdateOrganizerVatSettingAction;
use HiEvents\Http\Actions\Admin\SpamEvents\ApproveSpamEventAction;
use HiEvents\Http\Actions\Admin\SpamEvents\ConfirmSpamEventAction;
use HiEvents\Http\Actions\Admin\SpamEvents\GetAllSpamEventsAction;
use HiEvents\Http\Actions\Admin\Stats\GetAdminDashboardDataAction;
use HiEvents\Http\Actions\Admin\Stats\GetAdminStatsAction;
use HiEvents\Http\Actions\Admin\Users\GetAllUsersAction;
use HiEvents\Http\Actions\Admin\Users\StartImpersonationAction;
use HiEvents\Http\Actions\Admin\Users\StopImpersonationAction;
use HiEvents\Http\Actions\Affiliates\CreateAffiliateAction;
use HiEvents\Http\Actions\Affiliates\DeleteAffiliateAction;
use HiEvents\Http\Actions\Affiliates\ExportAffiliatesAction;
use HiEvents\Http\Actions\Affiliates\GetAffiliateAction;
use HiEvents\Http\Actions\Affiliates\GetAffiliatesAction;
use HiEvents\Http\Actions\Affiliates\UpdateAffiliateAction;
use HiEvents\Http\Actions\Analytics\GetAttendanceReportAction;
use HiEvents\Http\Actions\Analytics\GetDemographicsAction;
use HiEvents\Http\Actions\Analytics\GetSessionAnalyticsAction;
use HiEvents\Http\Actions\Analytics\GetZoneDwellTimeAction;
use HiEvents\Http\Actions\Announcements\DismissAnnouncementAction;
use HiEvents\Http\Actions\Announcements\GetActiveAnnouncementsAction;
use HiEvents\Http\Actions\ApiKey\CreateApiKeyAction;
use HiEvents\Http\Actions\ApiKey\GetApiKeysAction;
use HiEvents\Http\Actions\ApiKey\RevokeApiKeyAction;
use HiEvents\Http\Actions\Attendees\CheckInAttendeeAction;
use HiEvents\Http\Actions\Attendees\CreateAttendeeAction;
use HiEvents\Http\Actions\Attendees\EditAttendeeAction;
use HiEvents\Http\Actions\Attendees\ExportAttendeesAction;
use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Http\Actions\Attendees\GetAttendeeActionPublic;
use HiEvents\Http\Actions\Attendees\GetAttendeesAction;
use HiEvents\Http\Actions\Attendees\PartialEditAttendeeAction;
use HiEvents\Http\Actions\Attendees\ResendAttendeeTicketAction;
use HiEvents\Http\Actions\Auth\AcceptInvitationAction;
use HiEvents\Http\Actions\Auth\ForgotPasswordAction;
use HiEvents\Http\Actions\Auth\GetUserInvitationAction;
use HiEvents\Http\Actions\Auth\LoginAction;
use HiEvents\Http\Actions\Auth\LogoutAction;
use HiEvents\Http\Actions\Auth\RefreshTokenAction;
use HiEvents\Http\Actions\Auth\ResetPasswordAction;
use HiEvents\Http\Actions\Auth\ValidateResetPasswordTokenAction;
use HiEvents\Http\Actions\Badge\CapturePersonPhotoAction;
use HiEvents\Http\Actions\Badge\DeletePersonPhotoAction;
use HiEvents\Http\Actions\CapacityAssignments\CreateCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\DeleteCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentsAction;
use HiEvents\Http\Actions\CapacityAssignments\UpdateCapacityAssignmentAction;
use HiEvents\Http\Actions\CheckInLists\CreateCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\DeleteCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListsAction;
use HiEvents\Http\Actions\CheckInLists\Public\CreateAttendeeCheckInPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\DeleteAttendeeCheckInPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListAttendeeDetailPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListAttendeePublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListAttendeesPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListStatsPublicAction;
use HiEvents\Http\Actions\CheckInLists\UpdateCheckInListAction;
use HiEvents\Http\Actions\CommandCentre\GetCommandCentreSnapshotAction;
use HiEvents\Http\Actions\Common\GetColorThemesAction;
use HiEvents\Http\Actions\Common\Webhooks\StripeIncomingWebhookAction;
use HiEvents\Http\Actions\Credential\GetCredentialsAction;
use HiEvents\Http\Actions\Credential\IssueCredentialAction;
use HiEvents\Http\Actions\Credential\RevokeCredentialAction;
use HiEvents\Http\Actions\Device\GetFleetStatusAction;
use HiEvents\Http\Actions\Device\GetReconciliationFindingsAction;
use HiEvents\Http\Actions\Device\HeartbeatDeviceAction;
use HiEvents\Http\Actions\Device\PairDeviceAction;
use HiEvents\Http\Actions\Device\RegisterDeviceAction;
use HiEvents\Http\Actions\Device\ReviewReconciliationFindingAction;
use HiEvents\Http\Actions\Device\SuspendDeviceAction;
use HiEvents\Http\Actions\Device\SyncDeviceAction;
use HiEvents\Http\Actions\EmailTemplates\CreateEventEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\CreateOrganizerEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\DeleteEventEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\DeleteOrganizerEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\GetAvailableTokensAction;
use HiEvents\Http\Actions\EmailTemplates\GetDefaultEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\GetEventEmailTemplatesAction;
use HiEvents\Http\Actions\EmailTemplates\GetOrganizerEmailTemplatesAction;
use HiEvents\Http\Actions\EmailTemplates\PreviewEventEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\PreviewOrganizerEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\UpdateEventEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\UpdateOrganizerEmailTemplateAction;
use HiEvents\Http\Actions\EventOccurrences\BulkUpdateOccurrencesAction;
use HiEvents\Http\Actions\EventOccurrences\CancelOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\CreateEventOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\DeleteEventOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\DeletePriceOverrideAction;
use HiEvents\Http\Actions\EventOccurrences\GenerateOccurrencesAction;
use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesAction;
use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesPublicAction;
use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceGenerationStatusAction;
use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceProductAvailabilityAction;
use HiEvents\Http\Actions\EventOccurrences\GetPriceOverridesAction;
use HiEvents\Http\Actions\EventOccurrences\GetProductVisibilityAction;
use HiEvents\Http\Actions\EventOccurrences\ReactivateOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\UpdateEventOccurrenceAction;
use HiEvents\Http\Actions\EventOccurrences\UpdateProductVisibilityAction;
use HiEvents\Http\Actions\EventOccurrences\UpsertPriceOverrideAction;
use HiEvents\Http\Actions\Events\CreateEventAction;
use HiEvents\Http\Actions\Events\DeleteEventAction;
use HiEvents\Http\Actions\Events\DuplicateEventAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Events\GetEventDeletionStatusAction;
use HiEvents\Http\Actions\Events\GetEventPublicAction;
use HiEvents\Http\Actions\Events\GetEventsAction;
use HiEvents\Http\Actions\Events\GetOrganizerEventsPublicAction;
use HiEvents\Http\Actions\Events\Images\CreateEventImageAction;
use HiEvents\Http\Actions\Events\Images\DeleteEventImageAction;
use HiEvents\Http\Actions\Events\Images\GetEventImagesAction;
use HiEvents\Http\Actions\Events\Stats\GetEventCountsAction;
use HiEvents\Http\Actions\Events\Stats\GetEventStatsAction;
use HiEvents\Http\Actions\Events\UpdateEventAction;
use HiEvents\Http\Actions\Events\UpdateEventLocationAction;
use HiEvents\Http\Actions\Events\UpdateEventStatusAction;
use HiEvents\Http\Actions\EventSettings\EditEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\GetEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\GetPlatformFeePreviewAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use HiEvents\Http\Actions\EventUser\GetEventUsersAction;
use HiEvents\Http\Actions\EventUser\GrantEventRoleAction;
use HiEvents\Http\Actions\EventUser\RevokeEventRoleAction;
use HiEvents\Http\Actions\Exhibitor\AssignBoothAction;
use HiEvents\Http\Actions\Exhibitor\CaptureLeadAction;
use HiEvents\Http\Actions\Exhibitor\GetEventExhibitorsAction;
use HiEvents\Http\Actions\Exhibitor\GetLeadCaptureStatsAction;
use HiEvents\Http\Actions\Exhibitor\GetLeadsAction;
use HiEvents\Http\Actions\Exhibitor\GetLeadScoresAction;
use HiEvents\Http\Actions\Exhibitor\NameExhibitorStaffAction;
use HiEvents\Http\Actions\Exhibitor\ReleaseBoothAction;
use HiEvents\Http\Actions\Exhibitor\UpdateLeadAction;
use HiEvents\Http\Actions\Exhibitor\UpsertEventExhibitorAction;
use HiEvents\Http\Actions\Exhibitor\WithdrawExhibitorStaffAction;
use HiEvents\Http\Actions\Images\CreateImageAction;
use HiEvents\Http\Actions\Images\DeleteImageAction;
use HiEvents\Http\Actions\Locations\CreateLocationAction;
use HiEvents\Http\Actions\Locations\DeleteLocationAction;
use HiEvents\Http\Actions\Locations\GeoAutocompleteAction;
use HiEvents\Http\Actions\Locations\GeoPlaceDetailsAction;
use HiEvents\Http\Actions\Locations\GetGeoStatusAction;
use HiEvents\Http\Actions\Locations\GetLocationsAction;
use HiEvents\Http\Actions\Locations\UpdateLocationAction;
use HiEvents\Http\Actions\Messages\CancelMessageAction;
use HiEvents\Http\Actions\Messages\GetMessageRecipientsAction;
use HiEvents\Http\Actions\Messages\GetMessagesAction;
use HiEvents\Http\Actions\Messages\SendMessageAction;
use HiEvents\Http\Actions\Mfa\BeginMfaEnrolmentAction;
use HiEvents\Http\Actions\Mfa\ConfirmMfaEnrolmentAction;
use HiEvents\Http\Actions\Mfa\DisableMfaAction;
use HiEvents\Http\Actions\Mfa\GetMfaStatusAction;
use HiEvents\Http\Actions\Networking\CancelMeetingAction;
use HiEvents\Http\Actions\Networking\GetNetworkingDirectoryAction;
use HiEvents\Http\Actions\Networking\GetPersonMeetingsAction;
use HiEvents\Http\Actions\Networking\RequestMeetingAction;
use HiEvents\Http\Actions\Networking\RespondToMeetingAction;
use HiEvents\Http\Actions\Notification\GetPushHealthAction;
use HiEvents\Http\Actions\Notification\RegisterPushSubscriptionAction;
use HiEvents\Http\Actions\Notification\RevokePushSubscriptionAction;
use HiEvents\Http\Actions\Operations\AssignShiftAction;
use HiEvents\Http\Actions\Operations\DecideReadinessReviewAction;
use HiEvents\Http\Actions\Operations\GetIncidentSummaryAction;
use HiEvents\Http\Actions\Operations\GetOutstandingBlockersAction;
use HiEvents\Http\Actions\Operations\GetStaffingGapsAction;
use HiEvents\Http\Actions\Operations\InstantiateTaskTemplateAction;
use HiEvents\Http\Actions\Operations\OpenReadinessReviewAction;
use HiEvents\Http\Actions\Operations\ReportIncidentAction;
use HiEvents\Http\Actions\Operations\TransitionIncidentAction;
use HiEvents\Http\Actions\Orders\CancelOrderAction;
use HiEvents\Http\Actions\Orders\DownloadOrderInvoiceAction;
use HiEvents\Http\Actions\Orders\EditOrderAction;
use HiEvents\Http\Actions\Orders\ExportOrdersAction;
use HiEvents\Http\Actions\Orders\GetOrderAction;
use HiEvents\Http\Actions\Orders\GetOrdersAction;
use HiEvents\Http\Actions\Orders\MarkOrderAsPaidAction;
use HiEvents\Http\Actions\Orders\MessageOrderAction;
use HiEvents\Http\Actions\Orders\Payment\RefundOrderAction;
use HiEvents\Http\Actions\Orders\Payment\Stripe\CreatePaymentIntentActionPublic;
use HiEvents\Http\Actions\Orders\Payment\Stripe\GetPaymentIntentActionPublic;
use HiEvents\Http\Actions\Orders\Public\AbandonOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\CompleteOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\CreateOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\DownloadOrderInvoicePublicAction;
use HiEvents\Http\Actions\Orders\Public\GetOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\TransitionOrderToOfflinePaymentPublicAction;
use HiEvents\Http\Actions\Orders\ResendOrderConfirmationAction;
use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Http\Actions\Organizers\DeleteOrganizerAction;
use HiEvents\Http\Actions\Organizers\EditOrganizerAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerDeletionStatusAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerEventsAction;
use HiEvents\Http\Actions\Organizers\GetOrganizersAction;
use HiEvents\Http\Actions\Organizers\GetPublicOrganizerAction;
use HiEvents\Http\Actions\Organizers\Orders\GetOrganizerOrdersAction;
use HiEvents\Http\Actions\Organizers\Public\SendOrganizerContactMessagePublicAction;
use HiEvents\Http\Actions\Organizers\Settings\GetOrganizerSettingsAction;
use HiEvents\Http\Actions\Organizers\Settings\PartialUpdateOrganizerSettingsAction;
use HiEvents\Http\Actions\Organizers\Stats\GetOrganizerStatsAction;
use HiEvents\Http\Actions\Organizers\Stripe\CopyStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\CreateStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\DisconnectStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\GetStripeConnectAccountsAction;
use HiEvents\Http\Actions\Organizers\UpdateOrganizerLocationAction;
use HiEvents\Http\Actions\Organizers\UpdateOrganizerStatusAction;
use HiEvents\Http\Actions\Organizers\Vat\GetOrganizerVatSettingAction;
use HiEvents\Http\Actions\Organizers\Vat\UpsertOrganizerVatSettingAction;
use HiEvents\Http\Actions\Organizers\Webhooks\CreateOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\DeleteOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\EditOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhookLogsAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhooksAction;
use HiEvents\Http\Actions\ProductCategories\CreateProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\DeleteProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\EditProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoryAction;
use HiEvents\Http\Actions\Products\CreateProductAction;
use HiEvents\Http\Actions\Products\DeleteProductAction;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
use HiEvents\Http\Actions\Products\GetProductsAction;
use HiEvents\Http\Actions\Products\SortProductsAction;
use HiEvents\Http\Actions\PromoCodes\CreatePromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\DeletePromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodePublic;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodesAction;
use HiEvents\Http\Actions\PromoCodes\UpdatePromoCodeAction;
use HiEvents\Http\Actions\Questions\CreateQuestionAction;
use HiEvents\Http\Actions\Questions\DeleteQuestionAction;
use HiEvents\Http\Actions\Questions\EditQuestionAction;
use HiEvents\Http\Actions\Questions\EditQuestionAnswerAction;
use HiEvents\Http\Actions\Questions\ExportQuestionAnswersAction;
use HiEvents\Http\Actions\Questions\GetQuestionAction;
use HiEvents\Http\Actions\Questions\GetQuestionsAction;
use HiEvents\Http\Actions\Questions\GetQuestionsPublicAction;
use HiEvents\Http\Actions\Questions\SortQuestionsAction;
use HiEvents\Http\Actions\Queue\GetAccessPointQueueAction;
use HiEvents\Http\Actions\Queue\GetEventQueuesAction;
use HiEvents\Http\Actions\Raffle\CreateRaffleAction;
use HiEvents\Http\Actions\Raffle\DrawRaffleAction;
use HiEvents\Http\Actions\Raffle\GetRafflePoolAction;
use HiEvents\Http\Actions\Raffle\GetRafflesAction;
use HiEvents\Http\Actions\Raffle\RecordRaffleClaimAction;
use HiEvents\Http\Actions\Raffle\VerifyRaffleDrawAction;
use HiEvents\Http\Actions\Reports\ExportOrganizerReportAction;
use HiEvents\Http\Actions\Reports\GetOrganizerReportAction;
use HiEvents\Http\Actions\Reports\GetReportAction;
use HiEvents\Http\Actions\Room\CreateRoomAction;
use HiEvents\Http\Actions\Room\DeleteRoomAction;
use HiEvents\Http\Actions\Room\GetRoomAction;
use HiEvents\Http\Actions\Room\GetRoomsAction;
use HiEvents\Http\Actions\Room\UpdateRoomAction;
use HiEvents\Http\Actions\Rsvp\CreateInvitationAction;
use HiEvents\Http\Actions\Rsvp\GetGuestListAction;
use HiEvents\Http\Actions\Rsvp\RespondToInvitationPublicAction;
use HiEvents\Http\Actions\Rsvp\RevokeInvitationAction;
use HiEvents\Http\Actions\SelfService\EditAttendeePublicAction;
use HiEvents\Http\Actions\SelfService\EditOrderPublicAction;
use HiEvents\Http\Actions\SelfService\ResendAttendeeTicketPublicAction;
use HiEvents\Http\Actions\SelfService\ResendOrderConfirmationPublicAction;
use HiEvents\Http\Actions\Session\CancelSessionRegistrationAction;
use HiEvents\Http\Actions\Session\CreateSessionAction;
use HiEvents\Http\Actions\Session\DeleteSessionAction;
use HiEvents\Http\Actions\Session\ExportEventProgrammeIcsAction;
use HiEvents\Http\Actions\Session\ExportSessionIcsAction;
use HiEvents\Http\Actions\Session\GetAttendeeAgendaAction;
use HiEvents\Http\Actions\Session\GetSessionAction;
use HiEvents\Http\Actions\Session\GetSessionRegistrationsAction;
use HiEvents\Http\Actions\Session\GetSessionsAction;
use HiEvents\Http\Actions\Session\GetSessionStatsAction;
use HiEvents\Http\Actions\Session\RecordSessionAttendanceAction;
use HiEvents\Http\Actions\Session\RegisterForSessionAction;
use HiEvents\Http\Actions\Session\UpdateSessionAction;
use HiEvents\Http\Actions\Sitemap\GetSitemapEventsAction;
use HiEvents\Http\Actions\Sitemap\GetSitemapIndexAction;
use HiEvents\Http\Actions\Sitemap\GetSitemapOrganizersAction;
use HiEvents\Http\Actions\Speaker\CreateSpeakerAction;
use HiEvents\Http\Actions\Speaker\DeleteSpeakerAction;
use HiEvents\Http\Actions\Speaker\GetSpeakerAction;
use HiEvents\Http\Actions\Speaker\GetSpeakersAction;
use HiEvents\Http\Actions\Speaker\UpdateSpeakerAction;
use HiEvents\Http\Actions\Sponsor\AddSponsorshipEntitlementAction;
use HiEvents\Http\Actions\Sponsor\CreateSponsorshipAction;
use HiEvents\Http\Actions\Sponsor\CreateSponsorshipPackageAction;
use HiEvents\Http\Actions\Sponsor\GetPublicSponsorsAction;
use HiEvents\Http\Actions\Sponsor\GetSponsorshipFulfilmentAction;
use HiEvents\Http\Actions\Sponsor\GetSponsorshipsAction;
use HiEvents\Http\Actions\Sponsor\RecordEntitlementFulfilmentAction;
use HiEvents\Http\Actions\Sponsor\UpdateSponsorshipAction;
use HiEvents\Http\Actions\TaxesAndFees\CreateTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\DeleteTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\EditTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\GetTaxOrFeeAction;
use HiEvents\Http\Actions\TicketLookup\GetOrdersByLookupTokenAction;
use HiEvents\Http\Actions\TicketLookup\SendTicketLookupEmailAction;
use HiEvents\Http\Actions\Track\CreateTrackAction;
use HiEvents\Http\Actions\Track\DeleteTrackAction;
use HiEvents\Http\Actions\Track\GetTrackAction;
use HiEvents\Http\Actions\Track\GetTracksAction;
use HiEvents\Http\Actions\Track\UpdateTrackAction;
use HiEvents\Http\Actions\Users\CancelEmailChangeAction;
use HiEvents\Http\Actions\Users\ConfirmEmailAddressAction;
use HiEvents\Http\Actions\Users\ConfirmEmailChangeAction;
use HiEvents\Http\Actions\Users\ConfirmEmailWithCodeAction;
use HiEvents\Http\Actions\Users\CreateUserAction;
use HiEvents\Http\Actions\Users\DeleteInvitationAction;
use HiEvents\Http\Actions\Users\GetMeAction;
use HiEvents\Http\Actions\Users\GetUserAction;
use HiEvents\Http\Actions\Users\GetUsersAction;
use HiEvents\Http\Actions\Users\ResendEmailConfirmationAction;
use HiEvents\Http\Actions\Users\ResendInvitationAction;
use HiEvents\Http\Actions\Users\UpdateMeAction;
use HiEvents\Http\Actions\Users\UpdateUserAction;
use HiEvents\Http\Actions\V1\GetV1EventAttendeesAction;
use HiEvents\Http\Actions\Venue\CreateVenueAction;
use HiEvents\Http\Actions\Venue\DeleteVenueAction;
use HiEvents\Http\Actions\Venue\GetVenueAction;
use HiEvents\Http\Actions\Venue\GetVenuesAction;
use HiEvents\Http\Actions\Venue\UpdateVenueAction;
use HiEvents\Http\Actions\Waitlist\Organizer\CancelWaitlistEntryAction;
use HiEvents\Http\Actions\Waitlist\Organizer\GetWaitlistEntriesAction;
use HiEvents\Http\Actions\Waitlist\Organizer\GetWaitlistStatsAction;
use HiEvents\Http\Actions\Waitlist\Organizer\OfferWaitlistEntryAction;
use HiEvents\Http\Actions\Waitlist\Public\CancelWaitlistEntryActionPublic;
use HiEvents\Http\Actions\Waitlist\Public\CreateWaitlistEntryActionPublic;
use HiEvents\Http\Actions\Webhooks\CreateWebhookAction;
use HiEvents\Http\Actions\Webhooks\DeleteWebhookAction;
use HiEvents\Http\Actions\Webhooks\EditWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookLogsAction;
use HiEvents\Http\Actions\Webhooks\GetWebhooksAction;
use HiEvents\Http\Actions\Zone\CreateZoneAction;
use HiEvents\Http\Actions\Zone\DeleteZoneAction;
use HiEvents\Http\Actions\Zone\GetZoneAction;
use HiEvents\Http\Actions\Zone\GetZonesAction;
use HiEvents\Http\Actions\Zone\UpdateZoneAction;
use Illuminate\Routing\Router;

/** @var Router|Router $router */
$router = app()->get('router');

$router->prefix('/auth')->group(
    function (Router $router): void {
        // Auth
        $router->post('/login', LoginAction::class)->name('auth.login');
        $router->post('/logout', LogoutAction::class)->name('auth.logout');
        $router->post('/register', CreateAccountAction::class)->name('auth.register');
        $router->post('/forgot-password', ForgotPasswordAction::class)->name('auth.forgot-password');

        // Invitations
        $router->get('/invitation/{invite_token}', GetUserInvitationAction::class)->name('auth.invitation');
        $router->post('/invitation/{invite_token}', AcceptInvitationAction::class)->name('auth.accept-invitation');

        // Reset Passwords
        $router->get('/reset-password/{reset_token}', ValidateResetPasswordTokenAction::class)->name('auth.validate-reset-password-token');
        $router->post('/reset-password/{reset_token}', ResetPasswordAction::class)->name('auth.reset-password');
    }
);

/**
 * Logged In Routes
 */
$router->middleware(['auth:api'])->group(
    function (Router $router): void {
        // Auth
        $router->get('/auth/logout', LogoutAction::class);
        $router->post('/auth/refresh', RefreshTokenAction::class);

        // Users
        $router->get('/users/me', GetMeAction::class);
        $router->put('/users/me', UpdateMeAction::class);
        $router->post('/users', CreateUserAction::class);
        $router->get('/users', GetUsersAction::class);
        $router->get('/users/{user_id}', GetUserAction::class);
        $router->put('/users/{user_id}', UpdateUserAction::class);
        $router->post('/users/{user_id}/email-change/{changeToken}', ConfirmEmailChangeAction::class);
        $router->post('/users/{user_id}/invitation', ResendInvitationAction::class);
        $router->delete('/users/{user_id}/invitation', DeleteInvitationAction::class);
        $router->delete('/users/{user_id}/email-change', CancelEmailChangeAction::class);
        $router->post('/users/{user_id}/confirm-email/{resetToken}', ConfirmEmailAddressAction::class);
        $router->post('/users/{user_id}/resend-email-confirmation', ResendEmailConfirmationAction::class);
        $router->post('/users/{user_id}/confirm-email-with-code', ConfirmEmailWithCodeAction::class);

        // Announcements
        $router->get('/announcements/active', GetActiveAnnouncementsAction::class);
        $router->post('/announcements/{announcement_id}/dismiss', DismissAnnouncementAction::class);

        // Accounts
        $router->post('/accounts/deletion-request', RequestAccountDeletionAction::class);
        $router->delete('/accounts/deletion-request', CancelAccountDeletionAction::class);
        $router->get('/accounts/deletion-request', GetAccountDeletionStatusAction::class);
        $router->get('/accounts/{account_id?}', GetAccountAction::class);
        $router->put('/accounts/{account_id?}', UpdateAccountAction::class);

        // Organizers
        $router->post('/organizers', CreateOrganizerAction::class);
        // This is POST instead of PUT because you can't upload files via PUT in PHP (at least not easily)
        $router->post('/organizers/{organizer_id}', EditOrganizerAction::class);
        $router->put('/organizers/{organizer_id}/status', UpdateOrganizerStatusAction::class);
        $router->delete('/organizers/{organizer_id}', DeleteOrganizerAction::class);
        $router->get('/organizers/{organizer_id}/deletion-status', GetOrganizerDeletionStatusAction::class);
        $router->get('/organizers', GetOrganizersAction::class);
        $router->get('/organizers/{organizer_id}', GetOrganizerAction::class);
        $router->get('/organizers/{organizer_id}/events', GetOrganizerEventsAction::class);
        $router->get('/organizers/{organizer_id}/stats', GetOrganizerStatsAction::class);
        $router->get('/organizers/{organizer_id}/orders', GetOrganizerOrdersAction::class);
        $router->get('/organizers/{organizer_id}/settings', GetOrganizerSettingsAction::class);
        $router->patch('/organizers/{organizer_id}/settings', PartialUpdateOrganizerSettingsAction::class);
        $router->patch('/organizers/{organizer_id}/location', UpdateOrganizerLocationAction::class);
        $router->get('/organizers/{organizer_id}/reports/{report_type}', GetOrganizerReportAction::class);
        $router->get('/organizers/{organizer_id}/reports/{report_type}/export', ExportOrganizerReportAction::class);
        $router->post('/organizers/{organizer_id}/webhooks', CreateOrganizerWebhookAction::class);
        $router->get('/organizers/{organizer_id}/webhooks', GetOrganizerWebhooksAction::class);
        $router->put('/organizers/{organizer_id}/webhooks/{webhook_id}', EditOrganizerWebhookAction::class);
        $router->get('/organizers/{organizer_id}/webhooks/{webhook_id}', GetOrganizerWebhookAction::class);
        $router->delete('/organizers/{organizer_id}/webhooks/{webhook_id}', DeleteOrganizerWebhookAction::class);
        $router->get('/organizers/{organizer_id}/webhooks/{webhook_id}/logs', GetOrganizerWebhookLogsAction::class);

        // Locations - Organizer level
        $router->get('/organizers/{organizer_id}/locations', GetLocationsAction::class);
        $router->post('/organizers/{organizer_id}/locations', CreateLocationAction::class);
        $router->get('/geo/status', GetGeoStatusAction::class);
        $router->get('/organizers/{organizer_id}/locations/autocomplete', GeoAutocompleteAction::class)
            ->middleware('throttle:60,1');
        $router->get('/organizers/{organizer_id}/locations/places/{place_id}', GeoPlaceDetailsAction::class)
            ->where('place_id', '[A-Za-z0-9_\-]+')
            ->middleware('throttle:60,1');
        $router->put('/organizers/{organizer_id}/locations/{location_id}', UpdateLocationAction::class);
        $router->delete('/organizers/{organizer_id}/locations/{location_id}', DeleteLocationAction::class);

        // Stripe Connect - Organizer level
        $router->get('/organizers/{organizerId}/stripe/connect_accounts', GetStripeConnectAccountsAction::class);
        $router->post('/organizers/{organizerId}/stripe/connect', CreateStripeConnectAccountAction::class);
        $router->post('/organizers/{organizerId}/stripe/copy_from/{sourceOrganizerId}', CopyStripeConnectAccountAction::class);
        $router->delete('/organizers/{organizerId}/stripe/connect_accounts/{stripeAccountId}', DisconnectStripeConnectAccountAction::class)
            ->where('stripeAccountId', '[A-Za-z0-9_]+');

        // VAT Settings - Organizer level
        $router->get('/organizers/{organizerId}/vat-settings', GetOrganizerVatSettingAction::class);
        $router->post('/organizers/{organizerId}/vat-settings', UpsertOrganizerVatSettingAction::class);

        // Email Templates - Organizer level
        $router->get('/organizers/{organizerId}/email-templates', GetOrganizerEmailTemplatesAction::class);
        $router->get('/email-templates/defaults', GetDefaultEmailTemplateAction::class);
        $router->post('/organizers/{organizerId}/email-templates', CreateOrganizerEmailTemplateAction::class);
        $router->put('/organizers/{organizerId}/email-templates/{templateId}', UpdateOrganizerEmailTemplateAction::class);
        $router->delete('/organizers/{organizerId}/email-templates/{templateId}', DeleteOrganizerEmailTemplateAction::class);
        $router->post('/organizers/{organizerId}/email-templates/preview', PreviewOrganizerEmailTemplateAction::class);
        $router->get('/email-templates/tokens/{templateType}', GetAvailableTokensAction::class);

        // Taxes and Fees
        $router->post('/accounts/{account_id}/taxes-and-fees', CreateTaxOrFeeAction::class);
        $router->get('/accounts/{account_id}/taxes-and-fees', GetTaxOrFeeAction::class);
        $router->put('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', EditTaxOrFeeAction::class);
        $router->delete('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', DeleteTaxOrFeeAction::class);

        // Events
        $router->post('/events', CreateEventAction::class);
        $router->get('/events', GetEventsAction::class);
        $router->get('/events/{event_id}', GetEventAction::class);
        $router->put('/events/{event_id}', UpdateEventAction::class);
        $router->patch('/events/{event_id}/event-location', UpdateEventLocationAction::class);
        $router->put('/events/{event_id}/status', UpdateEventStatusAction::class);
        $router->delete('/events/{event_id}', DeleteEventAction::class);
        $router->get('/events/{event_id}/deletion-status', GetEventDeletionStatusAction::class);
        $router->post('/events/{event_id}/duplicate', DuplicateEventAction::class);

        // Product Categories
        $router->post('/events/{event_id}/product-categories', CreateProductCategoryAction::class);
        $router->get('/events/{event_id}/product-categories', GetProductCategoriesAction::class);
        $router->get('/events/{event_id}/product-categories/{category_id}', GetProductCategoryAction::class);
        $router->put('/events/{event_id}/product-categories/{category_id}', EditProductCategoryAction::class);
        $router->delete('/events/{event_id}/product-categories/{category_id}', DeleteProductCategoryAction::class);

        // Products
        $router->post('/events/{event_id}/products', CreateProductAction::class);
        $router->post('/events/{event_id}/products/sort', SortProductsAction::class);
        $router->put('/events/{event_id}/products/{ticket_id}', EditProductAction::class);
        $router->get('/events/{event_id}/products/{ticket_id}', GetProductAction::class);
        $router->delete('/events/{event_id}/products/{ticket_id}', DeleteProductAction::class);
        $router->get('/events/{event_id}/products', GetProductsAction::class);

        // Stats
        $router->get('/events/{event_id}/stats', GetEventStatsAction::class);
        $router->get('/events/{event_id}/counts', GetEventCountsAction::class);

        // Email Templates - Event level
        $router->get('/events/{eventId}/email-templates', GetEventEmailTemplatesAction::class);
        $router->post('/events/{eventId}/email-templates', CreateEventEmailTemplateAction::class);
        $router->put('/events/{eventId}/email-templates/{templateId}', UpdateEventEmailTemplateAction::class);
        $router->delete('/events/{eventId}/email-templates/{templateId}', DeleteEventEmailTemplateAction::class);
        $router->post('/events/{eventId}/email-templates/preview', PreviewEventEmailTemplateAction::class);

        // Attendees
        $router->post('/events/{event_id}/attendees', CreateAttendeeAction::class);
        $router->get('/events/{event_id}/attendees', GetAttendeesAction::class);
        $router->get('/events/{event_id}/attendees/{attendee_id}', GetAttendeeAction::class);
        $router->put('/events/{event_id}/attendees/{attendee_id}', EditAttendeeAction::class);
        $router->patch('/events/{event_id}/attendees/{attendee_id}', PartialEditAttendeeAction::class);
        $router->post('/events/{event_id}/attendees/export', ExportAttendeesAction::class);
        $router->post('/events/{event_id}/attendees/{attendee_public_id}/resend-ticket', ResendAttendeeTicketAction::class);
        $router->post('/events/{event_id}/attendees/{attendee_public_id}/check_in', CheckInAttendeeAction::class);

        // Orders
        $router->get('/events/{event_id}/orders', GetOrdersAction::class);
        $router->get('/events/{event_id}/orders/{order_id}', GetOrderAction::class);
        $router->put('/events/{event_id}/orders/{order_id}', EditOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/message', MessageOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/refund', RefundOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/resend_confirmation', ResendOrderConfirmationAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/cancel', CancelOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/mark-as-paid', MarkOrderAsPaidAction::class);
        $router->post('/events/{event_id}/orders/export', ExportOrdersAction::class);
        $router->get('/events/{event_id}/orders/{order_id}/invoice', DownloadOrderInvoiceAction::class);

        // Questions
        $router->post('/events/{event_id}/questions', CreateQuestionAction::class);
        $router->put('/events/{event_id}/questions/{question_id}', EditQuestionAction::class);
        $router->get('/events/{event_id}/questions/{question_id}', GetQuestionAction::class);
        $router->delete('/events/{event_id}/questions/{question_id}', DeleteQuestionAction::class);
        $router->get('/events/{event_id}/questions', GetQuestionsAction::class);
        $router->post('/events/{event_id}/questions/sort', SortQuestionsAction::class);
        $router->put('/events/{event_id}/questions/{question_id}/answers/{answer_id}', EditQuestionAnswerAction::class);
        $router->match(['get', 'post'], '/events/{event_id}/questions/answers/export', ExportQuestionAnswersAction::class);

        // Images
        $router->post('/events/{event_id}/images', CreateEventImageAction::class);
        $router->get('/events/{event_id}/images', GetEventImagesAction::class);
        $router->delete('/events/{event_id}/images/{image_id}', DeleteEventImageAction::class);

        // Promo Codes
        $router->post('/events/{event_id}/promo-codes', CreatePromoCodeAction::class);
        $router->put('/events/{event_id}/promo-codes/{promo_code_id}', UpdatePromoCodeAction::class);
        $router->get('/events/{event_id}/promo-codes', GetPromoCodesAction::class);
        $router->get('/events/{event_id}/promo-codes/{promo_code_id}', GetPromoCodeAction::class);
        $router->delete('/events/{event_id}/promo-codes/{promo_code_id}', DeletePromoCodeAction::class);

        // Affiliates
        $router->post('/events/{event_id}/affiliates', CreateAffiliateAction::class);
        $router->put('/events/{event_id}/affiliates/{affiliate_id}', UpdateAffiliateAction::class);
        $router->get('/events/{event_id}/affiliates', GetAffiliatesAction::class);
        $router->get('/events/{event_id}/affiliates/{affiliate_id}', GetAffiliateAction::class);
        $router->delete('/events/{event_id}/affiliates/{affiliate_id}', DeleteAffiliateAction::class);
        $router->post('/events/{event_id}/affiliates/export', ExportAffiliatesAction::class);

        // Messages
        $router->post('/events/{event_id}/messages', SendMessageAction::class);
        $router->get('/events/{event_id}/messages', GetMessagesAction::class);
        $router->post('/events/{event_id}/messages/{message_id}/cancel', CancelMessageAction::class);
        $router->get('/events/{event_id}/messages/{message_id}/recipients', GetMessageRecipientsAction::class);

        // Event Settings
        $router->get('/events/{event_id}/settings', GetEventSettingsAction::class);
        $router->put('/events/{event_id}/settings', EditEventSettingsAction::class);
        $router->patch('/events/{event_id}/settings', PartialEditEventSettingsAction::class);
        $router->get('/events/{event_id}/settings/platform-fee-preview', GetPlatformFeePreviewAction::class);

        // Capacity Assignments
        $router->post('/events/{event_id}/capacity-assignments', CreateCapacityAssignmentAction::class);
        $router->get('/events/{event_id}/capacity-assignments', GetCapacityAssignmentsAction::class);
        $router->get('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', GetCapacityAssignmentAction::class);
        $router->put('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', UpdateCapacityAssignmentAction::class);
        $router->delete('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', DeleteCapacityAssignmentAction::class);

        // Check-In Lists
        $router->post('/events/{event_id}/check-in-lists', CreateCheckInListAction::class);
        $router->get('/events/{event_id}/check-in-lists', GetCheckInListsAction::class);
        $router->get('/events/{event_id}/check-in-lists/{check_in_list_id}', GetCheckInListAction::class);
        $router->put('/events/{event_id}/check-in-lists/{check_in_list_id}', UpdateCheckInListAction::class);
        $router->delete('/events/{event_id}/check-in-lists/{check_in_list_id}', DeleteCheckInListAction::class);

        // Space, programme, accreditation and access (ARZO master plan documents 21-30)
        // Venues (account scoped)
        $router->post('/venues', CreateVenueAction::class);
        $router->get('/venues', GetVenuesAction::class);
        $router->get('/venues/{id}', GetVenueAction::class);
        $router->put('/venues/{id}', UpdateVenueAction::class);
        $router->delete('/venues/{id}', DeleteVenueAction::class);

        // Zones
        $router->post('/venues/{venue_id}/zones', CreateZoneAction::class);
        $router->get('/venues/{venue_id}/zones', GetZonesAction::class);
        $router->get('/venues/{venue_id}/zones/{id}', GetZoneAction::class);
        $router->put('/venues/{venue_id}/zones/{id}', UpdateZoneAction::class);
        $router->delete('/venues/{venue_id}/zones/{id}', DeleteZoneAction::class);

        // Access points
        $router->post('/zones/{zone_id}/access-points', CreateAccessPointAction::class);
        $router->get('/zones/{zone_id}/access-points', GetAccessPointsAction::class);
        $router->get('/zones/{zone_id}/access-points/{id}', GetAccessPointAction::class);
        $router->put('/zones/{zone_id}/access-points/{id}', UpdateAccessPointAction::class);
        $router->delete('/zones/{zone_id}/access-points/{id}', DeleteAccessPointAction::class);

        // Rooms
        $router->post('/venues/{venue_id}/rooms', CreateRoomAction::class);
        $router->get('/venues/{venue_id}/rooms', GetRoomsAction::class);
        $router->get('/venues/{venue_id}/rooms/{id}', GetRoomAction::class);
        $router->put('/venues/{venue_id}/rooms/{id}', UpdateRoomAction::class);
        $router->delete('/venues/{venue_id}/rooms/{id}', DeleteRoomAction::class);

        // Tracks
        $router->post('/events/{event_id}/tracks', CreateTrackAction::class);
        $router->get('/events/{event_id}/tracks', GetTracksAction::class);
        $router->get('/events/{event_id}/tracks/{id}', GetTrackAction::class);
        $router->put('/events/{event_id}/tracks/{id}', UpdateTrackAction::class);
        $router->delete('/events/{event_id}/tracks/{id}', DeleteTrackAction::class);

        // Speakers
        $router->post('/events/{event_id}/speakers', CreateSpeakerAction::class);
        $router->get('/events/{event_id}/speakers', GetSpeakersAction::class);
        $router->get('/events/{event_id}/speakers/{id}', GetSpeakerAction::class);
        $router->put('/events/{event_id}/speakers/{id}', UpdateSpeakerAction::class);
        $router->delete('/events/{event_id}/speakers/{id}', DeleteSpeakerAction::class);

        // Sessions
        $router->post('/events/{event_id}/sessions', CreateSessionAction::class);
        $router->get('/events/{event_id}/sessions', GetSessionsAction::class);
        $router->get('/events/{event_id}/sessions/{id}', GetSessionAction::class);
        $router->put('/events/{event_id}/sessions/{id}', UpdateSessionAction::class);
        $router->delete('/events/{event_id}/sessions/{id}', DeleteSessionAction::class);

        // Accreditation types
        $router->post('/events/{event_id}/accreditation-types', CreateAccreditationTypeAction::class);
        $router->get('/events/{event_id}/accreditation-types', GetAccreditationTypesAction::class);
        $router->get('/events/{event_id}/accreditation-types/{id}', GetAccreditationTypeAction::class);
        $router->put('/events/{event_id}/accreditation-types/{id}', UpdateAccreditationTypeAction::class);
        $router->delete('/events/{event_id}/accreditation-types/{id}', DeleteAccreditationTypeAction::class);

        // Access rules
        $router->post('/events/{event_id}/access-rules', CreateAccessRuleAction::class);
        $router->get('/events/{event_id}/access-rules', GetAccessRulesAction::class);
        $router->get('/events/{event_id}/access-rules/{id}', GetAccessRuleAction::class);
        $router->put('/events/{event_id}/access-rules/{id}', UpdateAccessRuleAction::class);
        $router->delete('/events/{event_id}/access-rules/{id}', DeleteAccessRuleAction::class);

        // Access logs and scanning
        $router->get('/events/{event_id}/access-logs', GetAccessLogsAction::class);
        $router->post('/events/{event_id}/access-scans', RecordAccessScanAction::class);
        $router->post('/events/{event_id}/access-scans/simulate', SimulateAccessScanAction::class);

        // Per-event roles
        $router->get('/events/{event_id}/users', GetEventUsersAction::class);
        $router->post('/events/{event_id}/users', GrantEventRoleAction::class);
        $router->delete('/events/{event_id}/users/{user_id}', RevokeEventRoleAction::class);
        // Session registration, attendance and agenda
        $router->get('/events/{event_id}/sessions/{session_id}/registrations', GetSessionRegistrationsAction::class);
        $router->post('/events/{event_id}/sessions/{session_id}/registrations', RegisterForSessionAction::class);
        $router->delete('/events/{event_id}/sessions/{session_id}/registrations/{attendee_id}', CancelSessionRegistrationAction::class);
        $router->post('/events/{event_id}/sessions/{session_id}/attendance', RecordSessionAttendanceAction::class);
        $router->get('/events/{event_id}/sessions/{session_id}/stats', GetSessionStatsAction::class);
        $router->get('/events/{event_id}/sessions/{session_id}/calendar.ics', ExportSessionIcsAction::class);
        $router->get('/events/{event_id}/programme.ics', ExportEventProgrammeIcsAction::class);
        $router->get('/events/{event_id}/attendees/{attendee_id}/agenda', GetAttendeeAgendaAction::class);
        // Accreditation application and review
        $router->post('/events/{event_id}/accreditations/applications', SubmitAccreditationAction::class);
        $router->post('/events/{event_id}/accreditations/{accreditation_id}/approve', ApproveAccreditationAction::class);
        $router->post('/events/{event_id}/accreditations/{accreditation_id}/reject', RejectAccreditationAction::class);
        $router->post('/events/{event_id}/accreditations/{accreditation_id}/credential', IssueAccreditationCredentialAction::class);
        $router->get('/events/{event_id}/accreditations/{accreditation_id}/audit-trail', GetAccreditationAuditTrailAction::class);
        // Badge photo capture at the desk
        $router->post('/events/{event_id}/persons/{person_id}/photo', CapturePersonPhotoAction::class);
        $router->delete('/events/{event_id}/persons/{person_id}/photo', DeletePersonPhotoAction::class);
        // API keys
        $router->get('/api-keys', GetApiKeysAction::class);
        $router->post('/api-keys', CreateApiKeyAction::class);
        $router->delete('/api-keys/{api_key_id}', RevokeApiKeyAction::class);
        // Exhibitors, booths and leads
        $router->get('/events/{event_id}/exhibitors', GetEventExhibitorsAction::class);
        $router->post('/events/{event_id}/exhibitors', UpsertEventExhibitorAction::class);
        $router->post('/events/{event_id}/exhibitors/{event_exhibitor_id}/staff', NameExhibitorStaffAction::class);
        $router->delete('/events/{event_id}/exhibitor-staff/{exhibitor_staff_id}', WithdrawExhibitorStaffAction::class);
        $router->post('/events/{event_id}/booth-assignments', AssignBoothAction::class);
        $router->delete('/events/{event_id}/booth-assignments/{assignment_id}', ReleaseBoothAction::class);
        $router->get('/events/{event_id}/exhibitors/{event_exhibitor_id}/leads', GetLeadsAction::class);
        $router->post('/events/{event_id}/exhibitors/{event_exhibitor_id}/leads', CaptureLeadAction::class);
        $router->patch('/events/{event_id}/exhibitors/{event_exhibitor_id}/leads/{lead_id}', UpdateLeadAction::class);
        $router->get('/events/{event_id}/exhibitors/{event_exhibitor_id}/lead-stats', GetLeadCaptureStatsAction::class);
        $router->get('/events/{event_id}/exhibitors/{event_exhibitor_id}/lead-scores', GetLeadScoresAction::class);

        // Queues, derived from the scan stream
        $router->get('/events/{event_id}/queues', GetEventQueuesAction::class);
        $router->get('/events/{event_id}/access-points/{access_point_id}/queue', GetAccessPointQueueAction::class);

        // Sponsors: a separate participation from exhibiting, sharing only the company
        $router->get('/events/{event_id}/sponsorships', GetSponsorshipsAction::class);
        $router->post('/events/{event_id}/sponsorships', CreateSponsorshipAction::class);
        $router->patch('/events/{event_id}/sponsorships/{sponsorship_id}', UpdateSponsorshipAction::class);
        $router->post('/events/{event_id}/sponsorship-packages', CreateSponsorshipPackageAction::class);
        $router->get('/events/{event_id}/sponsorships/{sponsorship_id}/fulfilment', GetSponsorshipFulfilmentAction::class);
        $router->post('/events/{event_id}/sponsorships/{sponsorship_id}/entitlements', AddSponsorshipEntitlementAction::class);
        $router->post('/events/{event_id}/sponsorship-entitlements/{entitlement_id}/fulfilment', RecordEntitlementFulfilmentAction::class);

        // Two-factor authentication, scoped to the caller
        $router->get('/auth/mfa', GetMfaStatusAction::class);
        $router->post('/auth/mfa', BeginMfaEnrolmentAction::class);
        $router->post('/auth/mfa/confirm', ConfirmMfaEnrolmentAction::class);
        $router->delete('/auth/mfa', DisableMfaAction::class);

        // Push subscriptions, scoped to the caller so nobody can silence another person
        $router->post('/push-subscriptions', RegisterPushSubscriptionAction::class);
        $router->delete('/push-subscriptions', RevokePushSubscriptionAction::class);
        $router->get('/events/{event_id}/push-health', GetPushHealthAction::class);

        // The event-day dashboard, in one response so every figure shares an instant
        $router->get('/events/{event_id}/command-centre', GetCommandCentreSnapshotAction::class);

        // Networking and meetings, arranged by the event team
        $router->get('/events/{event_id}/networking/{person_id}/directory', GetNetworkingDirectoryAction::class);
        $router->get('/events/{event_id}/networking/{person_id}/meetings', GetPersonMeetingsAction::class);
        $router->post('/events/{event_id}/meetings', RequestMeetingAction::class);
        $router->post('/events/{event_id}/meetings/{meeting_id}/respond', RespondToMeetingAction::class);
        $router->delete('/events/{event_id}/meetings/{meeting_id}', CancelMeetingAction::class);

        // Raffles, drawn from the access log and auditable by construction
        $router->get('/events/{event_id}/raffles', GetRafflesAction::class);
        $router->post('/events/{event_id}/raffles', CreateRaffleAction::class);
        $router->get('/events/{event_id}/raffles/{raffle_id}/pool', GetRafflePoolAction::class);
        $router->post('/events/{event_id}/raffles/{raffle_id}/draw', DrawRaffleAction::class);
        $router->get('/events/{event_id}/raffles/{raffle_id}/draws/{draw_id}/verify', VerifyRaffleDrawAction::class);
        $router->post('/events/{event_id}/raffles/{raffle_id}/winners/{winner_id}/claim', RecordRaffleClaimAction::class);

        // Guest list (RSVP)
        $router->get('/events/{event_id}/invitations', GetGuestListAction::class);
        $router->post('/events/{event_id}/invitations', CreateInvitationAction::class);
        $router->delete('/events/{event_id}/invitations/{invitation_id}', RevokeInvitationAction::class);

        // Operations: staffing, tasks, incidents, readiness
        $router->get('/events/{event_id}/staffing-gaps', GetStaffingGapsAction::class);
        $router->post('/events/{event_id}/shifts/{shift_id}/assignments', AssignShiftAction::class);
        $router->post('/events/{event_id}/task-templates/{template_id}/instantiate', InstantiateTaskTemplateAction::class);
        $router->get('/events/{event_id}/task-blockers', GetOutstandingBlockersAction::class);
        $router->post('/events/{event_id}/incidents', ReportIncidentAction::class);
        $router->post('/events/{event_id}/incidents/{incident_id}/transition', TransitionIncidentAction::class);
        $router->get('/events/{event_id}/incidents/summary', GetIncidentSummaryAction::class);
        $router->post('/events/{event_id}/readiness-reviews', OpenReadinessReviewAction::class);
        $router->post('/events/{event_id}/readiness-reviews/{review_id}/decide', DecideReadinessReviewAction::class);

        // Analytics
        $router->get('/events/{event_id}/analytics/attendance', GetAttendanceReportAction::class);
        $router->get('/events/{event_id}/analytics/sessions', GetSessionAnalyticsAction::class);
        $router->get('/events/{event_id}/analytics/demographics', GetDemographicsAction::class);
        $router->get('/events/{event_id}/analytics/zones/{zone_id}/dwell', GetZoneDwellTimeAction::class);

        // Device fleet
        $router->post('/events/{event_id}/devices', RegisterDeviceAction::class);
        $router->get('/events/{event_id}/devices/fleet-status', GetFleetStatusAction::class);
        $router->delete('/events/{event_id}/devices/{device_id}', SuspendDeviceAction::class);
        $router->get('/events/{event_id}/reconciliation-findings', GetReconciliationFindingsAction::class);
        $router->post('/events/{event_id}/reconciliation-findings/{finding_id}/review', ReviewReconciliationFindingAction::class);

        // Credentials
        $router->get('/events/{event_id}/credentials', GetCredentialsAction::class);
        $router->post('/events/{event_id}/credentials', IssueCredentialAction::class);
        $router->post('/events/{event_id}/credentials/{id}/revoke', RevokeCredentialAction::class);

        // Webhooks
        $router->post('/events/{event_id}/webhooks', CreateWebhookAction::class);
        $router->get('/events/{event_id}/webhooks', GetWebhooksAction::class);
        $router->put('/events/{event_id}/webhooks/{webhook_id}', EditWebhookAction::class);
        $router->get('/events/{event_id}/webhooks/{webhook_id}', GetWebhookAction::class);
        $router->delete('/events/{event_id}/webhooks/{webhook_id}', DeleteWebhookAction::class);
        $router->get('/events/{event_id}/webhooks/{webhook_id}/logs', GetWebhookLogsAction::class);

        // Reports
        $router->get('/events/{event_id}/reports/{report_type}', GetReportAction::class);

        // Waitlist
        $router->get('/events/{event_id}/waitlist', GetWaitlistEntriesAction::class);
        $router->get('/events/{event_id}/waitlist/stats', GetWaitlistStatsAction::class);
        $router->post('/events/{event_id}/waitlist/offer-next', OfferWaitlistEntryAction::class);
        $router->delete('/events/{event_id}/waitlist/{entry_id}', CancelWaitlistEntryAction::class);

        // Event Occurrences
        $router->post('/events/{event_id}/occurrences/generate', GenerateOccurrencesAction::class);
        $router->get('/events/{event_id}/occurrences/generate/status', GetOccurrenceGenerationStatusAction::class);
        $router->post('/events/{event_id}/occurrences/bulk-update', BulkUpdateOccurrencesAction::class);
        $router->post('/events/{event_id}/occurrences', CreateEventOccurrenceAction::class);
        $router->get('/events/{event_id}/occurrences', GetEventOccurrencesAction::class);
        $router->get('/events/{event_id}/occurrences/{occurrence_id}', GetEventOccurrenceAction::class);
        $router->put('/events/{event_id}/occurrences/{occurrence_id}', UpdateEventOccurrenceAction::class);
        $router->delete('/events/{event_id}/occurrences/{occurrence_id}', DeleteEventOccurrenceAction::class);
        $router->post('/events/{event_id}/occurrences/{occurrence_id}/cancel', CancelOccurrenceAction::class);
        $router->post('/events/{event_id}/occurrences/{occurrence_id}/reactivate', ReactivateOccurrenceAction::class);
        $router->put('/events/{event_id}/occurrences/{occurrence_id}/price-overrides', UpsertPriceOverrideAction::class);
        $router->get('/events/{event_id}/occurrences/{occurrence_id}/price-overrides', GetPriceOverridesAction::class);
        $router->delete('/events/{event_id}/occurrences/{occurrence_id}/price-overrides/{override_id}', DeletePriceOverrideAction::class);
        $router->get('/events/{event_id}/occurrences/{occurrence_id}/product-visibility', GetProductVisibilityAction::class);
        $router->get('/events/{event_id}/occurrences/{occurrence_id}/product-availability', GetOccurrenceProductAvailabilityAction::class);
        $router->put('/events/{event_id}/occurrences/{occurrence_id}/product-visibility', UpdateProductVisibilityAction::class);

        // Images
        $router->post('/images', CreateImageAction::class);
        $router->delete('/images/{image_id}', DeleteImageAction::class);
    }
);

$router->prefix('/admin')->middleware(['auth:api'])->group(
    function (Router $router): void {
        $router->get('/stats', GetAdminStatsAction::class);
        $router->get('/dashboard', GetAdminDashboardDataAction::class);
        $router->get('/attribution/stats', GetUtmAttributionStatsAction::class);
        $router->get('/accounts', GetAllAdminAccountsAction::class);
        $router->get('/accounts/{account_id}', GetAdminAccountAction::class);
        $router->put('/organizers/{organizerId}/vat-settings', UpdateOrganizerVatSettingAction::class);
        $router->patch('/organizers/{organizerId}/configuration', UpdateOrganizerConfigurationAction::class);
        $router->put('/organizers/{organizerId}/configuration', AssignOrganizerConfigurationAction::class);
        $router->get('/configurations', GetAllConfigurationsAction::class);
        $router->post('/configurations', CreateConfigurationAction::class);
        $router->put('/configurations/{configuration_id}', UpdateConfigurationAction::class);
        $router->delete('/configurations/{configuration_id}', DeleteConfigurationAction::class);
        $router->get('/users', GetAllUsersAction::class);
        $router->get('/events', GetAllAdminEventsAction::class);
        $router->get('/events/upcoming', GetUpcomingEventsAction::class);
        $router->get('/orders', GetAllOrdersAction::class);
        $router->post('/impersonate/{user_id}', StartImpersonationAction::class);
        $router->post('/stop-impersonation', StopImpersonationAction::class);

        // Failed Jobs
        $router->get('/failed-jobs', GetAllFailedJobsAction::class);
        $router->delete('/failed-jobs/{jobId}', DeleteFailedJobAction::class);
        $router->delete('/failed-jobs', DeleteAllFailedJobsAction::class);
        $router->post('/failed-jobs/{jobId}/retry', RetryFailedJobAction::class);
        $router->post('/failed-jobs/retry-all', RetryAllFailedJobsAction::class);

        // Messages
        $router->get('/messages', GetAllAdminMessagesAction::class);
        $router->post('/messages/{message_id}/approve', ApproveMessageAction::class);
        $router->post('/messages/{message_id}/reject', RejectMessageAction::class);

        // Spam Events
        $router->get('/spam-events', GetAllSpamEventsAction::class);
        $router->post('/spam-events/{event_id}/approve', ApproveSpamEventAction::class);
        $router->post('/spam-events/{event_id}/confirm-spam', ConfirmSpamEventAction::class);

        // Announcements
        $router->get('/announcements', GetAllAnnouncementsAction::class);
        $router->post('/announcements', CreateAnnouncementAction::class);
        $router->put('/announcements/{announcement_id}', UpdateAnnouncementAction::class);
        $router->delete('/announcements/{announcement_id}', DeleteAnnouncementAction::class);

        // Messaging Tiers
        $router->get('/messaging-tiers', GetMessagingTiersAction::class);
        $router->put('/accounts/{account_id}/messaging-tier', UpdateAccountMessagingTierAction::class);

        // Account Verification
        $router->put('/accounts/{account_id}/verification', UpdateAccountVerificationAction::class);

        // Account Deletion Requests
        $router->get('/deletion-requests', GetAllAccountDeletionRequestsAction::class);
        $router->post('/accounts/{account_id}/deletion-request', AdminRequestAccountDeletionAction::class);
        $router->delete('/deletion-requests/{deletion_request_id}', AdminCancelAccountDeletionAction::class);
        $router->post('/deletion-requests/{deletion_request_id}/execute', AdminExecuteAccountDeletionAction::class);

        // System Info
        $router->get('/system-info', GetSystemInfoAction::class);
    }
);

/**
 * Public routes
 */
$router->prefix('/public')->group(
    function (Router $router): void {
        // Events
        $router->get('/events/{event_id}', GetEventPublicAction::class);
        $router->get('/events/{event_id}/occurrences', GetEventOccurrencesPublicAction::class)
            ->middleware('throttle:60,1');

        // Organizers
        $router->get('/organizers/{organizer_id}', GetPublicOrganizerAction::class);
        $router->get('/organizers/{organizer_id}/events', GetOrganizerEventsPublicAction::class);
        $router->post('/organizers/{organizer_id}/contact', SendOrganizerContactMessagePublicAction::class)
            ->middleware('throttle:5,1');

        // Products
        $router->get('/events/{event_id}/products', GetEventPublicAction::class);

        // Orders
        $router->post('/events/{event_id}/order', CreateOrderActionPublic::class);
        $router->put('/events/{event_id}/order/{order_short_id}', CompleteOrderActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}', GetOrderActionPublic::class);
        $router->post('/events/{event_id}/order/{order_short_id}/abandon', AbandonOrderActionPublic::class);
        $router->post('/events/{event_id}/order/{order_short_id}/await-offline-payment', TransitionOrderToOfflinePaymentPublicAction::class);
        $router->get('/events/{event_id}/order/{order_short_id}/invoice', DownloadOrderInvoicePublicAction::class);

        // Attendees
        $router->get('/events/{event_id}/attendees/{attendee_short_id}', GetAttendeeActionPublic::class);

        // Waitlist
        $router->post('/events/{event_id}/waitlist', CreateWaitlistEntryActionPublic::class)
            ->middleware('throttle:10,1');
        $router->delete('/events/{event_id}/waitlist/{token}', CancelWaitlistEntryActionPublic::class)
            ->middleware('throttle:10,1');

        // Promo codes
        $router->get('/events/{event_id}/promo-codes/{promo_code}', GetPromoCodePublic::class)
            ->middleware('throttle:10,1');

        // Stripe payment gateway
        $router->post('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', CreatePaymentIntentActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', GetPaymentIntentActionPublic::class);

        // Questions
        $router->get('/events/{event_id}/questions', GetQuestionsPublicAction::class);

        // Webhooks
        $router->post('/webhooks/stripe', StripeIncomingWebhookAction::class);

        // Check-In
        $router->get('/check-in-lists/{check_in_list_short_id}', GetCheckInListPublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/stats', GetCheckInListStatsPublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/attendees', GetCheckInListAttendeesPublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/attendees/{attendee_public_id}', GetCheckInListAttendeePublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/attendees/{attendee_public_id}/detail', GetCheckInListAttendeeDetailPublicAction::class);
        $router->post('/check-in-lists/{check_in_list_short_id}/check-ins', CreateAttendeeCheckInPublicAction::class);
        $router->delete('/check-in-lists/{check_in_list_short_id}/check-ins/{check_in_short_id}', DeleteAttendeeCheckInPublicAction::class);

        // Color themes
        $router->get('/color-themes', GetColorThemesAction::class);

        // Ticket Lookup
        $router->post('/ticket-lookup', SendTicketLookupEmailAction::class)
            ->middleware('throttle:10,1');
        $router->get('/ticket-lookup/{token}', GetOrdersByLookupTokenAction::class);

        // Self-service order and attendee edits
        $router->prefix('/events/{event_id}/order/{order_short_id}')->group(function (Router $router): void {
            $router->patch('/', EditOrderPublicAction::class)->middleware('throttle:self-service-edit');
            $router->post('/resend-confirmation', ResendOrderConfirmationPublicAction::class)->middleware('throttle:self-service-email');

            $router->patch('/attendees/{attendee_short_id}', EditAttendeePublicAction::class)->middleware('throttle:self-service-edit');
            $router->post('/attendees/{attendee_short_id}/resend-ticket', ResendAttendeeTicketPublicAction::class)->middleware('throttle:self-service-email');
        });

        // Sponsors
        $router->get('/events/{event_id}/sponsors', GetPublicSponsorsAction::class);

        // RSVP
        $router->post('/rsvp/{token}', RespondToInvitationPublicAction::class)
            ->middleware('throttle:10,1');

        // Sitemap
        $router->get('/sitemap.xml', GetSitemapIndexAction::class);
        $router->get('/sitemap-events-{page}.xml', GetSitemapEventsAction::class)->where('page', '[0-9]+');
        $router->get('/sitemap-organizers-{page}.xml', GetSitemapOrganizersAction::class)->where('page', '[0-9]+');
    }
);

/*
 * Machine-to-machine API.
 *
 * Versioned from the start: an integration built against v1 keeps working when the
 * dashboard's own endpoints change shape, which the unversioned routes above do not
 * promise.
 */
/*
 * Device endpoints.
 *
 * Authenticated by the device's own key. A device syncs itself — the id comes from the
 * resolved principal, never the URL, so one tablet cannot pull another's roster.
 *
 * Pairing is necessarily unauthenticated: the device has no credential yet, which is
 * what pairing is for. The code is short, single-use and expiring, and the route is
 * throttled so it cannot be brute-forced.
 */
$router->prefix('device')->group(function (Router $router): void {
    $router->post('/pair', PairDeviceAction::class)->middleware('throttle:10,1');

    $router->middleware(['api-key', 'api-key-throttle'])->group(function (Router $router): void {
        $router->post('/sync', SyncDeviceAction::class)
            ->middleware('api-scope:device.submit_scan');
        $router->post('/heartbeat', HeartbeatDeviceAction::class);
    });
});

$router->prefix('v1')
    ->middleware(['api-key', 'api-key-throttle'])
    ->group(function (Router $router): void {
        $router->get('/events/{event_id}/attendees', GetV1EventAttendeesAction::class)
            ->middleware('api-scope:attendee.view');
    });

if (app()->environment('local', 'development')) {
    include_once __DIR__.'/mail.php';
}
