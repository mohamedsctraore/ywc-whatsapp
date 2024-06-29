<?php
/**
* BotReply.php - Model file
*
* This file is part of the BotReply component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\BotReply\Models;

use App\Yantrana\Base\BaseModel;

class BotReplyModel extends BaseModel
{
    /**
     * @var  string $table - The database table used by the model.
     */
    protected $table = "bot_replies";

    /**
     * @var  array $casts - The attributes that should be casted to native types.
     */
    protected $casts = [
        '__data' => 'array',
    ];

    /**
     * @var  array $fillable - The attributes that are mass assignable.
     */
    protected $fillable = [
    ];

    /**
     * Let the system knows Text columns treated as JSON
     *
     * @var array
     *----------------------------------------------------------------------- */
    protected $jsonColumns = [
        '__data' => [
            // stores the interactive message data
            'interaction_message' => 'array:extend',
            // store the media message data
            'media_message' => 'array:extend',
        ],
    ];
}