<?php
/**
* WhatsAppServiceEngine.php - Main component file
*
* This file is part of the WhatsAppService component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\WhatsAppService;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Yantrana\Base\BaseEngine;
use Illuminate\Support\Facades\Http;
use App\Events\VendorChannelBroadcast;
use App\Yantrana\Components\Media\MediaEngine;
use App\Yantrana\Components\Vendor\VendorSettingsEngine;
use App\Yantrana\Components\User\Repositories\UserRepository;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\BotReply\Repositories\BotReplyRepository;
use App\Yantrana\Components\Campaign\Repositories\CampaignRepository;
use App\Yantrana\Components\Contact\Repositories\ContactGroupRepository;
use App\Yantrana\Components\Contact\Repositories\GroupContactRepository;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppApiService;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppConnectApiService;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppTemplateRepository;
use App\Yantrana\Components\WhatsAppService\Interfaces\WhatsAppServiceEngineInterface;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageLogRepository;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageQueueRepository;
use App\Yantrana\Components\Configuration\ConfigurationEngine;

class WhatsAppServiceEngine extends BaseEngine implements WhatsAppServiceEngineInterface
{
    /**
     * @var ContactRepository - Contact Repository
     */
    protected $contactRepository;

    /**
     * @var ContactGroupRepository - ContactGroup Repository
     */
    protected $contactGroupRepository;

    /**
     * @var GroupContactRepository - ContactGroup Repository
     */
    protected $groupContactRepository;

    /**
     * @var WhatsAppTemplateRepository - WhatsApp Template Repository
     */
    protected $whatsAppTemplateRepository;

    /**
     * @var WhatsAppApiService - WhatsApp API Service
     */
    protected $whatsAppApiService;

    /**
     * @var MediaEngine - Media Engine
     */
    protected $mediaEngine;

    /**
     * @var WhatsAppMessageLogRepository - Status repository
     */
    protected $whatsAppMessageLogRepository;

    /**
     * @var WhatsAppMessageQueueRepository - WhatsApp Message Queue repository
     */
    protected $whatsAppMessageQueueRepository;
    /**
     * @var CampaignRepository - Campaign repository
     */
    protected $campaignRepository;

    /**
     * @var BotReplyRepository - Bot Reply repository
     */
    protected $botReplyRepository;

    /**
     * @var VendorSettingsEngine - Vendor Settings Engine
     */
    protected $vendorSettingsEngine;

    /**
     * @var UserRepository - UserRepository
     */
    protected $userRepository;

    /**
     * @var ConfigurationEngine - configurationEngine
     */
    protected $configurationEngine;

    /**
     * @var WhatsAppConnectApiService - WhatsApp Connect Service
     */
    protected $whatsAppConnectApiService;

    /**
     * Constructor
     *
     * @param  ContactRepository  $contactRepository  - Contact Repository
     * @param  ContactGroupRepository  $contactGroupRepository  - ContactGroup Repository
     * @param  GroupContactRepository  $groupContactRepository  - Group Contacts Repository
     * @param  WhatsAppTemplateRepository  $whatsAppTemplateRepository  - WhatsApp Templates Repository
     * @param  WhatsAppApiService  $whatsAppApiService  - WhatsApp API Service
     * @param  WhatsAppMessageQueueRepository  $whatsAppMessageQueueRepository  - WhatsApp Message Queue
     * @param  CampaignRepository  $campaignRepository  - Campaign repository
     * @param  BotReplyRepository  $botReplyRepository  - Bot Reply repository
     * @param  VendorSettingsEngine  $vendorSettingsEngine  - Configuration Engine
     * @param  UserRepository  $userRepository  - Users Repository
     * @param  ConfigurationEngine  $configurationEngine  - Configuration Engine
     * @param  WhatsAppConnectApiService  $whatsAppConnectApiService  - WhatsApp Connect Service
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        ContactRepository $contactRepository,
        ContactGroupRepository $contactGroupRepository,
        GroupContactRepository $groupContactRepository,
        WhatsAppTemplateRepository $whatsAppTemplateRepository,
        WhatsAppApiService $whatsAppApiService,
        MediaEngine $mediaEngine,
        WhatsAppMessageLogRepository $whatsAppMessageLogRepository,
        WhatsAppMessageQueueRepository $whatsAppMessageQueueRepository,
        CampaignRepository $campaignRepository,
        BotReplyRepository $botReplyRepository,
        VendorSettingsEngine $vendorSettingsEngine,
        UserRepository $userRepository,
        ConfigurationEngine $configurationEngine,
        WhatsAppConnectApiService $whatsAppConnectApiService
    ) {
        $this->contactRepository = $contactRepository;
        $this->contactGroupRepository = $contactGroupRepository;
        $this->groupContactRepository = $groupContactRepository;
        $this->whatsAppTemplateRepository = $whatsAppTemplateRepository;
        $this->whatsAppApiService = $whatsAppApiService;
        $this->mediaEngine = $mediaEngine;
        $this->whatsAppMessageLogRepository = $whatsAppMessageLogRepository;
        $this->whatsAppMessageQueueRepository = $whatsAppMessageQueueRepository;
        $this->campaignRepository = $campaignRepository;
        $this->botReplyRepository = $botReplyRepository;
        $this->vendorSettingsEngine = $vendorSettingsEngine;
        $this->userRepository = $userRepository;
        $this->configurationEngine = $configurationEngine;
        $this->whatsAppConnectApiService = $whatsAppConnectApiService;
    }

    /**
     * Get Contact Info
     *
     * @param  string  $contactUid
     * @return EngineResponse
     */
    public function sendMessageData($contactUid)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        $whatsAppApprovedTemplates = $this->whatsAppTemplateRepository->fetchItAll([
            'status' => 'APPROVED',
            'vendors__id' => $vendorId,
        ]);

        return $this->engineSuccessResponse([
            'contact' => $contact,
            'whatsAppTemplates' => $whatsAppApprovedTemplates,
            'template' => '',
            'templatePreview' => '',
        ]);
    }

    /**
     * Get Contact Info
     *
     * @param  string  $contactUid
     * @return EngineResponse
     */
    public function campaignRequiredData()
    {
        $vendorId = getVendorId();
        // templates
        $whatsAppApprovedTemplates = $this->whatsAppTemplateRepository->fetchItAll([
            'status' => 'APPROVED',
            'vendors__id' => $vendorId,
        ]);
        // contact groups
        $vendorContactGroups = $this->contactGroupRepository->fetchItAll([
            'vendors__id' => $vendorId,
        ]);

        return $this->engineSuccessResponse([
            'contact' => null,
            'whatsAppTemplates' => $whatsAppApprovedTemplates,
            'vendorContactGroups' => $vendorContactGroups,
            'template' => '',
            'templatePreview' => '',
        ]);
    }

    /**
     * Process the template change
     *
     * @param  string|int  $whatsAppTemplateId
     * @return EngineResponse
     */
    public function processTemplateChange($whatsAppTemplateId)
    {
        $preparedTemplateData = $this->prepareTemplate($whatsAppTemplateId);

        return $this->engineSuccessResponse([
            'template' => $preparedTemplateData['template'],
            'templateData' => $preparedTemplateData['templateData'],
        ]);
    }

    /**
     * Prepare Template with required parameters
     *
     * @param  string|int  $whatsAppTemplateId
     * @param  array  $options
     * @return array
     */
    protected function prepareTemplate($whatsAppTemplateId, array $options = [])
    {
        $options = array_merge([
            'templateComponents' => null,
        ], $options);
        // useful for message
        if ($whatsAppTemplateId == 'for_message') {
            $whatsAppTemplate = null;
            $templateComponents = &$options['templateComponents'];
        } else {
            $whatsAppTemplate = $this->whatsAppTemplateRepository->fetchIt($whatsAppTemplateId);
            abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template not found'));
            $templateComponents = Arr::get($whatsAppTemplate->toArray(), '__data.template.components');
        }

        $bodyComponentText = '';
        $headerComponentText = '';
        $componentButtonText = '';
        $buttonItems = [];
        $headerParameters = [];
        $headerFormat = null;
        $btnIndex = 0;
        $buttonParameters = [];
        foreach ($templateComponents as $templateComponent) {
            if ($templateComponent['type'] == 'HEADER') {
                $headerFormat = $templateComponent['format'];
                if ($templateComponent['format'] == 'TEXT') {
                    $headerComponentText = $templateComponent['text'];
                }
            } elseif ($templateComponent['type'] == 'BODY') {
                $bodyComponentText = $templateComponent['text'];
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                foreach ($templateComponent['buttons'] as $templateComponentButton) {
                    if ($templateComponentButton['type'] == 'URL' and (Str::contains($templateComponentButton['url'], '{{1}}'))) {
                        $buttonItems[] = [
                            'type' => $templateComponentButton['type'],
                            'url' => $templateComponentButton['url'],
                            'text' => $templateComponentButton['text'],
                        ];
                        $buttonParameters[] = "button_$btnIndex";
                    } elseif ($templateComponentButton['type'] == 'COPY_CODE') {
                        $buttonItems['COPY_CODE'] = [
                            'type' => $templateComponentButton['type'],
                            'text' => $templateComponentButton['text'],
                        ];
                    }
                    $btnIndex++;
                }
            }
        }
        // Regular expression to match {{number}}
        $pattern = '/{{\d+}}/';
        // Find matches
        preg_match_all($pattern, $headerComponentText, $headerVariableMatches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $headerParameters = array_map(function ($item) {
            return 'header_field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $headerVariableMatches[0]); // will contain all matched patterns
        // Find matches
        preg_match_all($pattern, $bodyComponentText, $matches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $bodyParameters = array_map(function ($item) {
            return 'field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $matches[0]); // will contain all matched patterns

        $templateDataPrepared = [
            'buttonItems' => $buttonItems,
            'templateComponents' => $templateComponents,
            'headerParameters' => $headerParameters,
            'buttonParameters' => $buttonParameters,
            'bodyParameters' => $bodyParameters,
            // 'buttonParameters' => $buttonParameters,
            'template' => $whatsAppTemplate,
            'headerFormat' => $headerFormat,
            // for preview
            'bodyComponentText' => $bodyComponentText,
            'contactDataMaps' => getContactDataMaps(),
        ];

        if ($options['templateComponents']) {
            return $templateDataPrepared;
        }

        return [
            'template' => view('whatsapp-service.message-preparation', $templateDataPrepared)->render(),
            'templateData' => $templateDataPrepared,
        ];
    }

    /**
     * Send message for selected contact
     *
     * @param  BaseRequestTwo  $request
     * @return EngineResponse
     */
    public function processSendMessageForContact($request)
    {
        $contact = $this->contactRepository->getVendorContact($request->get('contact_uid'));
        if (__isEmpty($contact)) {
            if (isExternalApiRequest() and $request->contact and is_array($request->contact)) {
                $contact = $this->createAContactForApiRequest($request);
            } else {
                return $this->engineFailedResponse([], __tr('Requested contact does not found'));
            }
        }

        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $contact->vendors__id);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        return $this->sendTemplateMessageProcess($request, $contact);
    }

    /**
     * Create contact if does not exist
     *
     * @param BaseRequestTwo $request
     * @return void
     */
    protected function createAContactForApiRequest($request)
    {
        $vendorId = getVendorId();
        // check the feature limit
        $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
            'vendors__id' => $vendorId
        ]), $vendorId);

        abortIf(!$vendorPlanDetails['is_limit_available'], null, $vendorPlanDetails['message']);

        $request->validate([
            'contact.first_name' => 'required',
            'contact.last_name' => 'required',
            'contact.country' => 'required',
            'contact.language_code' => 'nullable|alpha_dash',
            "phone_number" => [
                'required',
                'numeric',
                'min_digits:9',
                'min:1',
            ],
            'contact.email' => 'nullable|email',
        ]);

        $dataForContact = Arr::only($request->contact, [
            'first_name',
            'last_name',
            'language_code',
            'email',
            'country',
        ]);
        abortIf(str_starts_with($request->phone_number, '0') or str_starts_with($request->phone_number, '+'), null, 'phone number should be numeric value without prefixing 0 or +');
        // create contact
        return $this->contactRepository->storeContact([
            'first_name' => $dataForContact['first_name'],
            'last_name' => $dataForContact['last_name'] ?? '',
            'email' => $dataForContact['email'] ?? '',
            'language_code' => $dataForContact['language_code'] ?? '',
            'phone_number' => $request->phone_number,
            'country' => getCountryIdByName($dataForContact['country'] ?? null),
        ], $vendorId);
    }

    /**
     * get Current Billing Cycle
     *
     * @param string $subscriptionStartDate
     * @return array
     */
    protected function getCurrentBillingCycleDates($subscriptionStartDate)
    {
        $today = Carbon::now();
        $startOfMonth = new Carbon($subscriptionStartDate);
        // Adjust the start date to the current period
        $startOfMonth->year($today->year)->month($today->month);
        if ($today->day < $startOfMonth->day) {
            // If today is before the subscription day this month, start from last month
            $startOfMonth->subMonth();
        }
        $endOfMonth = (clone $startOfMonth)->addMonth()->subDay(); // End of this billing cycle
        return [
            'start' => $startOfMonth->startOfDay(), // Ensure time part is zeroed out
            'end' => $endOfMonth->endOfDay(), // Include the entire last day
        ];
    }

    /**
     * Process the message for Campaign creation
     *
     * @param  Request  $request
     * @return EngineResponse
     */
    public function processCampaignCreate($request)
    {
        $vendorId = getVendorId();
        // check the feature limit
        $subscription = getVendorCurrentActiveSubscription($vendorId);
        $currentBillingCycle = $this->getCurrentBillingCycleDates($subscription->created_at ?? getUserAuthInfo('vendor_created_at'));
        $vendorPlanDetails = vendorPlanDetails('campaigns', $this->campaignRepository->countIt([
            'vendors__id' => $vendorId,
            [
                'created_at', '>=', $currentBillingCycle['start'],
            ], [
                'created_at', '<=', $currentBillingCycle['end'],
            ]
        ]), $vendorId);
        if (!$vendorPlanDetails['is_limit_available']) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        $scheduleAt = $request->get('schedule_at');
        $timezone = $request->get('timezone');
        // if seconds missing, complete required date time format
        if(strlen($scheduleAt) == 16) {
            $scheduleAt = $scheduleAt . ':00';
        }
        if($scheduleAt) {
            try {
                $rawTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $scheduleAt, $timezone);
                $scheduleAt = $rawTime->setTimezone('UTC');
            } catch (\Throwable $th) {
                return $this->engineFailedResponse([], __tr('Failed to recognize the datetime, please reload and try again.'));
            }
        } else {
            $scheduleAt = now();
        }
        $whatsAppTemplate = $this->whatsAppTemplateRepository->fetchIt($request->template_uid);
        abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template not found in the system'));
        $contactGroupId = $request->contact_group;
        $restrictByTemplateContactLanguage = $request->restrict_by_templated_contact_language == 'on';
        $contactsWhereClause = [
            'vendors__id' => $vendorId,
        ];
        if($restrictByTemplateContactLanguage) {
            $contactsWhereClause['language_code'] = $whatsAppTemplate->language;
        }
        if($contactGroupId == 'all_contacts') {
            $contacts = $this->contactRepository->fetchItAll($contactsWhereClause);
        } else {
            $contactGroup = $this->contactGroupRepository->fetchIt([
                '_id' => $contactGroupId,
                'vendors__id' => $vendorId,
            ]);
            if(__isEmpty($contactGroup)) {
                return $this->engineFailedResponse([], __tr('Invalid Group'));
            }
            $groupContacts = $this->groupContactRepository->fetchItAll([
                'contact_groups__id' => $contactGroupId
            ]);
            if(__isEmpty($groupContacts)) {
                return $this->engineFailedResponse([], __tr('Group Contact does not found'));
            }
            $groupContactIds = $groupContacts->pluck('contacts__id')->toArray();
            $contacts = $this->contactRepository->fetchItAll($groupContactIds, [], '_id', [
                'where' => $contactsWhereClause
            ]);
        }

        if(__isEmpty($contacts)) {
            return $this->engineFailedResponse([], __tr('Contacts does not found'));
        }
        $testContactUid = getVendorSettings('test_recipient_contact');
        if(!$testContactUid) {
            return $this->engineFailedResponse([], __tr('Test Contact missing, You need to set the Test Contact first, do it under the WhatsApp Settings'));
        }
        $contact = $this->contactRepository->getVendorContact($testContactUid);
        if (__isEmpty($contact)) {
            return $this->engineFailedResponse([], __tr('Test contact does not found'));
        }
        // send test message
        $isTestMessageProcessed = $this->sendTemplateMessageProcess($request, $contact, false, null, $vendorId, $whatsAppTemplate);
        if($isTestMessageProcessed->failed()) {
            return $this->engineFailedResponse([], __tr('Failed to send test message'));
        }
        // remove test message log entry
        $this->whatsAppMessageLogRepository->deleteIt([
            '_uid' => $isTestMessageProcessed->data('messageUid')
        ]);
        $totalContacts = $contacts->count();
        $campaign = $this->campaignRepository->storeIt([
            'status' => 1,
            'vendors__id' => $vendorId,
            'users__id' => getUserID(),
            'title' => $request->title,
            'template_name' => $whatsAppTemplate->template_name,
            'template_language' => $whatsAppTemplate->language,
            'whatsapp_templates__id' => $whatsAppTemplate->_id,
            'scheduled_at' => $scheduleAt,
            'timezone' => $timezone,
            '__data' => [
                'total_contacts' => $totalContacts,
                'is_for_template_language_only' => $restrictByTemplateContactLanguage,
                'is_all_contacts' => $contactGroupId == 'all_contacts',
                'selected_groups' => $contactGroupId == 'all_contacts' ? [] : [
                    $contactGroup->_uid => [
                        '_id' => $contactGroup->_id,
                        '_uid' => $contactGroup->_uid,
                        'title' => $contactGroup->title,
                        'description' => $contactGroup->description,
                        'total_group_contacts' => $totalContacts,
                    ]
                ]
            ],
        ]);
        $queueData = [];
        foreach ($contacts as $contact) {
            $templateMessageSentProcess = $this->sendTemplateMessageProcess($request, $contact, true, $campaign->_id, $vendorId, $whatsAppTemplate, $isTestMessageProcessed->data('inputs'));
            $queueData[] = [
                'vendors__id' => $vendorId,
                'status' => 1, // queue
                'scheduled_at' => $scheduleAt,
                'phone_with_country_code' => $contact->wa_id,
                'campaigns__id' => $campaign->_id,
                'contacts__id' => $contact->_id,
                '__data' => [
                    'contact_data' => [
                        '_id' => $contact->_id,
                        '_uid' => $contact->_uid,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'countries__id' => $contact->countries__id,
                    ],
                    'campaign_data' => $templateMessageSentProcess->data()
                ]
            ];
        }
        if($this->whatsAppMessageQueueRepository->storeItAll($queueData)) {
            return $this->engineSuccessResponse([
                'campaignUid' => $campaign->_uid
            ], __tr('Test Message success and Campaign created'));
        }
        return $this->engineFailedResponse([
            'campaignUid' => $campaign->_uid
        ], __tr('Failed to queue messages for campaign'));
    }

    /**
     * Process the queued messages
     *
     * @return EngineResponse
     */
    public function processCampaignSchedule()
    {
        // set that cron job is done
        if(!getAppSettings('cron_setup_using_artisan_at') and app()->runningConsoleCommand('schedule:run')) {
            $this->configurationEngine->processConfigurationsStore('internals', [
                'cron_setup_using_artisan_at' => now()
            ]);
        }
        $queuedMessages = $this->whatsAppMessageQueueRepository->getQueueItemsForProcess();
        foreach ($queuedMessages as $queuedMessage) {
            try {
                // fetch the latest record
                $queuedMessage = $this->whatsAppMessageQueueRepository->fetchIt($queuedMessage->_id);
                // if record not found or if its already in process
                if(__isEmpty($queuedMessage) || ($queuedMessage->status == 3)) {
                    continue;
                }
                $this->whatsAppMessageQueueRepository->updateIt($queuedMessage, [
                    'status' => 3, // processing
                ]);
                $contactsData = $queuedMessage->__data['contact_data'];
                $campaignData = $queuedMessage->__data['campaign_data'];
                $processedResponse = $this->sendActualWhatsAppTemplateMessage(
                    $queuedMessage->vendors__id,
                    $contactsData['_id'],
                    $queuedMessage->phone_with_country_code,
                    $contactsData['_uid'],
                    $campaignData['whatsAppTemplateName'],
                    $campaignData['whatsAppTemplateLanguage'],
                    $campaignData['templateProforma'],
                    $campaignData['templateComponents'],
                    $campaignData['messageComponents'],
                    $queuedMessage->campaigns__id,
                    $contactsData,
                    ($campaignData['fromPhoneNumberId'] ?? null)
                );
                if($processedResponse->success()) {
                    $this->whatsAppMessageQueueRepository->deleteIt($queuedMessage);
                }
            } catch (Exception $e) {
                $this->whatsAppMessageQueueRepository->updateIt($queuedMessage, [
                    'status' => 2, // error
                    '__data' => [
                        'process_response' => [
                            'error_message' => $e->getMessage()
                        ]
                    ]
                ]);
            }
        }
        return $this->engineSuccessResponse([], __tr('Message processed'));
    }

    /**
     * Template Message Sending Process
     *
     * @param Request $request
     * @param object $contact
     * @param boolean $isForCampaign
     * @param int $campaignId
     * @param int $vendorId
     * @param object $whatsAppTemplate
     * @param array $inputs
     * @return EngineResponse
     */
    public function sendTemplateMessageProcess($request, $contact, $isForCampaign = false, $campaignId = null, $vendorId = null, $whatsAppTemplate = null, $inputs = null)
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        $inputs = $inputs ?: $request->all();
        if($request->template_name and isExternalApiRequest()) {
            $whatsAppTemplate = $whatsAppTemplate ?: $this->whatsAppTemplateRepository->fetchIt([
                'template_name' => $request->template_name,
                'language' => $request->template_language
            ]);
        } else {
            $whatsAppTemplate = $whatsAppTemplate ?: $this->whatsAppTemplateRepository->fetchIt($inputs['template_uid']);
        }

        abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template for the selected language not found in the system, if you have created template recently on Facebook please sync templates again.'));

        $contactWhatsappNumber = $contact->whatsappNumber;
        $templateProforma = Arr::get($whatsAppTemplate->toArray(), '__data.template');
        $templateComponents = Arr::get($templateProforma, 'components');
        $componentValidations = [];
        $bodyComponentText = '';
        $headerComponentText = '';
        $componentButtonText = '';
        $pattern = '/{{\d+}}/';
        foreach ($templateComponents as $templateComponent) {
            if ($templateComponent['type'] == 'HEADER') {
                $headerFormat = $templateComponent['format'];
                if ($headerFormat == 'TEXT') {
                    $headerComponentText = $templateComponent['text'];
                    // Find matches
                    preg_match_all($pattern, $headerComponentText, $headerMatches);
                    array_map(function ($item) use (&$componentValidations) {
                        $item = 'header_field_' . strtr($item, [
                            '{{' => '',
                            '}}' => '',
                        ]);
                        $componentValidations[$item] = [
                            'required',
                        ];

                        return $item;
                    }, $headerMatches[0]); // will contain all matched patterns
                } elseif ($headerFormat == 'LOCATION') {
                    $componentValidations['location_latitude'] = [
                        'required',
                        'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
                    ];
                    $componentValidations['location_longitude'] = [
                        'required',
                        'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/',
                    ];
                    $componentValidations['location_name'] = [
                        'required',
                        'string',
                    ];
                    $componentValidations['location_address'] = [
                        'required',
                        'string',
                    ];
                } elseif ($headerFormat == 'IMAGE') {
                    $componentValidations['header_image'] = [
                        'required',
                    ];
                } elseif ($headerFormat == 'VIDEO') {
                    $componentValidations['header_video'] = [
                        'required',
                    ];
                } elseif ($headerFormat == 'DOCUMENT') {
                    $componentValidations['header_document'] = [
                        'required',
                    ];
                    $componentValidations['header_document_name'] = [
                        'required',
                    ];
                }
            } elseif ($templateComponent['type'] == 'BODY') {
                $bodyComponentText = $templateComponent['text'];
                // Find matches
                preg_match_all($pattern, $bodyComponentText, $matches);
                array_map(function ($item) use (&$componentValidations) {
                    $item = 'field_' . strtr($item, [
                        '{{' => '',
                        '}}' => '',
                    ]);
                    $componentValidations[$item] = [
                        'required',
                    ];

                    return $item;
                }, $matches[0]); // will contain all matched patterns
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                $btnIndex = 0;
                foreach ($templateComponent['buttons'] as $templateComponentButton) {
                    if ($templateComponentButton['type'] == 'URL' and (Str::contains($templateComponentButton['url'], '{{1}}'))) {
                        $componentValidations["button_$btnIndex"] = [
                            'required',
                        ];
                    } elseif ($templateComponentButton['type'] == 'COPY_CODE') {
                        $componentValidations['copy_code'] = [
                            'required',
                            'alpha_dash',
                        ];
                    }
                    $btnIndex++;
                }
            }
        }
        if(!$isForCampaign) {
            $request->validate($componentValidations);
        }
        unset($componentValidations);

        // process the data
        // Regular expression to match {{number}}
        $pattern = '/{{\d+}}/';
        // Find matches
        preg_match_all($pattern, $headerComponentText, $headerVariableMatches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $headerParameters = array_map(function ($item) {
            return 'header_field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $headerVariableMatches[0]); // will contain all matched patterns

        preg_match_all($pattern, $componentButtonText, $buttonWordsMatches);
        $buttonParameters = array_map(function ($item) {
            return 'button_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $buttonWordsMatches[0]); // will contain all matched patterns

        $componentBodyIndex = 0;
        $mainIndex = 0;
        $componentBody = [];
        $componentBody[$mainIndex] = [
            'type' => 'body',
            'parameters' => [],
        ];
        foreach ($inputs as $inputItemKey => $inputItemValue) {
            if (Str::startsWith($inputItemKey, 'field_')) {
                $valueKeyName = str_replace('field_', '', $inputItemKey);
                $componentBody[$mainIndex]['parameters']["{{{$valueKeyName}}}"] = [
                    'type' => 'text',
                    'text' => $this->setParameterValue($contact, $inputs, $inputItemKey),
                ];
            }
            $componentBodyIndex++;
        }
        $componentButtons = [];
        $parametersComponentsCreations = [
            'COPY_CODE',
        ];
        foreach ($templateComponents as $templateComponent) {
            // @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages/#media-messages
            // @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/media#supported-media-types
            if ($templateComponent['type'] == 'HEADER') {
                if ($templateComponent['format'] == 'VIDEO') {
                    $mainIndex++;
                    if(isset($inputs['header_video']) and isValidUrl($inputs['header_video'])) {
                        $inputs['whatsapp_video'] = $inputs['header_video'];
                    } elseif(!isset($inputs['whatsapp_video'])) {
                        $inputHeaderVideo = $inputs['header_video'];
                        $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderVideo], 'whatsapp_video');
                        if ($isProcessed->failed()) {
                            return $isProcessed;
                        }
                        $inputs['whatsapp_video'] = $isProcessed->data('path');
                    }
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'video',
                                'video' => [
                                    'link' => $inputs['whatsapp_video'],
                                ],
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'IMAGE') {
                    $mainIndex++;
                    if(isset($inputs['header_image']) and isValidUrl($inputs['header_image'])) {
                        $inputs['whatsapp_image'] = $inputs['header_image'];
                    } elseif(!isset($inputs['whatsapp_image'])) {
                        $inputHeaderImage = $inputs['header_image'];
                        $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderImage], 'whatsapp_image');
                        if ($isProcessed->failed()) {
                            return $isProcessed;
                        }
                        $inputs['whatsapp_image'] = $isProcessed->data('path');
                    }
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                    'link' => $inputs['whatsapp_image'],
                                ],
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'DOCUMENT') {
                    $mainIndex++;
                    $inputHeaderDocument = $inputs['header_document'];
                    if(isset($inputs['header_document']) and isValidUrl($inputs['header_document'])) {
                        $inputs['whatsapp_document'] = $inputs['header_document'];
                    } elseif(!isset($inputs['whatsapp_document'])) {
                        $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderDocument], 'whatsapp_document');
                        if ($isProcessed->failed()) {
                            return $isProcessed;
                        }
                        $inputs['whatsapp_document'] = $isProcessed->data('path');
                    }

                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'filename' => $this->setParameterValue($contact, $inputs, 'header_document_name'),
                                    'link' => $inputs['whatsapp_document'],
                                ],
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'LOCATION') {
                    // @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates/#location
                    $mainIndex++;
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'location',
                                'location' => [
                                    'latitude' => $this->setParameterValue($contact, $inputs, 'location_latitude'),
                                    'longitude' => $this->setParameterValue($contact, $inputs, 'location_longitude'),
                                    'name' => $this->setParameterValue($contact, $inputs, 'location_name'),
                                    'address' => $this->setParameterValue($contact, $inputs, 'location_address'),
                                ],
                            ],
                        ],
                    ];
                } elseif (($templateComponent['format'] == 'TEXT') and Str::contains($templateComponent['text'], '{{1}}')) {
                    // @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
                    $mainIndex++;
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $this->setParameterValue($contact, $inputs, 'header_field_1'),
                            ],
                        ],
                    ];
                }
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                $componentButtonIndex = 0;
                $skipComponentsCreations = [
                    // 'URL',
                    'PHONE_NUMBER',
                ];
                foreach ($templateComponent['buttons'] as $templateComponentButton) {
                    // or check if this type is skipped from components creations
                    if (! in_array($templateComponentButton['type'], $skipComponentsCreations)) {
                        // create component block
                        $componentButtons[$mainIndex] = [
                            'type' => 'button',
                            'sub_type' => $templateComponentButton['type'],
                            'index' => $componentButtonIndex,
                            'parameters' => [],
                        ];
                        // create coupon code parameters
                        if (in_array($templateComponentButton['type'], $parametersComponentsCreations)) {
                            $componentButtons[$mainIndex]['parameters'][] = [
                                'type' => 'COUPON_CODE',
                                'coupon_code' => $this->setParameterValue($contact, $inputs, 'copy_code'),
                            ];
                        } elseif // create url parameters
                        (in_array($templateComponentButton['type'], ['URL']) and Str::contains($templateComponentButton['url'], '{{1}}')) {
                            $componentButtons[$mainIndex]['parameters'][] = [
                                'type' => 'text',
                                'text' => $this->setParameterValue($contact, $inputs, "button_$componentButtonIndex"),
                            ];
                        }
                    }
                    $componentButtonIndex++;
                    $mainIndex++;
                }
            }
        }
        // remove static links buttons
        foreach ($componentButtons as $componentButtonKey => $componentButton) {
            if(empty($componentButton['parameters'])) {
                unset($componentButtons[$componentButtonKey]);
            }
        }
        $messageComponents = array_merge($componentBody, $componentButtons);
        $contactId = $contact->_id;
        $contactUid = $contact->_uid;

        if($isForCampaign) {
            return $this->engineSuccessResponse([
                'whatsAppTemplateName' => $whatsAppTemplate->template_name,
                'whatsAppTemplateLanguage' => $whatsAppTemplate->language,
                'templateProforma' => $templateProforma,
                'templateComponents' => $templateComponents,
                'messageComponents' => $messageComponents,
                'inputs' => $inputs,
                'fromPhoneNumberId' => $request->from_phone_number_id,
            ], __tr('Message prepared for WhatsApp campaign'));
        }

        $contactsData = [
            '_id' => $contact->_id,
            '_uid' => $contact->_uid,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'countries__id' => $contact->countries__id,
            'is_template_test_contact' => $request->is_template_test_contact
        ];
        $processedResponse = $this->sendActualWhatsAppTemplateMessage(
            $vendorId,
            $contactId,
            $contactWhatsappNumber,
            $contactUid,
            $whatsAppTemplate->template_name,
            $whatsAppTemplate->language,
            $templateProforma,
            $templateComponents,
            $messageComponents,
            $campaignId,
            $contactsData,
            $request->from_phone_number_id
        );
        $processedResponse->updateData('inputs', $inputs);
        return $processedResponse;
    }

    /**
     * Send Interactive Message Process
     *
     * @param Request $request
     * @param boolean $isMediaMessage
     * @param integer $vendorId
     * @param array $options
     * @return EngineResponse
     */
    public function sendInteractiveMessageProcess($request, bool $isMediaMessage = false, $vendorId = null, $options = [])
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        if(is_array($request) === true) {
            $messageBody = $request['messageBody'];
            $contactUid = $request['contactUid'];
        } else {
            $messageBody = $request->message_body;
            $contactUid = $request->contact_uid;
        }
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        // mark unread chats as read if any
        $this->markAsReadProcess($contact, $vendorId);
        $mediaData = [];
        $serviceName = getAppSettings('name');

        $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, [
            'interactive_type' => '',
            'media_link' => '', //'https://camo.envatousercontent.com/a58d29650808a6231c7b785929abc438ac9910a3/68747470733a2f2f692e696d6775722e636f6d2f5a36367a3834682e706e67',
            'header_type' => '', // "text", "image", or "video"
            'header_text' => '',
            'body_text' => '',
            'footer_text' => '',
            'buttons' => [
            ],
            'cta_url' => null,
        ], $vendorId);
        $messageWamid = Arr::get($sendMessageResult, 'messages.0.id');
        if (! $messageWamid) {
            return $this->engineFailedResponse([
                'contact' => $contact,
            ], __tr('Failed to send message'));
        }
        $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
            getVendorSettings('current_phone_number_id', null, null, $vendorId),
            $contact->_id,
            $vendorId,
            $contact->wa_id,
            $messageWamid,
            'accepted',
            $sendMessageResult,
            $messageBody,
            null,
            $mediaData,
            false,
            $options
        );
        // update the client models by existing it
        updateClientModels([
            'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
        ], 'extend');

        return $this->engineSuccessResponse([
            'contact' => $contact,
        ], __tr('Message processed'));
    }

    /**
     * Actual Template Message Process
     *
     * @param integer $vendorId
     * @param integer $contactId
     * @param integer|string $contactWhatsappNumber
     * @param string $contactUid
     * @param string $whatsAppTemplateName
     * @param string $whatsAppTemplateLanguage
     * @param array $templateProforma
     * @param array $templateComponents
     * @param array $messageComponents
     * @param integer|null $campaignId
     * @param array|null $contactsData
     * @return EngineResponse
     */
    protected function sendActualWhatsAppTemplateMessage(
        int $vendorId,
        int $contactId,
        int|string $contactWhatsappNumber,
        string $contactUid,
        string $whatsAppTemplateName,
        string $whatsAppTemplateLanguage,
        array $templateProforma,
        array $templateComponents,
        array $messageComponents,
        ?int $campaignId = null,
        ?array $contactsData = null,
        $fromPhoneNumberId = null
    ) {
        $currentPhoneNumberId = $fromPhoneNumberId ?: getVendorSettings('current_phone_number_id', null, null, $vendorId);
        fromPhoneNumberIdForRequest($currentPhoneNumberId);
        $sendMessageResult = $this->whatsAppApiService->sendTemplateMessage($whatsAppTemplateName, $whatsAppTemplateLanguage, $contactWhatsappNumber, $messageComponents, $vendorId);
        $messageResponseStatus = Arr::get($sendMessageResult, 'messages.0.message_status');

        // store it into db
        $recordCreated = $this->whatsAppMessageLogRepository->storeIt([
            'vendors__id' => $vendorId,
            'status' => $messageResponseStatus,
            'contacts__id' => $contactId,
            'campaigns__id' => $campaignId,
            'wab_phone_number_id' => $currentPhoneNumberId,
            'is_incoming_message' => 0,
            'contact_wa_id' => Arr::get($sendMessageResult, 'contacts.0.wa_id'),
            'wamid' => Arr::get($sendMessageResult, 'messages.0.id'),
            '__data' => [
                'contact_data' => $contactsData,
                'initial_response' => $sendMessageResult,
                'template_proforma' => $templateProforma,
                'template_components' => $templateComponents,
                'template_component_values' => $messageComponents,
            ],
        ]);
        if ($messageResponseStatus == 'accepted') {
            return $this->engineSuccessResponse([
                'messageUid' => $recordCreated->_uid,
                'contactUid' => $contactUid,
                'log_message' => $recordCreated,
                'contact' => $this->contactRepository->fetchIt($contactUid),
            ], __tr('Message processed for WhatsApp contact'));
        }

        return $this->engineFailedResponse([], __tr('Failed to process Message for WhatsApp contact. Status: __messageStatus__', [
            '__messageStatus__' => $messageResponseStatus,
        ]));
    }


    /**
     * Mark unread messages as read
     *
     * @param  eloquent  $contact
     * @return void
     */
    public function markAsReadProcess($contact, $vendorId)
    {
        if(!__isEmpty($contact)) {
            if ($contact->lastUnreadMessage) {
                ignoreFacebookApiError(true);
                try {
                    $this->whatsAppApiService->markAsRead($contact->lastUnreadMessage->wa_id, $contact->lastUnreadMessage->wamid, $vendorId);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                ignoreFacebookApiError(false);
            }
            $this->whatsAppMessageLogRepository->markAsRead($contact, $vendorId);
            // update unread count
            $this->updateUnreadCount();
        }
    }

    /**
     * Prepare chat window data
     *
     * @return EngineResponse
     */
    public function chatData(string|null $contactUid, string|null $assigned)
    {
        $vendorId = getVendorId();
        if(isDemo() and isDemoVendorAccount() and !$contactUid) {
            $contactUid = getVendorSettings('test_recipient_contact');
        }
        $contact = $this->contactRepository->getVendorContactWithUnreadDetails($contactUid, $vendorId);
        // if(!__isEmpty($contact)) {
            // mark unread chats as read
            $this->markAsReadProcess($contact, $vendorId);
        // }

        if(__isEmpty($contact)) {
            /* return $this->engineSuccessResponse([
                'contact' => null,
                'assigned' => $assigned,
            ]); */
            return $this->engineSuccessResponse([
                // check if received incoming message from contact in last 24 hours
                // the direct message won't be delivered if not received any message by user in last 24 hours
                'isDirectMessageDeliveryWindowOpened' => null,
                'directMessageDeliveryWindowOpenedTillMessage' => '',
                'contact' => null,
                'contacts' => [],
                'whatsappMessageLogs' => [],
                // get the vendor users having messaging permission
                'vendorMessagingUsers' => $this->userRepository->getVendorMessagingUsers($vendorId),
                'assigned' => $assigned,
                'currentlyAssignedUserUid' => '',
            ]);
        }

        return $this->engineSuccessResponse([
            // check if received incoming message from contact in last 24 hours
            // the direct message won't be delivered if not received any message by user in last 24 hours
            'isDirectMessageDeliveryWindowOpened' => (! __isEmpty($contact->lastIncomingMessage) and ($contact->lastIncomingMessage?->messaged_at?->diffInHours() < 24)),
            'directMessageDeliveryWindowOpenedTillMessage' => __tr('Reply window open for __availableTimeForReply__', [
                '__availableTimeForReply__' => $contact->lastIncomingMessage?->messaged_at?->addHours(24)->diffForHumans([
                    'parts' => 2,
                    'join' => true,
                ])
            ]),
            'contact' => $contact,
            'contacts' => $this->contactsData($contact)->data('contacts'),
            'whatsappMessageLogs' => $this->getContactMessagesForChatBox($contact->_id),
            // get the vendor users having messaging permission
            'vendorMessagingUsers' => $this->userRepository->getVendorMessagingUsers($vendorId),
            'assigned' => $assigned,
            'currentlyAssignedUserUid' => $contact->assignedUser->_uid ?? '',
        ]);
    }

    /**
     * Prepare the contact messages for the chat box
     *
     * @param integer $contactId
     * @param boolean $onlyRecent
     * @return object
     */
    protected function getContactMessagesForChatBox(int $contactId, bool $onlyRecent = false)
    {
        if (! $onlyRecent) {
            $resultOfMessages = $this->whatsAppMessageLogRepository->allMessagesOfContact($contactId);
        } else {
            $resultOfMessages = $this->whatsAppMessageLogRepository->recentMessagesOfContact($contactId);
        }

        return $resultOfMessages->keyBy('_uid')->transform(function ($item, string $key) {
            $item->message = $this->formatWhatsAppText($item->message);
            $item->template_message = null;
            if (! $item->message || Arr::get($item->__data, 'interaction_message_data')) {
                $item->template_message = $this->compileMessageWithValues($item->__data);
            }

            return $item;
        });
    }

    /**
     * Prepare Single chat logs for current selected user
     *
     * @return EngineResponse
     */
    public function contactChatData(string|int $contactIdOrUid)
    {
        $contactId = is_string($contactIdOrUid) ? $this->contactRepository->getVendorContact($contactIdOrUid)->_id : $contactIdOrUid;

        return $this->engineSuccessResponse([
            'whatsappMessageLogs' => $this->getContactMessagesForChatBox($contactId, true),
        ]);
    }

    /**
     * Prepare Single chat logs for current selected user
     *
     * @param  string|int  $contactUid
     * @return EngineResponse
     */
    public function contactsData($contactUid)
    {
        $vendorId = getVendorId();
        if(is_string($contactUid)) {
            $contact = $this->contactRepository->getVendorContactWithUnreadDetails($contactUid, $vendorId);
        } else {
            $contact = $contactUid;
        }
        $vendorContactsWithUnreadDetails = $this->contactRepository->getVendorContactsWithUnreadDetails($vendorId);
        if(!__isEmpty($contact)) {
            $isContactInTheList = $vendorContactsWithUnreadDetails->where('_id', $contact->_id)->count();
            if(!$isContactInTheList) {
                $vendorContactsWithUnreadDetails = $vendorContactsWithUnreadDetails->toBase()->merge([$contact]);
            }
        }
        return $this->engineSuccessResponse([
            'contacts' => $vendorContactsWithUnreadDetails->keyBy('_uid')->sortByDesc(function ($contact, $key) {
                return $contact->lastMessage?->messaged_at;
            }),
        ]);
    }

    /**
     * Compile Message with required values
     *
     * @param array $messageData
     * @return view|string
     */
    protected function compileMessageWithValues($messageData)
    {
        if (isset($messageData['interaction_message_data'])) {
            return view('whatsapp-service.interaction-message-partial', [
                'mediaValues' => array_merge([
                    'media_link' => '',
                    'header_type' => '', // "text", "image", or "video"
                    'header_text' => '',
                    'body_text' => '',
                    'footer_text' => '',
                    'buttons' => [
                    ],
                ], $messageData['interaction_message_data']),
            ])->render();
        } elseif (isset($messageData['media_values'])) {
            $messageData['media_values']['caption'] = $this->formatWhatsAppText($messageData['media_values']['caption'] ?? '');
            return view('whatsapp-service.media-message-partial', [
                'mediaValues' => array_merge([
                    'link' => null,
                    'type' => null,
                    'caption' => null,
                    'file_name' => null,
                    'original_filename' => null,
                ], $messageData['media_values']),
            ])->render();
        }

        if (! isset($messageData['template_components']) or ! isset($messageData['template_component_values'])) {
            return null;
        }
        $templateComponents = $messageData['template_components'];
        $templateComponentValues = $messageData['template_component_values'];
        $bodyItemValues = [];
        $headerItemValues = [
            'image' => null,
            'video' => null,
            'document' => null,
            'location' => null,
            'text' => [],
        ];
        $buttonIndex = 1;
        $buttonValues = [];
        foreach ($templateComponentValues as $templateComponentValue) {
            if ($templateComponentValue['type'] == 'body') {
                foreach ($templateComponentValue['parameters'] as $templateComponentValueParameterKey => $templateComponentValueParameter) {
                    $bodyItemValues[$templateComponentValueParameterKey] = $templateComponentValueParameter['text'];
                }
            } elseif ($templateComponentValue['type'] == 'header') {
                foreach ($templateComponentValue['parameters'] as $templateComponentValueParameterKey => $templateComponentValueParameter) {
                    if ($templateComponentValueParameter['type'] == 'image') {
                        $headerItemValues['image'] = $templateComponentValueParameter['image']['link'] ?? '';
                    } elseif ($templateComponentValueParameter['type'] == 'video') {
                        $headerItemValues['video'] = $templateComponentValueParameter['video']['link'] ?? '';
                    } elseif ($templateComponentValueParameter['type'] == 'document') {
                        $headerItemValues['document'] = $templateComponentValueParameter['document']['link'] ?? '';
                    } elseif ($templateComponentValueParameter['type'] == 'location') {
                        $headerItemValues['location'] = $templateComponentValueParameter['location'] ?? [
                            'name' => '',
                            'address' => '',
                            'latitude' => null,
                            'longitude' => null,
                        ];
                    } elseif ($templateComponentValueParameter['type'] == 'text') {
                        $headerItemValues['text'][] = $templateComponentValueParameter['text'];
                    }
                }
            } elseif ($templateComponentValue['type'] == 'button') {
                if ($templateComponentValue['sub_type'] == 'URL') {
                    if (isset($templateComponentValue['parameters'][0]['text'])) {
                        $buttonValues["{{{$buttonIndex}}}"] = $templateComponentValue['parameters'][0]['text'];
                        $buttonIndex++;
                    }
                } elseif ($templateComponentValue['sub_type'] == 'COPY_CODE') {
                    $buttonValues['COPY_CODE'] = $templateComponentValue['parameters'][0]['coupon_code'] ?? null;
                }
            }
        }

        return view('whatsapp-service.message-template-partial', array_merge($this->prepareTemplate('for_message', [
            'templateComponents' => $templateComponents,
        ]), [
            'templateComponentValues' => $templateComponentValues,
            'headerItemValues' => $headerItemValues,
            'bodyItemValues' => $bodyItemValues,
            'buttonValues' => $buttonValues,
        ]))->render();
    }

    /**
     * Clear Chat history for the Contact
     *
     * @param string $contactUid
     * @return void
     */
    public function processClearChatHistory($contactUid)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        if ($this->whatsAppMessageLogRepository->deleteItAll([
            'vendors__id' => $contact->vendors__id,
            // 'wab_phone_number_id' => getVendorSettings('current_phone_number_id'),
            'contacts__id' => $contact->_id,
        ])) {
            updateClientModels([
                'whatsappMessageLogs' => [],
            ]);

            return $this->engineSuccessResponse([], __tr('Chat history has been cleared for __contactFullName__', [
                '__contactFullName__' => $contact->full_name,
            ]));
        }

        return $this->engineFailedResponse([], __tr('Failed to clear chat history for __contactFullName__', [
            '__contactFullName__' => $contact->full_name,
        ]));
    }

    /**
     * Send Chat Message Process
     *
     * @param Request $request
     * @param boolean $isMediaMessage
     * @param integer $vendorId
     * @param array $options
     * @return EngineResponse
     */
    public function processSendChatMessage($request, bool $isMediaMessage = false, $vendorId = null, $options = [])
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }
        $interactionMessageData = null;
        $mediaMessageData = null;
        $fromPhoneNumberId = null;
        if(is_array($request) === true) {
            $messageBody = $request['messageBody'];
            $contactUid = $request['contactUid'];
            if(isset($options['interaction_message_data']) and !empty($options['interaction_message_data'])) {
                $interactionMessageData = $options['interaction_message_data'];
            }
            if(isset($options['media_message_data']) and !empty($options['media_message_data'])) {
                $mediaMessageData = $options['media_message_data'];
            }
        } else {
            $messageBody = $request->message_body;
            $contactUid = $request->contact_uid;
            $fromPhoneNumberId = $request->from_phone_number_id;
        }
        // options extend
        $options = array_merge([
            'from_phone_number_id' => $fromPhoneNumberId
        ], $options);

        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        if (__isEmpty($contact)) {
            if (isExternalApiRequest() and $request->contact and is_array($request->contact)) {
                $contact = $this->createAContactForApiRequest($request);
            } else {
                return $this->engineFailedResponse([], __tr('Requested contact does not found'));
            }
        }
        $currentBusinessPhoneNumber = $options['from_phone_number_id'] ?: ($contact->lastMessage->wab_phone_number_id ?? getVendorSettings('current_phone_number_id', null, null, $vendorId));
        fromPhoneNumberIdForRequest($currentBusinessPhoneNumber);
        $initializeLogMessage = null;
        // to show message in chat instantly we have created first log entry
        // only for chat box
        if(!isExternalApiRequest() and $messageBody) {
            $initializeLogMessage = $this->whatsAppMessageLogRepository->storeIt([
                'is_incoming_message' => 0,
                'status' => 'initialize',
                'contact_wa_id' => $contact->wa_id,
                'contacts__id' => $contact->_id,
                'vendors__id' => $vendorId,
                'wab_phone_number_id' => $currentBusinessPhoneNumber,
                'message' => $messageBody,
                'messaged_at' => now(),
                '__data' => [
                    'options' => Arr::only($options, [
                        'bot_reply',
                        'ai_bot_reply',
                    ]),
                ],
            ]);
            // update message list
            updateClientModelsViaEvent([
                'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
            ]);
            // useful for scrolling
            dispatchStreamEventData('onChatBoxMessageSubmit', []);
            $options['message_log_id'] = $initializeLogMessage->_id;
        }
        if(Arr::get($options, 'ai_error_triggered') != true) {
            // mark unread chats as read if any
            $this->markAsReadProcess($contact, $vendorId);
        }

        $mediaData = [];
        $serviceName = getAppSettings('name');
        if ($interactionMessageData) {
            $interactionMessageData['body_text'] = isDemo() ? "{$serviceName} DEMO - " . $interactionMessageData['body_text'] : $interactionMessageData['body_text'];
            $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, $interactionMessageData, $contact->vendors__id);
        } elseif ($isMediaMessage) {
            $fileUrl = $fileName = $fileOriginalName = null;
            $rawUploadData = [];
            $caption = $request->caption ?? '';
            $mediaType = $request->media_type ?? '';
            if($mediaMessageData) {
                $fileName = $mediaMessageData['file_name'];
                $fileUrl = $mediaMessageData['media_link'];
                $fileOriginalName = $mediaMessageData['file_name'];
                $caption = $mediaMessageData['caption'];
                $mediaType = $mediaMessageData['header_type'];
            } elseif(!isValidUrl($request->media_url)) {
                $rawUploadData = json_decode($request->raw_upload_data, true);
                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $request->uploaded_media_file_name], 'whatsapp_' . $mediaType);
                if ($isProcessed->failed()) {
                    return $isProcessed;
                }
                $fileUrl = $isProcessed->data('path');
                $fileName = $isProcessed->data('fileName');
                $fileOriginalName = Arr::get($rawUploadData, 'original_filename');
            } else {
                $fileName = $request->file_name;
                $fileUrl = $request->media_url;
                $fileOriginalName = $fileName;
            }
            $sendMessageResult = $this->whatsAppApiService->sendMediaMessage($contact->wa_id, $mediaType, $fileUrl, (isDemo() ? "{$serviceName} DEMO - " . $caption : '' . $caption), $fileOriginalName, $vendorId);
            $mediaData = [
                'type' => $mediaType,
                'link' => $fileUrl,
                'caption' => $caption,
                'mime_type' => Arr::get($rawUploadData, 'fileMimeType'),
                'file_name' => $fileName,
                'original_filename' => $fileOriginalName,
            ];
        } else {
            $sendMessageResult = $this->whatsAppApiService->sendMessage($contact->wa_id, (isDemo() ? "`{$serviceName} DEMO`\n\r\n\r " . $messageBody : '' . $messageBody), $vendorId);
        }
        $messageWamid = Arr::get($sendMessageResult, 'messages.0.id');
        if (! $messageWamid) {
            if($initializeLogMessage) {
                $initializeLogMessage->status =  'failed';
                $initializeLogMessage->save();
            }
            return $this->engineFailedResponse([
                'contact' => $contact,
            ], __tr('Failed to send message'));
        }
        if($initializeLogMessage) {
            $initializeLogMessage->wamid =  $messageWamid;
            $initializeLogMessage->save();
        }
        $logMessage = $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
            $currentBusinessPhoneNumber,
            $contact->_id,
            $vendorId,
            $contact->wa_id,
            $messageWamid,
            'accepted',
            $sendMessageResult,
            $messageBody,
            null,
            $mediaData,
            false,
            $options
        );
        // update the client models by existing it
        updateClientModels([
            'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
        ], 'extend');

        return $this->engineSuccessResponse([
            'contact' => $contact,
            'log_message' => $logMessage,
        ], isExternalApiRequest() ? __tr('Message processed') : '');
    }

    /**
     * Set the Parameters to to concerned template dynamic values
     *
     * @param object $contact
     * @param array $inputs
     * @param mixed $item
     * @return mixed
     */
    protected function setParameterValue(&$contact, &$inputs, $item)
    {
        $inputValue = $inputs[$item];

        if(isExternalApiRequest()) {
            return $this->dynamicValuesReplacement($inputValue, $contact);
        }
        // for any internal requests
        if (Str::startsWith($inputValue, 'dynamic_contact_')) {
            // assign phone value
            if($inputValue == 'dynamic_contact_phone_number') {
                $inputValue = 'dynamic_contact_wa_id';
            }
            // check if value permitted
            if(!array_key_exists($inputValue, configItem('contact_data_mapping'))) {
                return null;
            }
            // correct the name
            $fieldName = str_replace('dynamic_contact_', '', $inputValue);
            // country value
            switch ($fieldName) {
                case 'country':
                    return $contact->country?->name;
                    break;
            }
            return $contact->{$fieldName};
            // custom field values
        } elseif (Str::startsWith($inputValue, 'contact_custom_field_')) {
            $fieldName = str_replace('contact_custom_field_', '', $inputValue);
            // for api external request find value by field name
            if(isExternalApiRequest()) {
                return $contact->valueWithField?->firstWhere('customField.input_name', $fieldName)?->field_value;
            }
            return $contact->customFieldValues?->firstWhere('contact_custom_fields__id', $fieldName)?->field_value ?: '';
        }
        return $inputs[$item];
    }

    /**
     * Prepare string with values replacements
     *
     * @param string $inputValue
     * @param Eloquent $contact
     * @return string
     */
    protected function dynamicValuesReplacement($inputValue, &$contact)
    {
        $dynamicFieldsToReplace = [
            '{first_name}' => $contact->first_name,
            '{last_name}' => $contact->last_name,
            '{full_name}' => $contact->first_name . ' ' . $contact->last_name,
            '{phone_number}' => $contact->wa_id,
            '{email}' => $contact->email,
            '{country}' => $contact->country?->name,
            '{language_code}' => $contact->language_code,
        ];
        // Review this code and make the appropriate changes
        $valueWithFields = $contact->valueWithField;
        foreach ($valueWithFields as $valueWithField) {
            if($valueWithField?->customField?->input_name) {
                $dynamicFieldsToReplace["{{$valueWithField->customField->input_name}}"] = $valueWithFields?->firstWhere('customField.input_name', $valueWithField->customField->input_name)?->field_value;
            }
        }
        // assign dynamic values
        return strtr($inputValue, $dynamicFieldsToReplace);
    }

    /**
     * Send message processed by bot reply
     *
     * @param string $contactUid
     * @param string $replyText
     * @param int $vendorId
     * @param array|null $interactionMessageData
     * @return void
     */
    protected function sendReplyBotMessage($contactUid, $replyText, $vendorId, $interactionMessageData = null, $options = [])
    {
        $options = array_merge([
            'from_phone_number_id' => null
        ], $options);

        $mediaMessageData = $options['mediaMessageData'] ?? null;
        return $this->processSendChatMessage(
            [
            'contactUid' => $contactUid,
            'messageBody' => $replyText,
        ],
            $mediaMessageData ? true : false,
            $vendorId,
            [
            'bot_reply' => true,
            'ai_bot_reply' => $options['ai_bot_reply'] ?? false,
            'ai_error_triggered' => $options['ai_error_triggered'] ?? false,
            'media_message_data' => $mediaMessageData,
            'interaction_message_data' => $interactionMessageData,
            'from_phone_number_id' => $options['from_phone_number_id']
        ]
        );
    }

    /**
     * Validate Bot Reply
     *
     * @param integer $testBotId
     * @return EngineResponse
     */
    public function validateTestBotReply(int $testBotId)
    {
        $testContactUid = getVendorSettings('test_recipient_contact');
        if(!$testContactUid) {
            return $this->engineFailedResponse([], __tr('Test Contact missing, You need to set the Test Contact first, do it under the WhatsApp Settings'));
        }
        $contact = $this->contactRepository->getVendorContact($testContactUid);
        if (__isEmpty($contact)) {
            return $this->engineFailedResponse([], __tr('Test contact does not found'));
        }
        return $this->processReplyBot($contact, '', $testBotId);
    }

    /**
     * Process the bot reply if required
     *
     * @param Eloquent Object $contact
     * @param string $messageBody
     * @return void
     */
    public function processReplyBot($contact, $messageBody, $testBotId = null, $options = [])
    {
        if(__isEmpty($contact)) {
            return false;
        }
        $options = array_merge([
            'fromPhoneNumberId' => null
        ], $options);
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $contact->vendors__id);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return false;
        }
        $messageBody = strtolower(trim($messageBody));
        $allBotReplies = $this->botReplyRepository->fetchItAll([
            'vendors__id' => $contact->vendors__id,
        ], [
            '_id',
            'reply_trigger',
            'reply_text',
            'trigger_type',
            'priority_index',
            '__data',
        ])->sortBy('priority_index');
        $isBotMatched = false;
        if(!__isEmpty($allBotReplies)) {
            // check if we already have incoming message 2 days
            $isIncomingMessageExists = $this->whatsAppMessageLogRepository->countIt([
                'vendors__id' => $contact->vendors__id,
                'contacts__id' => $contact->_id,
                'is_incoming_message' => 0,
            ]);
            foreach ($allBotReplies as $botReply) {
                $replyTrigger = strtolower($botReply->reply_trigger);
                // testing the bot reply
                if($testBotId and ($testBotId != $botReply->_id)) {
                    continue;
                } elseif($testBotId) {
                    $messageBody = $replyTrigger;
                }
                $replyText = null;
                $isBotMatchedForThisReply = false;
                $interactionMessageData = $botReply->__data['interaction_message'] ?? null;
                $mediaMessageData = $botReply->__data['media_message'] ?? null;
                if($botReply->trigger_type == 'welcome') {
                    if(!$isIncomingMessageExists or $testBotId) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                }
                if($botReply->trigger_type == 'is') {
                    if(Str::is($replyTrigger, $messageBody)) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                } elseif($botReply->trigger_type == 'starts_with') {
                    if(Str::startsWith($messageBody, $replyTrigger)) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                } elseif($botReply->trigger_type == 'ends_with') {
                    if(Str::endsWith($messageBody, $replyTrigger)) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                } elseif($botReply->trigger_type == 'contains_word') {
                    // Prepare the pattern to search for the whole word
                    // \b represents a word boundary in regex
                    $pattern = '/\b' . preg_quote($replyTrigger, '/') . '\b/u';
                    // Use preg_match to search the haystack for the whole word
                    if(preg_match($pattern, $messageBody) > 0) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                } elseif($botReply->trigger_type == 'contains') {
                    if(Str::contains($messageBody, $replyTrigger)) {
                        $replyText = $botReply->reply_text;
                        $isBotMatchedForThisReply = true;
                    }
                }
                // if reply text is ready
                if($isBotMatchedForThisReply) {
                    $isBotMatched = true;
                    if($replyText) {
                        $replyText = $this->dynamicValuesReplacement($replyText, $contact);
                    }
                    // if interaction message
                    if($interactionMessageData) {
                        // body text
                        $interactionMessageData['body_text'] = $replyText;
                        // header text assignments
                        if($interactionMessageData['header_text']) {
                            $interactionMessageData['header_text'] = $this->dynamicValuesReplacement($interactionMessageData['header_text'], $contact);
                        }
                        // footer text assignments
                        if($interactionMessageData['footer_text']) {
                            $interactionMessageData['footer_text'] = $this->dynamicValuesReplacement($interactionMessageData['footer_text'], $contact);
                        }
                    } elseif($mediaMessageData) {
                        // caption text
                        $mediaMessageData['caption'] = $this->dynamicValuesReplacement($mediaMessageData['caption'], $contact);
                    }
                    $sendReplyBotMessageResponse = $this->sendReplyBotMessage($contact->_uid, $replyText, $contact->vendors__id, $interactionMessageData, [
                        'mediaMessageData' => $mediaMessageData,
                        'from_phone_number_id' => $options['fromPhoneNumberId']
                    ]);
                    if($testBotId) {
                        return $sendReplyBotMessageResponse;
                    }
                }
            }
        }
        if($testBotId) {
            return $this->engineFailedResponse([], __tr('Bot Validation Failed due to unmatched'));
        }
        // initial ai bot only if manual bot didn't replied
        // ai bot is enabled
        // bot url has been set
        // contact ai bot replies is not disabled
        // has in subscription plan
        $vendorPlanDetails = vendorPlanDetails('ai_chat_bot', 0, $contact->vendors__id);
        if(!$isBotMatched and $vendorPlanDetails['is_limit_available'] and getVendorSettings('enable_flowise_ai_bot', null, null, $contact->vendors__id) and getVendorSettings('flowise_url', null, null, $contact->vendors__id) and !$contact->disable_ai_bot) {
            try {
                // base request start
                $botRequest = Http::throw(function ($response, $e) {
                    __logDebug($e->getMessage());
                });
                // set the token if required
                if($bearerToken = getVendorSettings('flowise_access_token', null, null, $contact->vendors__id)) {
                    $botRequest->withToken($bearerToken);
                }
                $aiBotReplyText = $botRequest->post(getVendorSettings('flowise_url', null, null, $contact->vendors__id), [
                    'question' => $messageBody,
                ])->json('text');
                // check if got the reply
                if($aiBotReplyText) {
                    $this->sendReplyBotMessage($contact->_uid, $aiBotReplyText, $contact->vendors__id, null, [
                        'ai_bot_reply' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId']
                    ]);
                }
            } catch (\Throwable $e) {
                __logDebug($e->getMessage());
                // send error message to the customers
                if(getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id)) {
                    $this->sendReplyBotMessage($contact->_uid, getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id), $contact->vendors__id, null, [
                        'ai_error_triggered' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId']
                    ]);
                }
            }
        }
    }
    /**
     * Webhook for message handling
     *
     * @param Request $request
     * @param string $vendorUid
     * @return void
     */
    public function processWebhook($request, $vendorUid)
    {
        $vendorId = getPublicVendorId($vendorUid);
        if (! $vendorId) {
            return false;
        }
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            abort(403, 'no active subscription');
        }
        $messageEntry = $request->get('entry');
        $phoneNumberId = Arr::get($messageEntry, '0.changes.0.value.metadata.phone_number_id');
        $messageStatusObject = Arr::get($messageEntry, '0.changes.0.value.statuses');
        $messageObject = Arr::get($messageEntry, '0.changes.0.value.messages');

       // set the webhook messages field as configured if not already done
        if(!getVendorSettings('webhook_messages_field_verified_at', null, null, $vendorUid)
           and (Arr::get($messageEntry, '0.changes.0.field') == 'messages')) {
            $this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
                'webhook_messages_field_verified_at' => now()
            ], $vendorId);
            // messages
            updateModelsViaVendorBroadcast($vendorUid, [
                'isWebhookMessagesFieldVerified' => true
            ]);
            // if its test message notification then get back
            if(Arr::get($messageObject, '0.text.body') == 'this is a text message') {
                return false;
            }
        }

        $waId = null;
        $contactUid = null;
        $campaignUid = null;
        $messageWamid = null;
        // mainly for incoming message
        $messageBody = null;
        $isNewIncomingMessage = false;
        if ($messageStatusObject) {
            $waId = Arr::get($messageStatusObject, '0.recipient_id'); // recipient
            $messageWamid = Arr::get($messageStatusObject, '0.id');
            $messageStatus = Arr::get($messageStatusObject, '0.status');
            $timestamp = Arr::get($messageStatusObject, '0.timestamp');
            $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
            if (__isEmpty($contact)) {
                return false;
            }
            $contactUid = $contact->_uid;
            // Update Record for sent message
            $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $waId,
                $messageWamid,
                $messageStatus,
                $messageEntry,
                null,
                $timestamp,
                null,
                true // do not create new record if not found
            );
        }
        // incoming message
        elseif ($messageObject) {
            $waId = Arr::get($messageObject, '0.from');
            $messageWamid = Arr::get($messageObject, '0.id');
            $messageType = Arr::get($messageObject, '0.type');
            $messageBody = null;
            $isNewIncomingMessage = true;
            $mediaData = [];
            if (in_array($messageType, [
                'text',
            ])) {
                $messageBody = Arr::get($messageObject, '0.text.body');
            } elseif (in_array($messageType, [
               'interactive',
            ])) {
                $messageBody = Arr::get($messageObject, '0.interactive.button_reply.title');
            } elseif (in_array($messageType, [
                'button',
            ])) {
                $messageBody = Arr::get($messageObject, '0.button.text');
            } elseif (in_array($messageType, [
                'image',
                'video',
                'audio',
                'document',
            ])) {
                $downloadedFileInfo = $this->mediaEngine->downloadAndStoreMediaFile($this->whatsAppApiService->downloadMedia(Arr::get($messageObject, "0.$messageType.id"), $vendorId), $vendorUid, $messageType);
                $mediaData = [
                    'type' => $messageType,
                    'link' => Arr::get($downloadedFileInfo, 'path'),
                    'caption' => Arr::get($messageObject, "0.$messageType.caption"),
                    'mime_type' => Arr::get($messageObject, "0.$messageType.mime_type"),
                    'file_name' => Arr::get($downloadedFileInfo, 'fileName'),
                    'original_filename' => Arr::get($downloadedFileInfo, 'fileName'),
                ];
            }
            $timestamp = Arr::get($messageObject, '0.timestamp');
            $reactionMessage = Arr::get($messageObject, '0.reaction.emoji');
            if($reactionMessage) {
                $repliedToMessage = Arr::get($messageObject, '0.reaction.message_id');
                $messageBody = $reactionMessage;
            } else {
                // replied message
                $repliedToMessage = Arr::get($messageObject, '0.context.id');
            }
            if ($repliedToMessage) {
                $repliedToMessage = $this->whatsAppMessageLogRepository->fetchIt([
                    'wamid' => $repliedToMessage,
                    'vendors__id' => $vendorId,
                ]);
                if (! __isEmpty($repliedToMessage)) {
                    $repliedToMessage = $repliedToMessage->_uid;
                } else {
                    $repliedToMessage = null;
                }
            }
            $isForwarded = Arr::get($messageObject, '0.context.forwarded');
            $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
            if(!__isEmpty($contact)) {
                // update contact name
                if(!$contact->first_name) {
                    $profileName = Arr::get($messageEntry, '0.changes.0.value.contacts.0.profile.name');
                    $firstName = Arr::get(explode(' ', $profileName), '0');
                    $contact = $this->contactRepository->updateIt($contact, [
                        'first_name' => $firstName,
                        'last_name' => str_replace($firstName, ' ', $profileName),
                    ], $vendorId);
                }
            } else {
                // check the feature limit
                $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
                    'vendors__id' => $vendorId
                ]), $vendorId);
                if (!$vendorPlanDetails['is_limit_available']) {
                    return false;
                }
                $profileName = Arr::get($messageEntry, '0.changes.0.value.contacts.0.profile.name');
                $firstName = Arr::get(explode(' ', $profileName), '0');
                $contact = $this->contactRepository->storeContact([
                    'first_name' => $firstName,
                    'last_name' => str_replace($firstName, ' ', $profileName),
                    'phone_number' => $waId,
                ], $vendorId);
            }
            $contactUid = $contact->_uid;
            $hasLogEntryOfMessage = false;
            if($messageWamid) {
                $hasLogEntryOfMessage = $this->whatsAppMessageLogRepository->countIt([
                    'wamid' => $messageWamid,
                    'vendors__id' => $vendorId,
                ]);
            }
            // prevent repeated message creation
            if($hasLogEntryOfMessage) {
                return false;
            }
            // create Record for sent message
            $this->whatsAppMessageLogRepository->storeIncomingMessage(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $waId, // sender
                $messageWamid,
                $messageEntry,
                $messageBody,
                $timestamp,
                $mediaData,
                $repliedToMessage,
                $isForwarded
            );
        }
        if($messageWamid) {
            $messageLogEntry = $this->whatsAppMessageLogRepository->fetchIt([
                'wamid' => $messageWamid,
                'vendors__id' => $vendorId,
            ]);
            // get the campaign if required
            if(!__isEmpty($messageLogEntry) and $messageLogEntry->campaigns__id) {
                $campaign = $this->campaignRepository->fetchIt([
                    'vendors__id' => $vendorId,
                    '_id' => $messageLogEntry->campaigns__id,
                ]);
                if(!__isEmpty($campaign)) {
                    $campaignUid = $campaign->_uid;
                }
            }
        }

        if ($contactUid) {
            $contact = $this->contactRepository->with('lastMessage')->getVendorContactByWaId($waId, $vendorId);
            // Dispatch event for message
            event(new VendorChannelBroadcast($vendorUid, [
                'contactUid' => $contactUid,
                'isNewIncomingMessage' => $isNewIncomingMessage,
                'campaignUid' => $campaignUid,
                'lastMessageUid' => $contact->lastMessage?->_uid,
                'formatted_last_message_time' => $contact->lastMessage?->formatted_message_time,
            ]));

            if($messageBody) {
                fromPhoneNumberIdForRequest($phoneNumberId);
                // process the bot if needed any
                $this->processReplyBot($contact, $messageBody, null, [
                    'fromPhoneNumberId' => $phoneNumberId,
                ]);
            }
        }
        return true;
    }
    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function updateUnreadCount()
    {
        updateClientModels([
            'unreadMessagesCount' => $this->whatsAppMessageLogRepository->getUnreadCount(),
            'myAssignedUnreadMessagesCount' => $this->whatsAppMessageLogRepository->getMyAssignedUnreadMessagesCount()
        ]);
        return $this->engineSuccessResponse([]);
    }

    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function refreshHealthStatus()
    {
        $healthStatus = $this->whatsAppApiService->healthStatus();
        $whatsAppBusinessAccountId = getVendorSettings('whatsapp_business_account_id');
        if(!$whatsAppBusinessAccountId) {
            return $this->engineFailedResponse([], __tr('WhatsApp Business Account ID not found'));
        }
        $now = now();
        $healthData = [
            'whatsapp_health_status_data' => [
                $whatsAppBusinessAccountId => [
                    'whatsapp_business_account_id' => $whatsAppBusinessAccountId,
                    'health_status_updated_at' => $now,
                    'health_status_updated_at_formatted' => formatDateTime($now),
                    'health_data' => $healthStatus,
                ]
            ]
        ];
        // store information
        $this->vendorSettingsEngine->updateProcess('internals', $healthData);
        // update models
        updateClientModels([
            'healthStatusData' => $healthData['whatsapp_health_status_data'][$whatsAppBusinessAccountId]
        ]);
        return $this->engineSuccessResponse([], __tr('WhatsApp Business Health Data Refreshed'));
    }
    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function processSyncPhoneNumbers()
    {
        $phoneNumbers = $this->whatsAppApiService->phoneNumbers()['data'] ?? [];
        $whatsAppBusinessAccountId = getVendorSettings('whatsapp_business_account_id');
        if(!$whatsAppBusinessAccountId) {
            return $this->engineFailedResponse([], __tr('WhatsApp Business Account ID not found'));
        }
        $vendorId = getVendorId();
        $phoneNumberRecord = Arr::first(($phoneNumbers ?? []), function ($value, $key) {
            return $value['id'] == getVendorSettings('current_phone_number_id');
        });
        if(!$phoneNumberRecord) {
            $phoneNumberRecord = $phoneNumbers[0];
        }
        if(!$this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
            'whatsapp_phone_numbers' => $phoneNumbers,
            'current_phone_number_number' => cleanDisplayPhoneNumber($phoneNumberRecord['display_phone_number']),
            'current_phone_number_id' => $phoneNumberRecord['id'],
        ], $vendorId)) {
            return $this->engineFailedResponse(['show_message' => true], __tr('Failed to update Phone Numbers.'));
        };
        // update models
        updateClientModels([
            'whatsAppPhoneNumbers' => $phoneNumbers
        ]);
        return $this->engineSuccessResponse([], __tr('WhatsApp Business Phone Numbers Synced'));
    }

    /**
     * Format the message like whatsapp do
     *
     * @param string $text
     * @return string
     */
    protected function formatWhatsAppText($text)
    {
        // Bold: Wrap text marked with * in <strong> tags
        $text = preg_replace('/\*(.*?)\*/', '<strong>$1</strong>', $text);

        // Italics: Wrap text marked with _ in <em> tags
        $text = preg_replace('/\_(.*?)\_/', '<em>$1</em>', $text);

        // Strikethrough: Wrap text marked with ~ in <del> tags
        $text = preg_replace('/\~(.*?)\~/', '<del>$1</del>', $text);

        // Monospace: Wrap text marked with ``` in <code> tags
        // Use preg_quote to escape backticks for the pattern
        $backtickPattern = preg_quote('```', '/');
        $text = preg_replace("/{$backtickPattern}(.*?){$backtickPattern}/s", '<code>$1</code>', $text);

        // Single backtick: Replace with <span> tags
        $text = preg_replace('/`(.*?)`/', '<span class="badge badge-light">$1</span>', $text);

        // Convert URLs to clickable links, YouTube
        $text = preg_replace_callback(
            '/(https?:\/\/[^\s]+)/',
            function ($matches) {
                $url = $matches[0];
                // YouTube URL
                if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w\-]+)/', $url, $youtubeMatches)) {
                    return '<iframe width="100%" height="300" src="https://www.youtube.com/embed/' . $youtubeMatches[1] . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></br></br>' . '<a href="' . $url . '" target="_blank">' . $url . '</a>';
                } else {
                    return '<a href="' . $url . '" target="_blank">' . $url . '</a>';
                }
            },
            $text
        );
        // Convert email addresses to mailto links
        $text = preg_replace(
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/',
            '<a href="mailto:$1">$1</a>',
            $text
        );

        return $text;
    }

    public function setupWhatsAppEmbeddedSignUpProcess($request)
    {
        $processedResponse = $this->whatsAppConnectApiService->processEmbeddedSignUp($request);
        if($processedResponse->success()) {
            $this->refreshHealthStatus();
        }
        return $processedResponse;
    }
    /**
     * Disconnect Account
     *
     * @return EngineResponse
     */
    public function processDisconnectAccount($vendorId = null)
    {
        $vendorId  = $vendorId ?: getVendorId();
        if(!isWhatsAppBusinessAccountReady($vendorId)) {
            return $this->engineFailedResponse([], __tr('Account should be ready in order to disconnect.'));
        }
        // remove webhooks
        if(!getVendorSettings('embedded_setup_done_at', null, null, $vendorId)) {
            $this->processDisconnectWebhook($vendorId);
        } else {
            $this->whatsAppConnectApiService->removeExistingWebhooks(getVendorSettings('whatsapp_business_account_id', null, null, $vendorId), getVendorSettings('whatsapp_access_token', null, null, $vendorId));
        }
        if($this->vendorSettingsEngine->deleteItemProcess([
            'embedded_setup_done_at',
            'facebook_app_id',
            'facebook_app_secret',
            'whatsapp_access_token',
            'whatsapp_business_account_id',
            'current_phone_number_number',
            'current_phone_number_id',
            'webhook_verified_at',
            'webhook_messages_field_verified_at',
            'whatsapp_phone_numbers_data',
            'whatsapp_onboarding_raw_data',
        ], $vendorId)->success()) {
             return $this->engineSuccessResponse([], __tr('Account has been disconnected successfully'));
        }
        return $this->engineFailedResponse([], __tr('Failed to disconnect account'));
    }
    /**
     * Disconnect Webhook
     *
     * @return EngineResponse
     */
    public function processDisconnectWebhook($vendorId = null)
    {
        $vendorId  = $vendorId ?: getVendorId();
        if(!getVendorSettings('facebook_app_secret', null, null, $vendorId)) {
            return $this->engineFailedResponse([], __tr('Missing App Secret'));
        }
        $processedResponse = $this->whatsAppConnectApiService->disconnectBaseWebhook(getVendorSettings('facebook_app_id', null, null, $vendorId), getVendorSettings('facebook_app_secret', null, null, $vendorId), getVendorSettings('whatsapp_business_account_id', null, null, $vendorId));
        if(isset($processedResponse['success']) and $processedResponse['success']) {
            if($this->vendorSettingsEngine->deleteItemProcess([
                'webhook_verified_at',
                'webhook_messages_field_verified_at',
            ],$vendorId)->success()) {
                // messages
                updateClientModels([
                    'isWebhookMessagesFieldVerified' => false,
                    'isWebhookVerified' => false,
                ]);
                return $this->engineSuccessResponse([], __tr('Webhook Disconnected'));
            }
        }
        return $this->engineFailedResponse([], __tr('Nothing to disconnect'));
    }
    /**
     * Connect Webhook
     *
     * @return void
     */
    public function processConnectWebhook()
    {
        $vendorUid = getVendorUid();
        $processedResponse = $this->whatsAppConnectApiService->connectBaseWebhook(getVendorSettings('facebook_app_id'), getVendorSettings('facebook_app_secret'), $vendorUid);
        // Note: We use override webhook urls only for embedded signup
        // and as if vendor connected via Embedded signup they don't have facility to disconnect webhooks
        // thats why commented out the url
        // $processedResponse = $this->whatsAppConnectApiService->connectWebhookOverrides($vendorUid, getVendorSettings('whatsapp_business_account_id'));
        if(isset($processedResponse['success']) and $processedResponse['success']) {
            return $this->engineSuccessResponse([], __tr('Webhook Connected'));
        }
        return $this->engineFailedResponse([], __tr('Nothing to connect'));
    }
}
