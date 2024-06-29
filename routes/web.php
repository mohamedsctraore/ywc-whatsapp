<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;
use App\Yantrana\Components\Home\Controllers\HomeController;
use App\Yantrana\Components\Page\Controllers\PageController;
use App\Yantrana\Components\User\Controllers\UserController;
use App\Yantrana\Components\Media\Controllers\MediaController;
use App\Yantrana\Components\Vendor\Controllers\VendorController;
use App\Yantrana\Components\Contact\Controllers\ContactController;
use App\Yantrana\Components\BotReply\Controllers\BotReplyController;
use App\Yantrana\Components\Campaign\Controllers\CampaignController;
use App\Yantrana\Components\Dashboard\Controllers\DashboardController;
use App\Yantrana\Components\Contact\Controllers\ContactGroupController;
use App\Yantrana\Components\Vendor\Controllers\VendorSettingsController;
use App\Yantrana\Components\Translation\Controllers\TranslationController;
use App\Yantrana\Components\Subscription\Controllers\SubscriptionController;
use App\Yantrana\Components\Contact\Controllers\ContactCustomFieldController;
use App\Yantrana\Components\Subscription\Controllers\StripeWebhookController;
use App\Yantrana\Components\Configuration\Controllers\ConfigurationController;
use App\Yantrana\Components\Subscription\Controllers\ManualSubscriptionController;
use App\Yantrana\Components\WhatsAppService\Controllers\WhatsAppServiceController;
use App\Yantrana\Components\WhatsAppService\Controllers\WhatsAppTemplateController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [
    HomeController::class,
    'homePageView',
])->name('landing_page');
// user console
Route::get('/console', function () {
    return hasCentralAccess() ? Redirect::route('central.console') : Redirect::route('vendor.console');
})->name('home');

