<?php
/**
 * CampaignEngine.php - Main component file
 *
 * This file is part of the Campaign component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Campaign;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\Campaign\Interfaces\CampaignEngineInterface;
use App\Yantrana\Components\Campaign\Repositories\CampaignRepository;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;

class CampaignEngine extends BaseEngine implements CampaignEngineInterface
{
    /**
     * @var CampaignRepository - Campaign Repository
     */
    protected $campaignRepository;

    /**
     * Constructor
     *
     * @param  CampaignRepository  $campaignRepository  - Campaign Repository
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    /**
     * Campaign datatable source
     *
     * @return array
     *---------------------------------------------------------------- */
    public function prepareCampaignDataTableSource()
    {
        $campaignCollection = $this->campaignRepository->fetchCampaignDataTableSource();
        $timeNow = now();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'title',
            'template_name',
            'template_language',
            'created_at' => function ($rowData) {
                return formatDateTime($rowData['created_at']);
            },
            'scheduled_at' => function ($rowData) {
                return (!$rowData['scheduled_at'] or ($rowData['scheduled_at'] != $rowData['created_at'])) ? '<span>📅 </span>' . formatDateTime($rowData['scheduled_at']) : '<span title="' . __tr('Instant') . '">⚡ </span>' . formatDateTime($rowData['scheduled_at']);
            },
            'scheduled_status' => function ($rowData) use (&$timeNow) {
                $statusText = __tr('Upcoming');
                if(Carbon::parse($rowData['scheduled_at']) < $timeNow) {
                    $statusText = __tr('Awaiting Execution');
                    if($rowData['queue_pending_messages_count'] and $rowData['message_log_count']) {
                         $statusText = __tr('Processing');
                    } elseif(!$rowData['queue_pending_messages_count']) {
                         $statusText = __tr('Executed');
                   } elseif(!$rowData['queue_pending_messages_count'] and !$rowData['message_log_count']) {
                        $statusText = __tr('NA');
                   }
                }
                return $statusText;
            },
            'delete_allowed' => function ($rowData) use (&$timeNow) {
                return (Carbon::parse($rowData['scheduled_at']) > $timeNow);
            },
        ];

        // prepare data for the DataTables
        return $this->dataTableResponse($campaignCollection, $requireColumns);
    }

    /**
     * Campaign delete process
     *
     * @param  mix  $campaignIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function processCampaignDelete($campaignIdOrUid)
    {
        // fetch the record
        $campaign = $this->campaignRepository->fetchIt($campaignIdOrUid);
        // check if the record found
        if (__isEmpty($campaign)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Campaign not found'));
        }
        // older campaigns can not be deleted
        if ($campaign->messageLog()->count()) {
            return $this->engineResponse(18, null, __tr('Executed Campaign can not be deleted'));
        }
        // ask to delete the record
        if ($this->campaignRepository->deleteIt($campaign)) {
            // if successful
            return $this->engineSuccessResponse([], __tr('Campaign deleted successfully'));
        }

        // if failed to delete
        return $this->engineFailedResponse([], __tr('Failed to delete Campaign'));
    }

    /**
     * Campaign prepare update data
     *
     * @param  mix  $campaignIdOrUid
     * @return object
     *---------------------------------------------------------------- */
    public function prepareCampaignUpdateData($campaignIdOrUid)
    {
        // data fetch request
        $campaign = $this->campaignRepository->fetchIt($campaignIdOrUid);
        // check if record found
        if (__isEmpty($campaign)) {
            // if record not found
            return $this->engineResponse(18, null, __tr('Campaign not found.'));
        }

        // if record found
        return $this->engineSuccessResponse($campaign->toArray());
    }

    /**
     * Campaign prepare update data
     *
     * @param  mix  $campaignIdOrUid
     * @return object
     *---------------------------------------------------------------- */
    public function prepareCampaignData($campaignIdOrUid)
    {
        // data fetch request
        // $campaign = $this->campaignRepository->with(['messageLog', 'queueMessages'])->fetchIt($campaignIdOrUid);
        $campaign = $this->campaignRepository->getCampaignData($campaignIdOrUid);
        // if record found
        abortIf(__isEmpty($campaign));
        $rawTime = Carbon::parse($campaign->scheduled_at, 'UTC');
        $scheduleAt = $rawTime->setTimezone($campaign->timezone);
        $campaign->scheduled_at_by_timezone = $scheduleAt;
        $statusText = __tr('Upcoming');
        $timeNow = now();
        if(Carbon::parse($campaign->scheduled_at) < $timeNow) {
            $statusText = __tr('Awaiting Execution');
            if($campaign->queue_pending_messages_count and $campaign->message_log_count) {
                    $statusText = __tr('Processing');
            } elseif(!$campaign->queue_pending_messages_count) {
                    $statusText = __tr('Executed');
            } elseif(!$campaign->queue_pending_messages_count and !$campaign->message_log_count) {
                $statusText = __tr('NA');
            }
        }
        if (Request::ajax() === true) {
            $messageLog = $campaign->messageLog;
            $queueMessages = $campaign->queueMessages;
            $campaignData = $campaign->__data;
            $totalContacts = (int) Arr::get($campaignData, 'total_contacts');
            $totalRead = $messageLog->where('status', 'read')->count();
            $totalReadInPercent = round($totalRead / $totalContacts * 100, 2) . '%';
            $totalDelivered = $messageLog->where('status', 'delivered')->count() + $totalRead;
            $totalDeliveredInPercent = round($totalDelivered / $totalContacts * 100, 2) . '%';
            $totalFailed = $queueMessages->where('status', 2)->count() + $messageLog->where('status', 'failed')->count();
            $totalFailedInPercent = round($totalFailed / $totalContacts * 100, 2) . '%';

            updateClientModels([
                'totalDelivered' => $totalDelivered,
                'totalDeliveredInPercent' => $totalDeliveredInPercent,
                'totalRead' => $totalRead,
                'totalReadInPercent' => $totalReadInPercent,
                'totalFailed' => $totalFailed,
                'statusText' => $statusText,
                'totalFailedInPercent' => $totalFailedInPercent,
                'executedCount' => $campaign->messageLog->count() ?? 0,
                'inQueuedCount' => $campaign->queueMessages->where('status', 1)->count() ?? 0,
            ]);
        }
        // if record found
        return $this->engineSuccessResponse([
            'campaign' => $campaign,
            'statusText' => $statusText,
        ]);
    }
    /**
     * Campaign prepare queue log data
     *
     * @param  mix  $campaignIdOrUid
     * @return object
     *---------------------------------------------------------------- */
    public function prepareCampaignQueueLogList($campaignIdOrUid)
    {
        // data fetch request
        $campaign = $this->campaignRepository->fetchIt($campaignIdOrUid);
        // data fetch request
        $campaignCollection = $this->campaignRepository->fetchCampaignQueueLogTableSource($campaign->_id);

        $requireColumns = [
            '_id',
            '_uid',
            'full_name' => function ($rowData) {
                $firstName = $rowData['first_name'];
                $lastName = $rowData['last_name'];
                return trim($firstName . ' ' . $lastName);
            },
            'phone_with_country_code' => function ($rowData) {
                $phoneNumber = $rowData['phone_with_country_code'];
                return $phoneNumber;
            },
            'updated_at' => function ($rowData) {
                $updatedTime = $rowData['formatted_updated_time'];
                return $updatedTime;
            },
            'status' => function ($rowData) {
                return $rowData['status'];
            },
            'whatsapp_message_error' => function ($rowData) {
                return $rowData['whatsapp_message_error'];
            },
        ];
        // prepare data for the DataTables
        return $this->dataTableResponse($campaignCollection, $requireColumns);
    }
    /**
     * Campaign prepare executed log data
     *
     * @param  mix  $campaignIdOrUid
     * @return object
     *---------------------------------------------------------------- */
    public function prepareCampaignExecutedLogList($campaignIdOrUid)
    {
        // data fetch request
        $campaign = $this->campaignRepository->fetchIt($campaignIdOrUid);
        // data fetch request
        $campaignCollection = $this->campaignRepository->fetchCampaignExecutedLogTableSource($campaign->_id);
        $requireColumns = [
            '_id',
            '_uid',
            'full_name' => function ($rowData) {
                $firstName = $rowData['first_name'];
                $lastName = $rowData['last_name'];
                return trim($firstName . ' ' . $lastName);
            },
            'contact_wa_id' => function ($rowData) {
                $contactId = $rowData['contact_wa_id'];
                return $contactId;
            },
            'status' => function ($rowData) {
                $status = $rowData['status'];
                return $status;
            },
            'messaged_at' => function ($rowData) {
                $messageAt = $rowData['formatted_message_time'];
                return $messageAt;
            },
            'updated_at' => function ($rowData) {
                $updatedTime = $rowData['formatted_updated_time'];
                return $updatedTime;
            },
        ];
        // prepare data for the DataTables
        return $this->dataTableResponse($campaignCollection, $requireColumns);
    }
}
