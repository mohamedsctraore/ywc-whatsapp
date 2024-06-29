<?php
/**
* BotReplyEngine.php - Main component file
*
* This file is part of the BotReply component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\BotReply;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use App\Yantrana\Base\BaseEngine;
use Illuminate\Database\Query\Builder;
use App\Yantrana\Components\Media\MediaEngine;
use App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine;
use App\Yantrana\Components\BotReply\Repositories\BotReplyRepository;
use App\Yantrana\Components\BotReply\Interfaces\BotReplyEngineInterface;
use App\Yantrana\Components\Contact\Repositories\ContactCustomFieldRepository;

class BotReplyEngine extends BaseEngine implements BotReplyEngineInterface
{
    /**
     * @var  BotReplyRepository $botReplyRepository - BotReply Repository
     */
    protected $botReplyRepository;

    /**
     * @var  ContactCustomFieldRepository $contactCustomFieldRepository - ContactCustomField Repository
     */
    protected $contactCustomFieldRepository;

    /**
     * @var MediaEngine - Media Engine
     */
    protected $mediaEngine;

    /**
     * @var WhatsAppServiceEngine - WhatsApp Service Engine
     */
    protected $whatsAppServiceEngine;

    /**
      * Constructor
      *
      * @param  BotReplyRepository $botReplyRepository - BotReply Repository
      *
      * @return  void
      *-----------------------------------------------------------------------*/

    public function __construct(
        BotReplyRepository $botReplyRepository,
        ContactCustomFieldRepository $contactCustomFieldRepository,
        MediaEngine $mediaEngine,
        WhatsAppServiceEngine $whatsAppServiceEngine,
    ) {
        $this->botReplyRepository = $botReplyRepository;
        $this->contactCustomFieldRepository = $contactCustomFieldRepository;
        $this->mediaEngine = $mediaEngine;
        $this->whatsAppServiceEngine = $whatsAppServiceEngine;
    }

    /**
     * Get contact dynamic fields and custom fields
     *
     * @return EngineResponse
     */
    public function preDataForBots()
    {
        $vendorId = getVendorId();

        $dynamicFieldsToReplace = [
            '{first_name}',
            '{last_name}',
            '{full_name}',
            '{phone_number}',
            '{email}',
            '{country}',
            '{language_code}',
        ];

        $customFields = $this->contactCustomFieldRepository->fetchItAll([
            'vendors__id' => $vendorId
        ]);

        foreach ($customFields as $customField) {
            $dynamicFieldsToReplace[] = "{{$customField->input_name}}";
        }

        return $this->engineSuccessResponse([
            'dynamicFields' => $dynamicFieldsToReplace
        ]);
    }

    /**
      * BotReply datatable source
      *
      * @return  array
      *---------------------------------------------------------------- */
    public function prepareBotReplyDataTableSource()
    {
        $botReplyCollection = $this->botReplyRepository->fetchBotReplyDataTableSource();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'name',
            'reply_text',
            'trigger_type',
            'reply_trigger',
            'created_at' => function ($rowData) {
                return formatDateTime($rowData['created_at']);
            },
            'bot_type' => function ($rowData) {
                $botReplyType = __tr('Simple');
                if($rowData['__data']['media_message'] ?? null) {
                    $botReplyType = __tr('Media');
                } elseif($rowData['__data']['interaction_message'] ?? null) {
                    $botReplyType = __tr('Interactive/Buttons');;
                }
                return $botReplyType;
            },
        ];
        // prepare data for the DataTables
        return $this->dataTableResponse($botReplyCollection, $requireColumns);
    }


    /**
      * BotReply delete process
      *
      * @param  mix $botReplyIdOrUid
      *
      * @return  array
      *---------------------------------------------------------------- */

    public function processBotReplyDelete($botReplyIdOrUid)
    {
        // fetch the record
        $botReply = $this->botReplyRepository->fetchIt($botReplyIdOrUid);
        // check if the record found
        if (__isEmpty($botReply)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Bot Reply not found'));
        }
        // ask to delete the record
        if ($this->botReplyRepository->deleteIt($botReply)) {
            // if successful
            return $this->engineResponse(1, null, __tr('Bot Reply deleted successfully'));
        }
        // if failed to delete
        return $this->engineResponse(2, null, __tr('Failed to delete BotReply'));
    }

    /**
      * BotReply create
      *
      * @param  array $inputData
      *
      * @return  array
      *---------------------------------------------------------------- */

    public function processBotReplyCreate($inputData)
    {
        $vendorId = getVendorId();
        $messageType = $inputData['message_type'] ?? 'simple';
        // check the feature limit
        $vendorPlanDetails = vendorPlanDetails('bot_replies', $this->botReplyRepository->countIt([
            'vendors__id' => $vendorId
        ]), $vendorId);
        if (!$vendorPlanDetails['is_limit_available']) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }
        $inputData['vendors__id'] = $vendorId;
        if($messageType == 'interactive') {
            $interactiveType = $inputData['interactive_type'] ?? 'button';
            $mediaLink = '';
            if($inputData['header_type'] and ($inputData['header_type'] != 'text')) {
                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputData['uploaded_media_file_name']], 'whatsapp_' . $inputData['header_type']);
                if ($isProcessed->failed()) {
                    return $isProcessed;
                }
                $mediaLink = $isProcessed->data('path');
            }
            $ctaUrlButton = null;
            if($interactiveType == 'cta_url') {
                $ctaUrlButton = [
                    'display_text' => $inputData['button_display_text'],
                    'url' => $inputData['button_url'],
                ];
            }
            $inputData['__data'] = [
                'interaction_message' => [
                    'interactive_type' => $interactiveType,
                    'media_link' => $mediaLink,
                    'header_type' => $inputData['header_type'], // "text", "image", or "video"
                    'header_text' => $inputData['header_text'] ?? '',
                    'body_text' => $inputData['reply_text'],
                    'footer_text' => $inputData['footer_text'] ?? '',
                    'buttons' => array_filter($inputData['buttons'] ?? []),
                    'cta_url' => $ctaUrlButton,
                ]
            ];
        } elseif($messageType == 'media') {
            $inputData['header_type'] = $inputData['media_header_type'];
            $mediaLink = '';
            $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputData['uploaded_media_file_name']], 'whatsapp_' . $inputData['header_type']);
            if ($isProcessed->failed()) {
                return $isProcessed;
            }
            $mediaLink = $isProcessed->data('path');
            $inputData['reply_text'] = '';
            $inputData['__data'] = [
                'media_message' => [
                    'media_link' => $mediaLink,
                    'header_type' => $inputData['header_type'], // "text", "image", "audio or "video"
                    'caption' => $inputData['caption'] ?? '',
                    'file_name' => $isProcessed->data('fileName'),
                ]
            ];
        }
        // ask to add record
        $engineResponse = $this->botReplyRepository->processTransaction(function () use (&$inputData) {
            if ($botReply = $this->botReplyRepository->storeBotReply($inputData)) {
                // if needs to validate message using by sending test message
                if($inputData['validate_bot_reply'] ?? null) {
                    $validateTestBotReply = $this->whatsAppServiceEngine->validateTestBotReply($botReply->_id);
                    if($validateTestBotReply->success()) {
                        return $this->botReplyRepository->transactionResponse(1, [], __tr('Bot Reply Created'));
                    }
                    // if got any errors etc
                    return $this->botReplyRepository->transactionResponse($validateTestBotReply->reaction(), [], $validateTestBotReply->message());
                }
                // success
                return $this->botReplyRepository->transactionResponse(1, [], __tr('Bot Reply Created'));
            }
            // failed for any other reason
            return $this->botReplyRepository->transactionResponse(2, [], __tr('Failed to create Bot Reply'));
        });
        return $this->engineResponse($engineResponse);

    }

    /**
      * BotReply prepare update data
      *
      * @param  mix $botReplyIdOrUid
      *
      * @return  array
      *---------------------------------------------------------------- */

    public function prepareBotReplyUpdateData($botReplyIdOrUid)
    {
        $botReply = $this->botReplyRepository->fetchIt($botReplyIdOrUid);

        // Check if $botReply not exist then throw not found
        // exception
        if (__isEmpty($botReply)) {
            return $this->engineResponse(18, null, __tr('Bot Reply not found.'));
        }

        return $this->engineResponse(1, $botReply->toArray());
    }

    /**
      * BotReply process update
      *
      * @param  mixed $botReplyIdOrUid
      * @param  object $request
      *
      * @return  array
      *---------------------------------------------------------------- */

    public function processBotReplyUpdate($botReplyIdOrUid, $request)
    {
        $botReply = $this->botReplyRepository->fetchIt($botReplyIdOrUid);
        // Check if $botReply not exist then throw not found
        // exception
        if (__isEmpty($botReply)) {
            return $this->engineResponse(18, null, __tr('Bot Reply not found.'));
        }
        $vendorId = getVendorId();
        $triggerTypeValidations = [];
        // validate for uniqueness
        $request->validate([
            "name" => [
                "required",
                Rule::unique('bot_replies')->where(fn (Builder $query) => $query->where('vendors__id', $vendorId))->ignore($botReply->_id, '_id')
            ],
            "reply_trigger" => $triggerTypeValidations,
        ]);

        $inputData = $request->all();
        $messageType = $inputData['message_type'] ?? 'simple';
        $updateData = [
            'name' => $inputData['name'],
            'reply_text' => $inputData['reply_text'] ?? '',
            'reply_trigger' => $request->reply_trigger,
        ];
        // media message
        if($messageType == 'media') {
            $updateData['__data'] = [
                'media_message' => [
                    'caption' => $inputData['caption'] ?? '',
                ]
            ];
        } elseif($messageType == 'interactive') {
            $interactiveType = $inputData['interactive_type'] ?? 'button';
            $ctaUrlButton = null;
            if($interactiveType == 'cta_url') {
                $ctaUrlButton = [
                    'display_text' => $inputData['button_display_text'],
                    'url' => $inputData['button_url'],
                ];
            }

            $updateData['__data'] = [
                'interaction_message' => [
                    'interactive_type' => $interactiveType,
                    'header_text' => $inputData['header_text'] ?? '',
                    'body_text' => $inputData['reply_text'],
                    'footer_text' => $inputData['footer_text'] ?? '',
                    'buttons' => array_filter($inputData['buttons'] ?? []),
                    'cta_url' => $ctaUrlButton,
                ]
            ];
        }
        // update process
        $engineResponse = $this->botReplyRepository->processTransaction(function () use (&$botReply, &$updateData, &$request) {
            if ($this->botReplyRepository->updateIt($botReply, $updateData)) {
                if($request->validate_bot_reply) {
                    $validateTestBotReply = $this->whatsAppServiceEngine->validateTestBotReply($botReply->_id);
                    if($validateTestBotReply->success()) {
                        return $this->botReplyRepository->transactionResponse(1, [], __tr('Bot Reply updated.'));
                    }
                    return $this->botReplyRepository->transactionResponse($validateTestBotReply->reaction(), [], $validateTestBotReply->message());
                }
                return $this->botReplyRepository->transactionResponse(1, [], __tr('Bot Reply updated.'));
            }
            return $this->botReplyRepository->transactionResponse(2, [], __tr('Bot Reply Not updated.'));
        });
        return $this->engineResponse($engineResponse);
    }
}