// authentication routes
require __DIR__ . '/auth.php';
// Authenticated Routes
Route::middleware([
    App\Http\Middleware\Authenticate::class,
])->group(function () {
    /*
    Media Component Routes Start from here
    ------------------------------------------------------------------- */
    Route::group([
        'prefix' => 'media',
    ], function () {
        // Temp Upload
        Route::post('/upload-temp-media/{uploadItem?}', [
            MediaController::class,
            'uploadTempMedia',
        ])->name('media.upload_temp_media');
    });

    // User consoles
    Route::prefix('user-console')
        ->group(function () {
            // profile form
            Route::get('profile-update', [
                UserController::class,
                'profileEditForm',
            ])->name('user.profile.edit');
            // profile update request
            Route::post('profile-update', [
                UserController::class,
                'updateProfile',
            ])->name('user.profile.update');
        });
    // SuperAdmin Routes
    Route::middleware([
        App\Http\Middleware\CentralAccessCheckpost::class,
    ])->prefix('central-console')
        ->group(function () {
            Route::get('/', [
                DashboardController::class,
                'dashboardView',
            ])->name('central.console');
            // Upload Logo
            Route::post('/upload-logo', [
                MediaController::class,
                'uploadLogo',
            ])->name('media.upload_logo');
            // Upload Favicon
            Route::post('/upload-favicon', [
                MediaController::class,
                'uploadFavicon',
            ])->name('media.upload_favicon');

            Route::get('/subscription-plans', [
                ConfigurationController::class,
                'subscriptionPlans',
            ])->name('manage.configuration.subscription-plans');

            Route::post('/subscription-plans', [
                ConfigurationController::class,
                'subscriptionPlansProcess',
            ])->name('manage.configuration.subscription-plans.write.update');

            Route::post('/create-stripe-webhook', [
                ConfigurationController::class,
                'createStripeWebhook',
            ])->name('manage.configuration.create_stripe_webhook');

            Route::get('/vendors', function () {
                return view('vendors.list');
            })->name('central.vendors');

            Route::get('/{vendorIdOrUid}/details', [
                vendorController::class,
                'vendorDetails',
            ])->name('central.vendor.details');

            // login as team member
            Route::post("/{vendorUid}/login-as-vendor-admin", [
                VendorController::class,
                'loginAsVendorAdmin'
            ])->name('central.vendors.user.write.login_as');

            Route::post('/dashboard-stats-filter-data/{vendorUid}', [
                DashboardController::class,
                'dashboardStatsDataFilter',
            ])->name('central.read.stat_data_filter');

            Route::get('/subscriptions', function () {
                return view('subscription.list');
            })->name('central.subscriptions');

            // ManualSubscription Routes Group Start
            Route::prefix('/manual-subscriptions')->group(function () {
                // ManualSubscription list view
                Route::get("/", [
                    ManualSubscriptionController::class,
                    'showManualSubscriptionView'
                ])->name('central.subscription.manual_subscription.read.list_view');
                // selected plan details
                Route::post("/selected-plan-details", [
                    ManualSubscriptionController::class,
                    'getSelectedPlanDetails'
                ])->name('central.subscription.manual_subscription.read.selected_plan_details');
                // ManualSubscription list request
                Route::get("/list-data/{vendorUid?}", [
                    ManualSubscriptionController::class,
                    'prepareManualSubscriptionList'
                ])->name('central.subscription.manual_subscription.read.list');

                // ManualSubscription delete process
                Route::post("/{manualSubscriptionIdOrUid}/delete-process", [
                    ManualSubscriptionController::class,
                    'processManualSubscriptionDelete'
                ])->name('central.subscription.manual_subscription.write.delete');

                // ManualSubscription create process
                Route::post("/add-process", [
                    ManualSubscriptionController::class,
                    'processManualSubscriptionCreate'
                ])->name('central.subscription.manual_subscription.write.create');

                // ManualSubscription get the data
                Route::get("/{manualSubscriptionIdOrUid}/get-update-data", [
                    ManualSubscriptionController::class,
                    'updateManualSubscriptionData'
                ])->name('central.subscription.manual_subscription.read.update.data');

                // ManualSubscription update process
                Route::post("/update-process", [
                    ManualSubscriptionController::class,
                    'processManualSubscriptionUpdate'
                ])->name('central.subscription.manual_subscription.write.update');

                // cancel subscription
                Route::post('/cancel-and-discard/{vendorUid}', [
                    SubscriptionController::class,
                    'cancelAndDiscard',
                ])->name('central.subscription.write.cancel');

            });
            // ManualSubscription Routes Group End


            Route::post('/add', [
                VendorController::class,
                'addVendor',
            ])->name('central.vendors.write.add');

            Route::get('/fetch-list', [
                VendorController::class,
                'vendorDataTableList',
            ])->name('central.vendors.read.list');
            /*
            Manage Translations
            ------------------------------------------------------------------- */
            Route::group([
                'namespace' => 'Translation\Controllers',
                'prefix' => 'translations',
            ], function () {
                Route::get('/', [
                    TranslationController::class,
                    'languages',
                ])->name('manage.translations.languages');

                // Store New Language
                Route::post('/process-language-store', [
                    TranslationController::class,
                    'storeLanguage',
                ])->name('manage.translations.write.language_create');

                // Update Language
                Route::post('/process-language-update', [
                    TranslationController::class,
                    'updateLanguage',
                ])->name('manage.translations.write.language_update');
                // Delete Language
                Route::post('/{languageId}/process-language-delete', [
                    TranslationController::class,
                    'deleteLanguage',
                ])->name('manage.translations.write.language_delete');

                Route::get('language/{languageId}', [
                    TranslationController::class,
                    'lists',
                ])->name('manage.translations.lists');

                Route::get('/scan/{languageId}/{preventReload?}', [
                    TranslationController::class,
                    'scan',
                ])->name('manage.translations.scan');

                Route::post('/update', [
                    TranslationController::class,
                    'update',
                ])->name('manage.translations.update');

                Route::get('/export/{languageId}', [
                    TranslationController::class,
                    'export',
                ])->name('manage.translations.export');

                Route::post('/import/{languageId}', [
                    TranslationController::class,
                    'import',
                ])->name('manage.translations.import');

                Route::post('/auto-translate/{serviceId}/{languageId}', [
                    TranslationController::class,
                    'translatePoFile',
                ])->name('manage.translations.auto_translate');

                Route::post('/auto-translate-all/{serviceId}', [
                    TranslationController::class,
                    'translatePoFiles',
                ])->name('manage.translations.auto_translate_all');
            });
            /*
            Configuration Component Routes Start from here
            ------------------------------------------------------------------- */
            Route::group([
                'namespace' => 'Configuration\Controllers',
                'prefix' => 'configuration',
            ], function () {

                Route::get('/licence-information', [
                    ConfigurationController::class,
                    'registerProductView',
                ])->name('manage.configuration.product_registration');

                Route::post('/licence-information-process', [
                    ConfigurationController::class,
                    'processProductRegistration',
                ])->name('installation.version.create.registration');

                Route::post('/licence-information-remove-process', [
                    ConfigurationController::class,
                    'processProductRegistrationRemoval',
                ])->name('installation.version.create.remove_registration');
                // View Configuration View
                Route::get('/{pageType}', [
                    ConfigurationController::class,
                    'getConfiguration',
                ])->name('manage.configuration.read');
                // Process Configuration Data
                Route::post('/{pageType}/process-configuration-store', [
                    ConfigurationController::class,
                    'processStoreConfiguration',
                ])->name('manage.configuration.write');
            });
            // manage-vendor Routes Group start
            Route::prefix('/vendors')->group(function () {

                Route::post('/list-data/{vendorIdOrUid}', [
                    vendorController::class,
                    'prepareVendorDelete',
                ])->name('vendor.delete');
                //Vendor permanant delete
                Route::post('/vendor-delete/{vendorIdOrUid}', [
                    vendorController::class,
                    'prepareVendorPermanentDelete',
                ])->name('vendor.permanant.delete');

                // Vendor get the data
                Route::get('/get-update-data/{vendorIdOrUid}', [
                    vendorController::class,
                    'prepareUpdateVendorData',
                ])->name('vendor.read.update.data');
                // Vendor get the data
                Route::post('/update-vendor-data', [
                    vendorController::class,
                    'updateVendorData',
                ])->name('vendor.write.update');
                // route for change password button on author side .
                Route::get('/{vendorIdOrUid}/get-change-password-vendor', [
                    vendorController::class,
                    'changePasswordVendorData',
                ])->name('vendor.change.password.data');

                // route for change password button on super-admin side .
                Route::post('/change-password-vendor', [
                    vendorController::class,
                    'changePasswordVendor',
                ])->name('auth.vendor.change.password');

                // Vendor-dashboard
                Route::get('/{vendorIdOrUid}/dashboard', [
                    vendorController::class,
                    'vendorDashboard',
                ])->name('vendor.dashboard');
            });
            // manage-vendor Routes Group End
            Route::prefix('/subscription-list')->group(function () {
                Route::get('/', [
                    SubscriptionController::class,
                    'subscriptionList',
                ])->name('central.subscription.read.list');

                Route::post('/delete-subscription-entries', [
                    SubscriptionController::class,
                    'deleteSubscriptionEntries',
                ])->name('central.subscription.write.delete_all_entries');
            });
        });
    // Vendor Routes
    Route::middleware([
        App\Http\Middleware\VendorAccessCheckpost::class,
    ])->prefix('vendor-console')
        ->group(function () {
            Route::get('/', [
                DashboardController::class,
                'vendorDashboardView',
            ])->name('vendor.console');

            Route::post('/dashboard-stats-filter-data', [
                DashboardController::class,
                'dashboardStatsDataFilter',
            ])->name('vendor.read.stat_data_filter');


            // User Routes Group Start

            Route::prefix('/users')->group(function () {
                // User list view
                Route::get("/", [
                    UserController::class,
                    'showUserView'
                ])->name('vendor.user.read.list_view');
                // User list request
                Route::get("/list-data", [
                    UserController::class,
                    'prepareUserList'
                ])->name('vendor.user.read.list');

                // User delete process
                Route::post("/{userIdOrUid}/delete-process", [
                    UserController::class,
                    'processUserDelete'
                ])->name('vendor.user.write.delete');

                // User create process
                Route::post("/add-process", [
                    UserController::class,
                    'processUserCreate'
                ])->name('vendor.user.write.create');

                // User get the data
                Route::get("/{userIdOrUid}/get-update-data", [
                    UserController::class,
                    'updateUserData'
                ])->name('vendor.user.read.update.data');

                // User update process
                Route::post("/update-process", [
                    UserController::class,
                    'processUserUpdate'
                ])->name('vendor.user.write.update');
                // login as team member
                Route::post("/{userIdOrUid}/login-as", [
                    UserController::class,
                    'loginAsUser'
                ])->name('vendor.user.write.login_as');

                Route::post("/logout-as", [
                    UserController::class,
                    'logoutAsUser'
                ])->name('vendor.user.write.logout_as');

                // logged out as vendor admin
                Route::post("/logout-as-vendor-admin", [
                    VendorController::class,
                    'logoutAsVendorAdmin'
                ])->name('central.vendors.user.write.logout_as');

            });
            // User Routes Group End


            Route::prefix('/whatsapp')->group(function () {

                Route::post('/health-status', [
                    WhatsAppServiceController::class,
                    'getHealthStatus',
                ])->name('vendor.whatsapp.health.status');

                Route::post('/sync-phone-numbers', [
                    WhatsAppServiceController::class,
                    'syncPhoneNumbers',
                ])->name('vendor.whatsapp.sync_phone_numbers');

                Route::post('/process-template-change', [
                    WhatsAppServiceController::class,
                    'changeTemplate',
                ])->name('vendor.request.template.view');
                // contact template message view
                Route::get('/contact/send-template-message/{contactUid}', [
                    WhatsAppServiceController::class,
                    'sendTemplateMessageView',
                ])->name('vendor.template_message.contact.view');
                // process template message send
                Route::post('/contact/send-template-message/{contactUid}', [
                    WhatsAppServiceController::class,
                    'sendTemplateMessageProcess',
                ])->name('vendor.template_message.contact.process');

                Route::prefix('/campaign')->group(function () {

                    Route::get('/new', [
                        WhatsAppServiceController::class,
                        'createNewCampaign',
                    ])->name('vendor.campaign.new.view');
                    // campaign schedule
                    Route::post('/schedule', [
                        WhatsAppServiceController::class,
                        'scheduleCampaign',
                    ])->name('vendor.campaign.schedule.process');

                    Route::get('/status/{campaignUid}/view/{pageType?}', [
                        CampaignController::class,
                        'campaignStatusView',
                    ])->name('vendor.campaign.status.view');
                    //campaign queue log list view
                    Route::get('/queue/{campaignUid}', [
                        CampaignController::class,
                        'campaignQueueLogListView',
                    ])->name('vendor.campaign.queue.log.list.view');
                     //campaign executed log list view
                     Route::get('/executed/{campaignUid}', [
                        CampaignController::class,
                        'campaignExecutedLogListView',
                    ])->name('vendor.campaign.executed.log.list.view');

                    Route::get('/status/{campaignUid}/data', [
                        CampaignController::class,
                        'campaignStatusData',
                    ])->name('vendor.campaign.status.data');

                    // Campaign list view
                    Route::get('/', [
                        CampaignController::class,
                        'showCampaignView',
                    ])->name('vendor.campaign.read.list_view');
                    // Campaign list request
                    Route::get('/list-data', [
                        CampaignController::class,
                        'prepareCampaignList',
                    ])->name('vendor.campaign.read.list');

                    // Campaign delete process
                    Route::post('/{campaignIdOrUid}/delete-process', [
                        CampaignController::class,
                        'processCampaignDelete',
                    ])->name('vendor.campaign.write.delete');

                });

                // contact chat view
                Route::get('/contact/chat/{contactUid?}', [
                    WhatsAppServiceController::class,
                    'chatView',
                ])->name('vendor.chat_message.contact.view');

                Route::get('/chat/unread-count', [
                    WhatsAppServiceController::class,
                    'unreadCount',
                ])->name('vendor.chat_message.read.unread_count');

                Route::post('/contact/chat/send', [
                    WhatsAppServiceController::class,
                    'sendChatMessage',
                ])->name('vendor.chat_message.send.process');

                Route::post('/contact/chat/assign-user', [
                    ContactController::class,
                    'assignChatUser',
                ])->name('vendor.chat.assign_user.process');

                Route::get('/contact/chat/prepare-send-media/{mediaType?}', [
                    WhatsAppServiceController::class,
                    'prepareSendMediaUploader',
                ])->name('vendor.chat_message_media.upload.prepare');

                Route::post('/contact/chat/send-media', [
                    WhatsAppServiceController::class,
                    'sendChatMessageMedia',
                ])->name('vendor.chat_message_media.send.process');

                Route::get('/contact/chat-data/{contactUid}', [
                    WhatsAppServiceController::class,
                    'getContactChatData',
                ])->name('vendor.chat_message.data.read');

                Route::get('/contact/contacts-data/{contactUid?}', [
                    WhatsAppServiceController::class,
                    'getContactsData',
                ])->name('vendor.contacts.data.read');

                Route::post('/contact/chat/clear-history/{contactUid}', [
                    WhatsAppServiceController::class,
                    'clearChatHistory',
                ])->name('vendor.chat_message.delete.process');

                Route::prefix('/templates')->group(function () {
                    // WhatsAppService list view
                    Route::get('/', [
                        WhatsAppTemplateController::class,
                        'showTemplatesView',
                    ])->name('vendor.whatsapp_service.templates.read.list_view');
                    // WhatsAppService list request
                    Route::get('/list-data', [
                        WhatsAppTemplateController::class,
                        'prepareTemplatesList',
                    ])->name('vendor.whatsapp_service.templates.read.list');

                    Route::post('/sync', [
                        WhatsAppTemplateController::class,
                        'syncTemplates',
                    ])->name('vendor.whatsapp_service.templates.write.sync');

                    Route::post('/delete/{whatsappTemplateUid}', [
                        WhatsAppTemplateController::class,
                        'deleteTemplate',
                    ])->name('vendor.whatsapp_service.templates.write.delete');

                    Route::get('/create', [
                        WhatsAppTemplateController::class,
                        'createNewTemplate',
                    ])->name('vendor.whatsapp_service.templates.read.new_view');

                    Route::post('/create-process', [
                        WhatsAppTemplateController::class,
                        'createNewTemplateProcess',
                    ])->name('vendor.whatsapp_service.templates.write.create');
                    // update template
                    Route::get('/update/{templateUid}', [
                        WhatsAppTemplateController::class,
                        'updateTemplate',
                    ])->name('vendor.whatsapp_service.templates.read.update_view');

                    Route::post('/update-process', [
                        WhatsAppTemplateController::class,
                        'updateTemplateProcess',
                    ])->name('vendor.whatsapp_service.templates.write.update');

                });
            });


            // BotReply Routes Group Start
            Route::prefix('/bot-replies')->group(function () {

                // BotReply list view
                Route::get("/", [
                    BotReplyController::class,
                    'showBotReplyView'
                ])->name('vendor.bot_reply.read.list_view');
                // BotReply list request
                Route::get("/list-data", [
                    BotReplyController::class,
                    'prepareBotReplyList'
                ])->name('vendor.bot_reply.read.list');

                // BotReply delete process
                Route::post("/{botReplyIdOrUid}/delete-process", [
                    BotReplyController::class,
                    'processBotReplyDelete'
                ])->name('vendor.bot_reply.write.delete');

                // BotReply create process
                Route::post("/add-process", [
                    BotReplyController::class,
                    'processBotReplyCreate'
                ])->name('vendor.bot_reply.write.create');

                // BotReply get the data
                Route::get("/{botReplyIdOrUid}/get-update-data", [
                    BotReplyController::class,
                    'updateBotReplyData'
                ])->name('vendor.bot_reply.read.update.data');

                // BotReply update process
                Route::post("/update-process", [
                    BotReplyController::class,
                    'processBotReplyUpdate'
                ])->name('vendor.bot_reply.write.update');
            });
            // BotReply Routes Group End


            // Upload
            Route::post('/upload/{uploadItem}', [
                MediaController::class,
                'vendorUpload',
            ])->name('vendor.media.upload');
            //disable message sound notification
            Route::get('/disable-sound-notifications-for-message', [
                VendorSettingsController::class,
                'disableSoundForMessageNotification',
            ])->name('vendor.disable.sound_message_sound_notification.write');

            // Settings page type
            Route::get('/settings/{pageType?}', [
                VendorSettingsController::class,
                'index',
            ])->name('vendor.settings.read');
            // Vendor Settings update
            Route::post('/settings', [
                VendorSettingsController::class,
                'update',
            ])->name('vendor.settings.write.update');

            Route::post('/settings-basic', [
                VendorSettingsController::class,
                'updateBasicSettings',
            ])->name('vendor.settings_basic.write.update');

            Route::post('/disconnect-webhook', [
                WhatsAppServiceController::class,
                'disconnectWebhook',
            ])->name('vendor.webhook.disconnect.write');

            Route::post('/disconnect-account', [
                WhatsAppServiceController::class,
                'disconnectAccount',
            ])->name('vendor.account.disconnect.write');

            Route::post('/connect-webhook', [
                WhatsAppServiceController::class,
                'connectWebhook',
            ])->name('vendor.webhook.connect.write');

            Route::post('/embedded-signup-process', [
                WhatsAppServiceController::class,
                'embeddedSignUpProcess',
            ])->name('vendor.whatsapp_setup.embedded_signup.write');

            // subscriptions
            Route::prefix('/subscription')->group(function () {
                // load subscription page
                Route::get('/', [
                    SubscriptionController::class,
                    'show',
                ])->name('subscription.read.show');
                // cancel subscription
                Route::get('/cancel', [
                    SubscriptionController::class,
                    'cancel',
                ])->name('subscription.write.cancel');
                // resume subscription
                Route::get('/resume', [
                    SubscriptionController::class,
                    'resume',
                ])->name('subscription.write.resume');
                // billing portal
                Route::get('/billing-portal', [
                    SubscriptionController::class,
                    'billingPortal',
                ])->name('subscription.read.billing_portal');
                // Invoice list
                Route::get('/download-invoice/{invoice}', [
                    SubscriptionController::class,
                    'downloadInvoice',
                ])->name('subscription.read.download_invoice');
                // subscribe to plan
                Route::post('/create', [
                    SubscriptionController::class,
                    'create',
                ])->name('subscription.write.create');

                Route::post('/change-plan', [
                    SubscriptionController::class,
                    'changePlan',
                ])->name('subscription.write.change');

                // Offline
                Route::post('/manual-pay', [
                    ManualSubscriptionController::class,
                    'prepareManualPay',
                ])->name('vendor.subscription_manual_pay');

                Route::post('/manual-pay/delete-request', [
                    ManualSubscriptionController::class,
                    'deleteRequest',
                ])->name('vendor.subscription_manual_pay.delete_request');

                Route::post('/manual-pay/enter-payment-details', [
                    ManualSubscriptionController::class,
                    'sendPaymentDetails',
                ])->name('vendor.subscription_manual_pay.send_payment_details');

                Route::get('/manual-pay/upi-payment-request-qr', [
                    HomeController::class,
                    'generateUpiPaymentUrl',
                ])->name('vendor.generate.upi_payment_request');
            });

            // Page Routes Group Start
            Route::prefix('/pages')->group(function () {

                // Page list view
                Route::get('/', [
                    PageController::class,
                    'showPageView',
                ])->name('page.read.list_view');
                // Page list request
                Route::get('/list-data', [
                    PageController::class,
                    'preparePageList',
                ])->name('page.read.list');

                // Page delete process
                Route::post('/{pageIdOrUid}/delete-process', [
                    PageController::class,
                    'processPageDelete',
                ])->name('page.write.delete');

                // Page create process
                Route::post('/add-process', [
                    PageController::class,
                    'processPageCreate',
                ])->name('page.write.create');

                // Page get the data
                Route::get('/{pageIdOrUid}/get-update-data', [
                    PageController::class,
                    'updatePageData',
                ])->name('page.read.update.data');

                // Page update process
                Route::post('/update-process', [
                    PageController::class,
                    'processPageUpdate',
                ])->name('page.write.update');
            });
            // Page Routes Group End

            // Contact Routes Group Start

            Route::prefix('/contacts')->group(function () {
                // Contact list view
                Route::get('/list/{groupUid?}', [
                    ContactController::class,
                    'showContactView',
                ])->name('vendor.contact.read.list_view');
                // Contact list request
                Route::get('/list-data/{groupUid?}', [
                    ContactController::class,
                    'prepareContactList',
                ])->name('vendor.contact.read.list');

                // Contact delete process
                Route::post('/{contactIdOrUid}/delete-process', [
                    ContactController::class,
                    'processContactDelete',
                ])->name('vendor.contact.write.delete');
                // delete selected contacts
                Route::post('/delete-selected-process', [
                    ContactController::class,
                    'selectedContactsDelete',
                ])->name('vendor.contacts.selected.write.delete');
                // assign group to selected contacts
                Route::post('/assign-groups-selected-process', [
                    ContactController::class,
                    'assignGroupsToSelectedContacts',
                ])->name('vendor.contacts.selected.write.assign_groups');

                // Contact create process
                Route::post('/add-process', [
                    ContactController::class,
                    'processContactCreate',
                ])->name('vendor.contact.write.create');

                // Contact get the data
                Route::get('/{contactIdOrUid}/get-update-data', [
                    ContactController::class,
                    'updateContactData',
                ])->name('vendor.contact.read.update.data');

                // Contact update process
                Route::post('/update-process', [
                    ContactController::class,
                    'processContactUpdate',
                ])->name('vendor.contact.write.update');

                Route::post('/{contactIdOrUid}/toggle-ai-bot', [
                    ContactController::class,
                    'toggleAiBot',
                ])->name('vendor.contact.write.toggle_ai_bot');

                Route::get('/export/{exportType?}', [
                    ContactController::class,
                    'exportContacts',
                ])->name('vendor.contact.write.export');

                Route::post('/import', [
                    ContactController::class,
                    'importContacts',
                ])->name('vendor.contact.write.import');

                // ContactCustomField Routes Group Start
                Route::prefix('/custom-fields')->group(function () {
                    // ContactCustomField list view
                    Route::get("/", [
                        ContactCustomFieldController::class,
                        'showCustomFieldView'
                    ])->name('vendor.contact.custom_field.read.list_view');
                    // ContactCustomField list request
                    Route::get("/list-data", [
                        ContactCustomFieldController::class,
                        'prepareCustomFieldList'
                    ])->name('vendor.contact.custom_field.read.list');

                    // ContactCustomField delete process
                    Route::post("/{contactCustomFieldIdOrUid}/delete-process", [
                        ContactCustomFieldController::class,
                        'processCustomFieldDelete'
                    ])->name('vendor.contact.custom_field.write.delete');

                    // ContactCustomField create process
                    Route::post("/add-process", [
                        ContactCustomFieldController::class,
                        'processCustomFieldCreate'
                    ])->name('vendor.contact.custom_field.write.create');

                    // ContactCustomField get the data
                    Route::get("/{contactCustomFieldIdOrUid}/get-update-data", [
                        ContactCustomFieldController::class,
                        'updateCustomFieldData'
                    ])->name('vendor.contact.custom_field.read.update.data');

                    // ContactCustomField update process
                    Route::post("/update-process", [
                        ContactCustomFieldController::class,
                        'processCustomFieldUpdate'
                    ])->name('vendor.contact.custom_field.write.update');
                });
                // ContactCustomField Routes Group End

                // ContactGroup Routes Group Start
                Route::prefix('/groups')->group(function () {

                    // ContactGroup list view
                    Route::get('/', [
                        ContactGroupController::class,
                        'showGroupView',
                    ])->name('vendor.contact.group.read.list_view');
                    // ContactGroup list request
                    Route::get('/list-data', [
                        ContactGroupController::class,
                        'prepareGroupList',
                    ])->name('vendor.contact.group.read.list');

                    // ContactGroup delete process
                    Route::post('/{contactGroupIdOrUid}/delete-process', [
                        ContactGroupController::class,
                        'processGroupDelete',
                    ])->name('vendor.contact.group.write.delete');

                    // ContactGroup create process
                    Route::post('/add-process', [
                        ContactGroupController::class,
                        'processGroupCreate',
                    ])->name('vendor.contact.group.write.create');

                    // ContactGroup get the data
                    Route::get('/{contactGroupIdOrUid}/get-update-data', [
                        ContactGroupController::class,
                        'updateGroupData',
                    ])->name('vendor.contact.group.read.update.data');

                    // ContactGroup update process
                    Route::post('/update-process', [
                        ContactGroupController::class,
                        'processGroupUpdate',
                    ])->name('vendor.contact.group.write.update');
                });
                // ContactGroup Routes Group End
            });
            // Contact Routes Group End
        });
});
// subscription payment webhook for stripe
Route::post(
    '/stripe/webhook',
    [StripeWebhookController::class, 'handleWebhook']
)->name('cashier.webhook');

