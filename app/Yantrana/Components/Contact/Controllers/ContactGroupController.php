<?php
/**
* ContactGroupController.php - Controller file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact\Controllers;

use App\Yantrana\Base\BaseController;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Components\Contact\ContactGroupEngine;

class ContactGroupController extends BaseController
{
    /**
     * @var ContactGroupEngine - ContactGroup Engine
     */
    protected $contactGroupEngine;

    /**
     * Constructor
     *
     * @param  ContactGroupEngine  $contactGroupEngine  - ContactGroup Engine
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(ContactGroupEngine $contactGroupEngine)
    {
        $this->contactGroupEngine = $contactGroupEngine;
    }

    /**
     * list of Group
     *
     * @return json object
     *---------------------------------------------------------------- */
    public function showGroupView()
    {
        validateVendorAccess('manage_contacts');
        // load the view
        return $this->loadView('contact.contact-group.group-list');
    }

    /**
     * list of Group
     *
     * @return json object
     *---------------------------------------------------------------- */
    public function prepareGroupList()
    {
        validateVendorAccess('manage_contacts');
        // respond with dataTables preparations
        return $this->contactGroupEngine->prepareGroupDataTableSource();
    }

    /**
     * Group process delete
     *
     * @param  mix  $contactGroupIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function processGroupDelete($contactGroupIdOrUid, BaseRequest $request)
    {
        validateVendorAccess('manage_contacts');
        // ask engine to process the request
        $processReaction = $this->contactGroupEngine->processGroupDelete($contactGroupIdOrUid);

        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
     * Group create process
     *
     * @param  object BaseRequest $request
     * @return json object
     *---------------------------------------------------------------- */
    public function processGroupCreate(BaseRequest $request)
    {
        validateVendorAccess('manage_contacts');
        // process the validation based on the provided rules
        $request->validate([
            'title' => 'required',
        ]);
        // ask engine to process the request
        $processReaction = $this->contactGroupEngine->processGroupCreate($request->all());

        // get back with response
        return $this->processResponse($processReaction);
    }

    /**
     * Group get update data
     *
     * @param  mix  $contactGroupIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function updateGroupData($contactGroupIdOrUid)
    {
        validateVendorAccess('manage_contacts');
        // ask engine to process the request
        $processReaction = $this->contactGroupEngine->prepareGroupUpdateData($contactGroupIdOrUid);

        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
     * Group process update
     *
     * @param  mix @param  mix $contactGroupIdOrUid
     * @param  object BaseRequest $request
     * @return json object
     *---------------------------------------------------------------- */
    public function processGroupUpdate(BaseRequest $request)
    {
        validateVendorAccess('manage_contacts');
        // process the validation based on the provided rules
        $request->validate([
            'contactGroupIdOrUid' => 'required',
            'title' => 'required',
        ]);
        // ask engine to process the request
        $processReaction = $this->contactGroupEngine->processGroupUpdate($request->get('contactGroupIdOrUid'), $request->all());

        // get back with response
        return $this->processResponse($processReaction, [], [], true);
    }
}
