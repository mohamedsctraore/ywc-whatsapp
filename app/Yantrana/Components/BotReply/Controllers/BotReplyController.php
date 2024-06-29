<?php
/**
* BotReplyController.php - Controller file
*
* This file is part of the BotReply component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\BotReply\Controllers;

use Illuminate\Validation\Rule;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Base\BaseController;

use Illuminate\Database\Query\Builder;
use App\Yantrana\Support\CommonPostRequest;
use App\Yantrana\Components\BotReply\BotReplyEngine;

class BotReplyController extends BaseController
{
    /**
     * @var  BotReplyEngine $botReplyEngine - BotReply Engine
     */
    protected $botReplyEngine;

    /**
      * Constructor
      *
      * @param  BotReplyEngine $botReplyEngine - BotReply Engine
      *
      * @return  void
      *-----------------------------------------------------------------------*/
    public function __construct(BotReplyEngine $botReplyEngine)
    {
        $this->botReplyEngine = $botReplyEngine;
    }


    /**
      * list of BotReply
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function showBotReplyView()
    {
        validateVendorAccess('manage_bot_replies');
        // load the view
        return $this->loadView('bot-reply.list', [
            'dynamicFields' => $this->botReplyEngine->preDataForBots()->data('dynamicFields')
        ]);
    }
    /**
      * list of BotReply
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function prepareBotReplyList()
    {
        validateVendorAccess('manage_bot_replies');
        // respond with dataTables preparations
        return $this->botReplyEngine->prepareBotReplyDataTableSource();
    }

    /**
        * BotReply process delete
        *
        * @param  mix $botReplyIdOrUid
        *
        * @return  json object
        *---------------------------------------------------------------- */

    public function processBotReplyDelete($botReplyIdOrUid, BaseRequest $request)
    {
        validateVendorAccess('manage_bot_replies');
        // ask engine to process the request
        $processReaction = $this->botReplyEngine->processBotReplyDelete($botReplyIdOrUid);
        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
      * BotReply create process
      *
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function processBotReplyCreate(BaseRequest $request)
    {
        validateVendorAccess('manage_bot_replies');
        $triggerTypeValidations = [];
        if($request->trigger_type != 'welcome') {
            $triggerTypeValidations = [
                "required",
                "max:250",
            ];
        }
        $validations = [
            "name" => [
                "required",
                Rule::unique('bot_replies')->where(fn (Builder $query) => $query->where('vendors__id', getVendorId()))
            ],
            "trigger_type" => "required",
            "reply_trigger" => $triggerTypeValidations,
        ];
        if(in_array($request->message_type, [
            'simple',
            'interactive',
        ])) {
            $validations['reply_text'] = "required";
        }
        if(in_array($request->message_type, [
            'media',
        ])) {
            $validations['media_header_type'] = [
                "required",
                Rule::in([
                    'image',
                    'video',
                    'audio',
                    'document',
                ])
            ];
            $validations['uploaded_media_file_name'] = "required";
        }
        // interaction message thats button etc
        if($request->message_type == 'interactive') {
            $validations['header_type'] = [
                Rule::in([
                    '',
                    'text',
                    'image',
                    'video',
                    'document',
                ])
            ];
            $validations['interactive_type'] = [
                Rule::in([
                    'button',
                    'cta_url',
                ])
            ];
            if($request->interactive_type == 'cta_url') {
                $validations['button_display_text'] = "required|min:1|max:20";
                $validations['button_url'] = "required|url";
            } else {
                // must be reply button type
                // at least 1 button is required
                $validations['buttons.1'] = "required|min:1|max:20";
                $validations['buttons.2'] = "nullable|min:1|max:20";
                $validations['buttons.3'] = "nullable|min:1|max:20";
            }

            // if header is not a text then it should be media
            if(($request->header_type != 'text') and ($request->message_type != 'interactive')) {
                $validations['uploaded_media_file_name'] = "required";
            } elseif($request->message_type != 'interactive') {
                // if header text then its required
                $validations['header_text'] = "required";
            }
        }
        // process the validation based on the provided rules
        $request->validate($validations, [
            'uploaded_media_file_name' => __tr('Media is required')
        ]);
        // ask engine to process the request
        $processReaction = $this->botReplyEngine->processBotReplyCreate($request->all());
        // get back with response
        return $this->processResponse($processReaction);
    }

    /**
      * BotReply get update data
      *
      * @param  mix $botReplyIdOrUid
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function updateBotReplyData($botReplyIdOrUid)
    {
        validateVendorAccess('manage_bot_replies');
        // ask engine to process the request
        $processReaction = $this->botReplyEngine->prepareBotReplyUpdateData($botReplyIdOrUid);
        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
      * BotReply process update
      *
      * @param  mix @param  mix $botReplyIdOrUid
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function processBotReplyUpdate(BaseRequest $request)
    {
        validateVendorAccess('manage_bot_replies');
        $validations = [
            'botReplyIdOrUid' => 'required',
            "name" => "required",
            // "reply_text" => "required",
        ];

        if(in_array($request->message_type, [
            'simple',
            'interactive',
        ])) {
            $validations['reply_text'] = "required";
        }
        if(in_array($request->message_type, [
            'media',
        ])) {
            $validations['media_header_type'] = [
                "required",
                Rule::in([
                    'image',
                    'video',
                    'audio',
                    'document',
                ])
            ];
        }

        if($request->message_type == 'interactive') {

            if($request->interactive_type == 'cta_url') {
                $validations['button_display_text'] = "required|min:1|max:20";
                $validations['button_url'] = "required|url";
            } else {
                // must be reply button type
                // at least 1 button is required
                $validations['buttons.1'] = "required|min:1|max:20";
                $validations['buttons.2'] = "nullable|min:1|max:20";
                $validations['buttons.3'] = "nullable|min:1|max:20";
            }
            // if header is not a text then it should be media
            if($request->header_type == 'text') {
                // if header text then its required
                $validations['header_text'] = "required";
            }
        }
        // process the validation based on the provided rules
        $request->validate($validations);
        // ask engine to process the request
        $processReaction = $this->botReplyEngine->processBotReplyUpdate($request->get('botReplyIdOrUid'), $request);
        // get back with response
        return $this->processResponse($processReaction, [], [], true);
    }
}