Route::get('/change-language/{localeID}', [
    UserController::class,
    'changeLocale',
])->name('locale.change');
//contact page view
Route::get('/contact', [
    HomeController::class,
    'contactForm',
])->name('user.contact.form');
// Contact process
Route::post('/contact-process', [
    HomeController::class,
    'contactProcess',
])->name('user.contact.process');

// compiled js code serverside to make translations ready strings etc
Route::get('/server-compiled.js', [
    HomeController::class,
    'serverCompiledJs',
])->name('vendor.load_server_compiled_js');

Route::get('/terms-and-policies/{contentName?}', [
    HomeController::class,
    'viewTermsAndPolicies',
])->name('app.terms_and_policies');
// whatsapp qr code
Route::get('/whatsapp-qr/{vendorUid}/{phoneNumber}', [
    HomeController::class,
    'generateWhatsAppQR',
])->name('vendor.whatsapp_qr');

// whatsapp webhook
Route::any('whatsapp-webhook/{vendorUid}', [
    WhatsAppServiceController::class,
    'webhook',
])->name('vendor.whatsapp_webhook');

// for cron job to run schedule
Route::get('/run-cron-schedule/{token?}', [
    WhatsAppServiceController::class,
    'runCampaignSchedule',
])->name('campaign.run_schedule.process');
