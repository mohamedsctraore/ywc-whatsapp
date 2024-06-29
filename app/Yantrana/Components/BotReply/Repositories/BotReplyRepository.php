<?php
/**
* BotReplyRepository.php - Repository file
*
* This file is part of the BotReply component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\BotReply\Repositories;

use App\Yantrana\Base\BaseRepository;
use App\Yantrana\Components\BotReply\Models\BotReplyModel;
use App\Yantrana\Components\BotReply\Interfaces\BotReplyRepositoryInterface;

class BotReplyRepository extends BaseRepository implements BotReplyRepositoryInterface
{
    /**
     * primary model instance
     *
     * @var  object
     */
    protected $primaryModel = BotReplyModel::class;


    /**
      * Fetch botReply datatable source
      *
      * @return  mixed
      *---------------------------------------------------------------- */
    public function fetchBotReplyDataTableSource()
    {
        // basic configurations for dataTables data
        $dataTableConfig = [
            // searchable columns
            'searchable' => [
                'name',
                'reply_text',
                'trigger_type',
                'reply_trigger'
            ]
        ];
        // get Model result for dataTables
        return BotReplyModel::where([
            'vendors__id' => getVendorId()
        ])->dataTables($dataTableConfig)->toArray();
    }

    /**
      * Delete $botReply record and return response
      *
      * @param  object $inputData
      *
      * @return  mixed
      *---------------------------------------------------------------- */

    public function deleteBotReply($botReply)
    {
        // Check if $botReply deleted
        if ($botReply->deleteIt()) {
            // if deleted
            return true;
        }
        // if failed to delete
        return false;
    }

    /**
      * Store new botReply record and return response
      *
      * @param  array $inputData
      *
      * @return  mixed
      *---------------------------------------------------------------- */

    public function storeBotReply($inputData)
    {
        // prepare data to store
        $keyValues = [
            'name',
            'reply_text',
            'trigger_type',
            'reply_trigger',
            'vendors__id',
            '__data',
        ];
        return $this->storeIt($inputData, $keyValues);
    }


}
