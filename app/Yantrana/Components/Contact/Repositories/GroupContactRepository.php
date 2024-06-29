<?php
/**
* GroupContactRepository.php - Repository file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact\Repositories;

use App\Yantrana\Base\BaseRepository;
use App\Yantrana\Components\Contact\Interfaces\GroupContactRepositoryInterface;
use App\Yantrana\Components\Contact\Models\GroupContactModel;

class GroupContactRepository extends BaseRepository implements GroupContactRepositoryInterface
{
    /**
     * primary model instance
     *
     * @var object
     */
    protected $primaryModel = GroupContactModel::class;
}
