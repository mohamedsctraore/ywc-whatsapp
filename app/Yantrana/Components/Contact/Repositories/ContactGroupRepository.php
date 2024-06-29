<?php
/**
* ContactGroupRepository.php - Repository file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact\Repositories;

use App\Yantrana\Base\BaseRepository;
use App\Yantrana\Components\Contact\Interfaces\ContactGroupRepositoryInterface;
use App\Yantrana\Components\Contact\Models\ContactGroupModel;

class ContactGroupRepository extends BaseRepository implements ContactGroupRepositoryInterface
{
    /**
     * primary model instance
     *
     * @var object
     */
    protected $primaryModel = ContactGroupModel::class;

    /**
     * Fetch group datatable source
     *
     * @return mixed
     *---------------------------------------------------------------- */
    public function fetchGroupDataTableSource()
    {
        // basic configurations for dataTables data
        $dataTableConfig = [
            // searchable columns
            'searchable' => [
                'title',
                'description',

            ],
        ];

        // get Model result for dataTables
        return ContactGroupModel::where([
            'vendors__id' => getVendorId()
        ])->dataTables($dataTableConfig)->toArray();
    }

    /**
     * Delete $group record and return response
     *
     * @param  object  $inputData
     * @return mixed
     *---------------------------------------------------------------- */
    public function deleteGroup($group)
    {
        // Check if $group deleted
        if ($group->deleteIt()) {
            // if deleted
            return true;
        }

        // if failed to delete
        return false;
    }

    /**
     * Store new group record and return response
     *
     * @param  array  $inputData
     * @return mixed
     *---------------------------------------------------------------- */
    public function storeGroup($inputData)
    {
        // prepare data to store
        $keyValues = [
            'title',
            'description',
            'vendors__id' => getVendorId(),
        ];

        return $this->storeIt($inputData, $keyValues);
    }
}
