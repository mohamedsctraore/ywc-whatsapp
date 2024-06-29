<?php

/**
 * DashboardEngine.php - Main component file
 *
 * This file is part of the Dashboard component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Dashboard;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\Dashboard\Interfaces\DashboardEngineInterface;
use App\Yantrana\Components\Vendor\Repositories\VendorRepository;
use App\Yantrana\Components\User\Repositories\UserRepository;

use Illuminate\Support\Carbon;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\BotReply\Repositories\BotReplyRepository;
use App\Yantrana\Components\Campaign\Repositories\CampaignRepository;
use App\Yantrana\Components\Contact\Repositories\ContactGroupRepository;
use App\Yantrana\Components\Contact\Repositories\GroupContactRepository;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppApiService;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppTemplateRepository;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageLogRepository;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageQueueRepository;

class DashboardEngine extends BaseEngine implements DashboardEngineInterface
{
    /**
     * @var VendorRepository - Vendor Repository
     */
    protected $vendorRepository;
    /**
     * @var UserRepository - User Repository
     */
    protected $userRepository;

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
     * Constructor
     *
     * @param  VendorRepository  $vendorRepository  - Vendor Repository
     * @param  UserRepository  $userRepository  - User Repository
     * @param  ContactRepository  $contactRepository  - Contact Repository
     * @param  ContactGroupRepository  $contactGroupRepository  - ContactGroup Repository
     * @param  GroupContactRepository  $groupContactRepository  - Group Contacts Repository
     * @param  WhatsAppTemplateRepository  $whatsAppTemplateRepository  - WhatsApp Templates Repository
     * @param  WhatsAppApiService  $whatsAppApiService  - WhatsApp API Service
     * @param  WhatsAppMessageQueueRepository  $whatsAppMessageQueueRepository  - WhatsApp Message Queue
     * @param  CampaignRepository  $campaignRepository  - Campaign repository
     * @param  BotReplyRepository  $botReplyRepository  - Bot Reply repository
     *
     * @return void
     */
    public function __construct(
        VendorRepository $vendorRepository,
        UserRepository $userRepository,
        ContactRepository $contactRepository,
        ContactGroupRepository $contactGroupRepository,
        GroupContactRepository $groupContactRepository,
        WhatsAppTemplateRepository $whatsAppTemplateRepository,
        WhatsAppApiService $whatsAppApiService,
        WhatsAppMessageLogRepository $whatsAppMessageLogRepository,
        WhatsAppMessageQueueRepository $whatsAppMessageQueueRepository,
        CampaignRepository $campaignRepository,
        BotReplyRepository $botReplyRepository
    ) {
        $this->vendorRepository = $vendorRepository;
        $this->userRepository = $userRepository;
        $this->contactRepository = $contactRepository;
        $this->contactGroupRepository = $contactGroupRepository;
        $this->groupContactRepository = $groupContactRepository;
        $this->whatsAppTemplateRepository = $whatsAppTemplateRepository;
        $this->whatsAppApiService = $whatsAppApiService;
        $this->whatsAppMessageLogRepository = $whatsAppMessageLogRepository;
        $this->whatsAppMessageQueueRepository = $whatsAppMessageQueueRepository;
        $this->campaignRepository = $campaignRepository;
        $this->botReplyRepository = $botReplyRepository;
    }

    /**
     * Prepare Vendor Dashboard Data
     *
     * @return array
     */
    public function prepareDashboardData()
    {
        
        return [
            'vendorRegistrations' => $this->vendorRepository->vendorRegistrationsStats(),
            'newVendors' => $this->vendorRepository->newVendors(),
            'totalVendors' => $this->vendorRepository->countIt(),
            'totalContacts' => $this->contactRepository->countIt(),
            'totalCampaigns' => $this->campaignRepository->countIt(),
            'messagesInQueue' => $this->whatsAppMessageQueueRepository->countIt([
                'status' => 1
            ]),
            'totalMessagesProcessed' => $this->whatsAppMessageLogRepository->countIt(),
            'totalActiveVendors' => $this->vendorRepository->countIt([
                'status' => 1,
            ]),
        ];
    }

    /**
     * Prepare Vendor Dashboard Data
     *
     * @return array
     */
    public function prepareVendorDashboardData($vendorId = null)
    {
      
        if (! $vendorId) {
            $vendorId = getVendorId();
        } else {
            if (is_string($vendorId)) {
                $vendor = $this->vendorRepository->fetchIt($vendorId);
                if (! __isEmpty($vendor)) {
                    $vendorId = $vendor->_id;
                }
            }
        }
        $vendorWhereClause = [
            'vendors__id' => $vendorId
        ];
    
        return array_merge([
            'firstOfMonth' => Carbon::now()->firstOfMonth(),
            'lastOfMonth' => Carbon::now()->lastOfMonth(),
            'vendorId' => $vendorId,
            'activeTeamMembers'=> $this->userRepository->countVendorsActiveUsers($vendorWhereClause),
            'totalContacts' => $this->contactRepository->countIt($vendorWhereClause),
            'totalGroups' => $this->contactGroupRepository->countIt($vendorWhereClause),
            'totalCampaigns' => $this->campaignRepository->countIt($vendorWhereClause),
            'totalTemplates' => $this->whatsAppTemplateRepository->countIt($vendorWhereClause),
            'totalBotReplies' => $this->botReplyRepository->countIt($vendorWhereClause),
            'messagesInQueue' => $this->whatsAppMessageQueueRepository->countIt([
                'status' => 1,
                'vendors__id' => $vendorId
            ]),
            'totalMessagesProcessed' => $this->whatsAppMessageLogRepository->countIt($vendorWhereClause),
        ]);
    }
}
