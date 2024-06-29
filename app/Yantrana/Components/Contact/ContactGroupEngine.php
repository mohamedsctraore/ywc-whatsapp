<?php
/**
* ContactGroupEngine.php - Main component file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\Contact\Interfaces\ContactGroupEngineInterface;
use App\Yantrana\Components\Contact\Repositories\ContactGroupRepository;

class ContactGroupEngine extends BaseEngine implements ContactGroupEngineInterface
{
    /**
     * @var ContactGroupRepository - ContactGroup Repository
     */
    protected $contactGroupRepository;

    /**
     * Constructor
     *
     * @param  ContactGroupRepository  $contactGroupRepository  - ContactGroup Repository
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(ContactGroupRepository $contactGroupRepository)
    {
        $this->contactGroupRepository = $contactGroupRepository;
    }

    /**
     * Group datatable source
     *
     * @return array
     *---------------------------------------------------------------- */
    public function prepareGroupDataTableSource()
    {
        $groupCollection = $this->contactGroupRepository->fetchGroupDataTableSource();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'title',
            'description',
        ];

        // prepare data for the DataTables
        return $this->dataTableResponse($groupCollection, $requireColumns);
    }

    /**
     * Group delete process
     *
     * @param  mix  $contactGroupIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function processGroupDelete($contactGroupIdOrUid)
    {
        // fetch the record
        $group = $this->contactGroupRepository->fetchIt($contactGroupIdOrUid);
        // check if the record found
        if (__isEmpty($group)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Group not found'));
        }
        // ask to delete the record
        if ($this->contactGroupRepository->deleteIt($group)) {
            // if successful
            return $this->engineSuccessResponse([], __tr('Group deleted successfully'));
        }

        // if failed to delete
        return $this->engineFailedResponse([], __tr('Failed to delete Group'));
    }

    /**
     * Group create
     *
     * @param  array  $inputData
     * @return array
     *---------------------------------------------------------------- */
    public function processGroupCreate($inputData)
    {
        // ask to add record
        if ($this->contactGroupRepository
            ->storeGroup($inputData)) {

            return $this->engineSuccessResponse([], __tr('Group added.'));
        }

        return $this->engineFailedResponse([], __tr('Group not added.'));
    }

    /**
     * Group prepare update data
     *
     * @param  mix  $contactGroupIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function prepareGroupUpdateData($contactGroupIdOrUid)
    {
        $group = $this->contactGroupRepository->fetchIt($contactGroupIdOrUid);

        // Check if $group not exist then throw not found
        // exception
        if (__isEmpty($group)) {
            return $this->engineResponse(18, null, __tr('Group not found.'));
        }

        return $this->engineSuccessResponse($group->toArray());
    }

    /**
     * Group process update
     *
     * @param  mixed  $contactGroupIdOrUid
     * @param  array  $inputData
     * @return array
     *---------------------------------------------------------------- */
    public function processGroupUpdate($contactGroupIdOrUid, $inputData)
    {
        $group = $this->contactGroupRepository->fetchIt($contactGroupIdOrUid);

        // Check if $group not exist then throw not found
        // exception
        if (__isEmpty($group)) {
            return $this->engineResponse(18, null, __tr('Group not found.'));
        }

        $updateData = [
            'title' => $inputData['title'],
            'description' => $inputData['description'],

        ];

        // Check if Group updated
        if ($this->contactGroupRepository->updateIt($group, $updateData)) {

            return $this->engineSuccessResponse([], __tr('Group updated.'));
        }

        return $this->engineResponse(14, null, __tr('Group not updated.'));
    }
}
