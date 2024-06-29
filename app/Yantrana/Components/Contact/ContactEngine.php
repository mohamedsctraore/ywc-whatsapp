<?php
/**
* ContactEngine.php - Main component file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact;

use XLSXWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Yantrana\Base\BaseEngine;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use App\Yantrana\Components\User\Repositories\UserRepository;
use App\Yantrana\Support\Country\Repositories\CountryRepository;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\Contact\Interfaces\ContactEngineInterface;
use App\Yantrana\Components\Contact\Repositories\ContactGroupRepository;
use App\Yantrana\Components\Contact\Repositories\GroupContactRepository;
use App\Yantrana\Components\Contact\Repositories\ContactCustomFieldRepository;

class ContactEngine extends BaseEngine implements ContactEngineInterface
{
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
     * @var ContactCustomFieldRepository - ContactGroup Repository
     */
    protected $contactCustomFieldRepository;
    /**
     * @var UserRepository - User Repository
     */
    protected $userRepository;

    /**
     * Constructor
     *
     * @param  ContactRepository  $contactRepository  - Contact Repository
     * @param  ContactGroupRepository  $contactGroupRepository  - ContactGroup Repository
     * @param  GroupContactRepository  $groupContactRepository  - Group Contacts Repository
     * @param  ContactCustomFieldRepository  $contactCustomFieldRepository  - Contacts Custom  Fields Repository
     * @param  UserRepository  $userRepository  - User Fields Repository
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        ContactRepository $contactRepository,
        ContactGroupRepository $contactGroupRepository,
        GroupContactRepository $groupContactRepository,
        ContactCustomFieldRepository $contactCustomFieldRepository,
        UserRepository $userRepository,
    ) {
        $this->contactRepository = $contactRepository;
        $this->contactGroupRepository = $contactGroupRepository;
        $this->groupContactRepository = $groupContactRepository;
        $this->contactCustomFieldRepository = $contactCustomFieldRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Contact datatable source
     *
     * @return array
     *---------------------------------------------------------------- */
    public function prepareContactDataTableSource($contactGroupUid = null)
    {
        $groupContactIds = [];
        // if for specific group
        if($contactGroupUid) {
            $vendorId = getVendorId();
            $contactGroup = $this->contactGroupRepository->fetchIt([
                '_uid' => $contactGroupUid,
                'vendors__id' => $vendorId,
            ]);
            if(!__isEmpty($contactGroup)) {
                $groupContacts = $this->groupContactRepository->fetchItAll([
                    'contact_groups__id' => $contactGroup->_id
                ]);
                $groupContactIds = $groupContacts->pluck('contacts__id')->toArray();
            }
        }
        $contactCollection = $this->contactRepository->fetchContactDataTableSource($groupContactIds, $contactGroupUid);
        $listOfCountries = getCountryPhoneCodes();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'first_name',
            'last_name',
            'language_code',
            'country_name' => function ($rowData) use (&$listOfCountries) {
                return Arr::get($listOfCountries, $rowData['countries__id'] . '.name');
            },
            'phone_number' => function ($rowData) {
                return $rowData['wa_id'];
            },
            'email',
            'created_at' => function ($rowData) {
                return formatDateTime($rowData['created_at']);
            },
        ];

        // prepare data for the DataTables
        return $this->dataTableResponse($contactCollection, $requireColumns);
    }

    /**
     * Contact delete process
     *
     * @param  mix  $contactIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function processContactDelete($contactIdOrUid)
    {
        // fetch the record
        $contact = $this->contactRepository->fetchIt($contactIdOrUid);
        // check if the record found
        if (__isEmpty($contact)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Contact not found'));
        }

        if(getVendorSettings('test_recipient_contact') == $contact->_uid) {
            return $this->engineFailedResponse([], __tr('Record set as Test Contact for Campaign, Set another contact for test before deleting it.'));
        }

        // ask to delete the record
        if ($this->contactRepository->deleteIt($contact)) {
            // if successful
            return $this->engineSuccessResponse([], __tr('Contact deleted successfully'));
        }

        // if failed to delete
        return $this->engineFailedResponse([], __tr('Failed to delete Contact'));
    }
    /**
     * Contact delete process
     *
     * @param  BaseRequest  $request
     *
     * @return array
     *---------------------------------------------------------------- */
    public function processSelectedContactsDelete($request)
    {
        $selectedContactUids = $request->get('selected_contacts');
        $message = '';
        // check for test number
        if(in_array(getVendorSettings('test_recipient_contact'), $selectedContactUids)) {
            $message .= __tr(' However one of these contact is set as Test Contact, which can not be deleted.');
            if (($key = array_search(getVendorSettings('test_recipient_contact'), $selectedContactUids)) !== false) {
                unset($selectedContactUids[$key]);
            }
            if(empty($selectedContactUids)) {
                return $this->engineFailedResponse([], __tr('As selected is test contact it can not be deleted.'));
            }
        }
        if(empty($selectedContactUids)) {
            return $this->engineFailedResponse([], __tr('Nothing to delete'));
        }
        // ask to delete the record
        if ($this->contactRepository->deleteSelectedContacts($selectedContactUids)) {
            // if successful
            return $this->engineSuccessResponse([
                'reloadDatatableId' => '#lwContactList'
            ], __tr('Contacts deleted successfully.') . $message);
        }
        // if failed to delete
        return $this->engineFailedResponse([], __tr('Failed to delete Contacts'));
    }

    /**
     * Contact create
     *
     * @param  array  $inputData
     * @return EngineResponse
     *---------------------------------------------------------------- */
    public function processContactCreate($inputData)
    {
        $vendorId = getVendorId();
        // check the feature limit
        $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
            'vendors__id' => $vendorId
        ]), $vendorId);
        if (!$vendorPlanDetails['is_limit_available']) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        $customInputFields = isset($inputData['custom_input_fields']) ? $inputData['custom_input_fields'] : [];
        $customInputFieldUidsAndValues = [];
        // ask to add record
        if ($contactCreated = $this->contactRepository->storeContact($inputData)) {
            // if external api request
            if($contactCreated and isExternalApiRequest()) {
                return $this->engineSuccessResponse([
                    'contact' => $contactCreated
                ], __tr('Contact created'));
            }
            if(!empty($inputData['contact_groups'])) {
                // prepare group ids needs to be assign to the contact
                $groupsToBeAdded = $this->contactGroupRepository->fetchItAll($inputData['contact_groups'], [], '_id');
                $assignGroups = [];
                // prepare to assign if needed
                if (! empty($groupsToBeAdded)) {
                    foreach ($groupsToBeAdded as $groupToBeAdded) {
                        if($groupToBeAdded->vendors__id != $vendorId) {
                            continue;
                        }
                        $assignGroups[] = [
                            'contact_groups__id' => $groupToBeAdded->_id,
                            'contacts__id' => $contactCreated->_id,
                        ];
                    }
                    $this->groupContactRepository->storeItAll($assignGroups);
                }
            }
            // check if custom fields
            if (!empty($customInputFields)) {
                $customInputFieldsFromDb = $this->contactCustomFieldRepository->fetchItAll(array_keys(
                    $customInputFields
                ), [], '_uid')->keyBy('_uid');
                // loop though items
                foreach ($inputData['custom_input_fields'] as $customInputFieldKey => $customInputFieldValue) {
                    $customInputFieldFromDb = null;
                    if(isset($customInputFieldsFromDb[$customInputFieldKey])) {
                        $customInputFieldFromDb = $customInputFieldsFromDb[$customInputFieldKey];
                    }
                    // if invalid
                    if(!$customInputFieldFromDb or ($customInputFieldFromDb->vendors__id != $vendorId)) {
                        continue;
                    }
                    // if data verified
                    $customInputFieldUidsAndValues[] = [
                        'contact_custom_fields__id' => $customInputFieldFromDb->_id,
                        'contacts__id' => $contactCreated->_id,
                        'field_value' => $customInputFieldValue,
                    ];
                }
            }
            if(!empty($customInputFieldUidsAndValues)) {
                $this->contactCustomFieldRepository->storeCustomValues($customInputFieldUidsAndValues);
            }
            return $this->engineSuccessResponse([], __tr('Contact added.'));
        }
        return $this->engineFailedResponse([], __tr('Contact not added.'));
    }

    /**
     * Contact prepare update data
     *
     * @param  mix  $contactIdOrUid
     * @return EngineResponse
     *---------------------------------------------------------------- */
    public function prepareContactUpdateData($contactIdOrUid)
    {
        $contact = $this->contactRepository->with(['groups', 'customFieldValues', 'country'])->fetchIt($contactIdOrUid);
        // Check if $contact not exist then throw not found
        if (__isEmpty($contact)) {
            return $this->engineResponse(18, null, __tr('Contact not found.'));
        }
        $existingGroupIds = $contact->groups->pluck('_id')->toArray();
        $contactArray = $contact->toArray();
        return $this->engineSuccessResponse(array_merge($contactArray, [
            'existingGroupIds' => json_encode($existingGroupIds),
        ]));
    }

    /**
     * Process toggle ai bot for contact
     *
     * @param  mixed  $contactIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function processToggleAiBot($contactIdOrUid)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->with('groups')->fetchIt([
            '_uid' => $contactIdOrUid,
            'vendors__id' => $vendorId,
        ]);
        // Check if $contact not exist then throw not found
        // exception
        if (__isEmpty($contact)) {
            return $this->engineResponse(18, null, __tr('Contact not found.'));
        }
        $isAiBotDisabled = $contact->disable_ai_bot ? 0 : 1;
        if ($this->contactRepository->updateIt($contact, [
            'disable_ai_bot' => $isAiBotDisabled
        ])) {
            updateClientModels([
                'isAiChatBotEnabled' => !$isAiBotDisabled
            ]);
            if(!$isAiBotDisabled) {
                return $this->engineSuccessResponse([], __tr('AI bot enabled for this contact.'));
            }
            return $this->engineSuccessResponse([], __tr('AI bot disabled for this contact.'));
        }
        return $this->engineResponse(14, [], __tr('AI bot disabled for this contact.'));
    }
    /**
     * Contact process update
     *
     * @param  mixed  $contactIdOrUid
     * @param  array  $inputData
     * @return array
     *---------------------------------------------------------------- */
    public function processContactUpdate($contactIdOrUid, $inputData)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->with('groups')->fetchIt([
            '_uid' => $contactIdOrUid,
            'vendors__id' => $vendorId,
        ]);
        // Check if $contact not exist then throw not found
        // exception
        if (__isEmpty($contact)) {
            return $this->engineResponse(18, null, __tr('Contact not found.'));
        }

        $updateData = [
            'first_name' => $inputData['first_name'],
            'last_name' => $inputData['last_name'],
            'countries__id' => $inputData['country'],
            'language_code' => $inputData['language_code'],
            'email' => $inputData['email'],
            'disable_ai_bot' => (isset($inputData['enable_ai_bot']) and $inputData['enable_ai_bot']) ? 0 : 1,
        ];

        $customInputFields = isset($inputData['custom_input_fields']) ? $inputData['custom_input_fields'] : [];
        $customInputFieldUidsAndValues = [];

        // extract exiting group ids
        $existingGroupIds = $contact->groups->pluck('_id')->toArray();
        // prepare group ids needs to be assign to the contact
        $groupsToBeAddedIds = array_diff($inputData['contact_groups'] ?? [], $existingGroupIds);
        // prepare group ids needs to be remove from the contact
        $groupsToBeDeleted = array_diff($existingGroupIds, $inputData['contact_groups'] ?? []);
        $isUpdated = false;
        // process to delete if needed
        if (! empty($groupsToBeDeleted)) {
            if ($this->groupContactRepository->deleteItAll($groupsToBeDeleted, 'contact_groups__id')) {
                $isUpdated = true;
            }
        }
        // prepare to assign if needed
        if (! empty($groupsToBeAddedIds)) {
            // prepare group ids needs to be assign to the contact
            $groupsToBeAdded = $this->contactGroupRepository->fetchItAll($groupsToBeAddedIds, [], '_id');
            $assignGroups = [];
            foreach ($groupsToBeAdded as $groupToBeAdded) {
                if($groupToBeAdded->vendors__id != $vendorId) {
                    continue;
                }
                $assignGroups[] = [
                    'contact_groups__id' => $groupToBeAdded->_id,
                    'contacts__id' => $contact->_id,
                ];
            }
            if ($this->groupContactRepository->storeItAll($assignGroups)) {
                $isUpdated = true;
            }
        }
        // Check if Contact updated
        if ($this->contactRepository->updateIt($contact, $updateData)) {
            $isUpdated = true;
        }

        // check if custom fields
        if (!empty($customInputFields)) {
            $customInputFieldsFromDb = $this->contactCustomFieldRepository->fetchItAll(array_keys($customInputFields), [], '_uid')->keyBy('_uid');
            // loop though items
            foreach ($inputData['custom_input_fields'] as $customInputFieldKey => $customInputFieldValue) {
                $customInputFieldFromDb = null;
                if(isset($customInputFieldsFromDb[$customInputFieldKey])) {
                    $customInputFieldFromDb = $customInputFieldsFromDb[$customInputFieldKey];
                }
                // if invalid
                if(!$customInputFieldFromDb or ($customInputFieldFromDb->vendors__id != $vendorId)) {
                    continue;
                }
                // if data verified
                $customInputFieldUidsAndValues[] = [
                    'contact_custom_fields__id' => $customInputFieldFromDb->_id,
                    'contacts__id' => $contact->_id,
                    'field_value' => $customInputFieldValue,
                ];
            }
        }
        if(!empty($customInputFieldUidsAndValues)) {
            if($customFieldsUpdated = $this->contactCustomFieldRepository->storeCustomValues($customInputFieldUidsAndValues, 'contact_custom_fields__id', [
                'key' => 'contacts__id',
                'value' => $contact->_id,
            ])) {
                $isUpdated = true;
            }
        }

        if ($isUpdated) {
            return $this->engineSuccessResponse([], __tr('Contact details updated.'));
        }

        return $this->engineResponse(14, null, __tr('Nothing to update contact information.'));
    }

    /**
     * Prepare Contact Required data
     *
     * @param string|null $groupUid
     * @return EnginResponse
     */
    public function prepareContactRequiredData($groupUid = null)
    {
        $vendorId = getVendorId();

        if($groupUid) {
            $group = $this->contactGroupRepository->fetchIt([
                '_uid' => $groupUid,
                'vendors__id' => $vendorId,
            ]);
            abortIf(__isEmpty($group));
        }

        $vendorContactCustomFields = $this->contactCustomFieldRepository->fetchItAll([
            'vendors__id' => $vendorId,
        ]);
        // contact groups
        $vendorContactGroups = $this->contactGroupRepository->fetchItAll([
            'vendors__id' => $vendorId,
        ]);

        return $this->engineSuccessResponse([
            'groupUid' => $groupUid,
            'vendorContactGroups' => $vendorContactGroups,
            'vendorContactCustomFields' => $vendorContactCustomFields,
        ]);
    }

    /**
     * Export Template with or without Data
     *
     * @param string $exportType
     * @return Download File
     */
    public function processExportContacts($exportType = 'blank')
    {
        $header = [];
        $vendorId = getVendorId();
        $header = array_merge($header, [
            'first_name' => 'string',
            'last_name' => 'string',
            'phone_number' => 'string',
            'language_code' => 'string',
            'country' => 'string',
            'email' => 'string',
            'groups' => 'string',
        ]);
        // required data like fields and groups
        $contactsRequiredData = $this->prepareContactRequiredData();
        // get vendor custom fields
        $vendorContactCustomFields = $contactsRequiredData->data('vendorContactCustomFields');
        // create header array
        foreach ($vendorContactCustomFields as $vendorContactCustomField) {
            $header[$vendorContactCustomField->input_name] = 'string';
        }
        $data = [];
        if($exportType == 'data') {
            if(isDemo() and isDemoVendorAccount()) {
                abort(403, __tr('Exporting Contacts data has been disabled for demo'));
            }
            // country repository
            $countryRepository = new CountryRepository();
            $countries = $countryRepository->fetchItAll([], ['_id','name'])->keyBy('_id')->toArray();
            // contacts
            $contacts = $this->contactRepository->with(['groups', 'customFieldValues'])->fetchItAll([
                'vendors__id' => $vendorId
            ]);
            // go though each contact and prepare item for export
            foreach ($contacts as $contact) {
                $dataItem = [
                    $contact->first_name,
                    $contact->last_name,
                    // phone number
                    $contact->wa_id,
                    $contact->language_code,
                    $countries[$contact->countries__id]['name'] ?? null,
                    $contact->email,
                ];
                // group
                if($contact->groups) {
                    $groupItems = [];
                    foreach ($contact->groups as $group) {
                        $groupItems[] = $group->title;
                    }
                    $dataItem[] = implode(',', $groupItems);
                }
                // custom fields
                if($contact->customFieldValues) {
                    foreach ($contact->customFieldValues as $customFieldValue) {
                        $dataItem[] = $customFieldValue->field_value;
                    }
                }
                $data[] = $dataItem;
            }
        }
        //create temp path for store excel file
        $tempFile = tempnam(sys_get_temp_dir(), "exported_contacts_{$vendorId}.xlsx");
        $writer = new XLSXWriter();
        $writer->writeSheetHeader('Contacts', $header);
        foreach($data as $row) {
            // create row
            $writer->writeSheetRow('Contacts', $row);
        }
        // write to file
        $writer->writeToFile($tempFile);
        // file name
        $dateTime = str_slug(now()->format('Y-m-d-H-i-s'));
        // get back with response
        return response()->download($tempFile, "contacts-{$exportType}-{$dateTime}.xlsx", [
            'Content-Transfer-Encoding: binary',
            'Content-Type: application/octet-stream',
        ])->deleteFileAfterSend();
    }

    /**
     * Import contacts using Excel sheet
     *
     * @param BaseRequest $request
     * @return EngineResponse
     */
    public function processImportContacts($request)
    {
        $vendorId = getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }
        $filePath = getTempUploadedFile($request->get('document_name'));
        $countryRepository = new CountryRepository();
        $countries = $countryRepository->fetchItAll([], ['_id','name'])->keyBy('name')->toArray();
        $contactsRequiredData = $this->prepareContactRequiredData();
        $vendorContactGroups = $contactsRequiredData->data('vendorContactGroups')?->keyBy('title')?->toArray() ?: [];
        $vendorContactCustomFields = $contactsRequiredData->data('vendorContactCustomFields')?->keyBy('input_name')?->toArray() ?: [];
        $duplicateEntries = [];
        $reader = ReaderEntityFactory::createReaderFromFile($filePath);
        $reader->open($filePath);
        $data = [];

        $dataStructure = [
            'first_name',
            'last_name',
            'wa_id',
            'language_code',
            'countries__id',
            'email',
        ];
        $customFieldStructure = [];
        $contactsToUpdate = [];
        $customFieldsToUpdate = [];
        $contactGroupsToUpdate = [];
        // get the contacts from db
        $contacts = $this->contactRepository->with(['groups', 'customFieldValues'])
            ->fetchItAll([
                'vendors__id' => $vendorId
            ], [
                '_id',
                '_uid',
                'wa_id'
                ])?->keyBy('_uid')?->toArray() ?: [];
        $phoneNumbers = [];
        $ignoreRow = false;
        $newContactsCount = 0;
        try {
            // loop through the sheets
            foreach ($reader->getSheetIterator() as $sheet) {
                // loop though each row
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    if($ignoreRow) {
                        $ignoreRow = false;
                    }
                    // do stuff with the row
                    $cells = $row->getCells();
                    $contact = null;
                    $contactId = null;
                    if($rowIndex != 1) {
                        $contactsToUpdate[$rowIndex]['vendors__id'] = $vendorId;
                    }
                    // loop through each cell of row
                    foreach ($cells as $cellIndex => $cell) {
                        if($ignoreRow) {
                            continue;
                        }
                        $cellValue = e($cell->getValue());
                        // if its not header row and upto 5 cells its contact basic fields
                        if(($rowIndex != 1) and ($cellIndex <= 5)) {
                            if($dataStructure[$cellIndex] == 'wa_id') {
                                if(!$cellValue) {
                                    return $this->engineFailedResponse([], __tr('Missing phone number'));
                                }
                                // check if mobile number is valid
                                if(!is_numeric($cellValue) or str_starts_with($cellValue, '0') or str_starts_with($cellValue, '+')) {
                                    return $this->engineFailedResponse([], __tr('phone number should be numeric value without prefixing 0 or +'));
                                }
                                // check if number is already processed then skip and continue
                                if(in_array($cellValue, $phoneNumbers)) {
                                    $ignoreRow = true;
                                    $duplicateEntries[] = $cellValue;
                                    unset($contactsToUpdate[$rowIndex]);
                                    continue;
                                }
                                $contact = Arr::first($contacts, function ($value, $key) use (&$cellValue) {
                                    return $value['wa_id'] == $cellValue;
                                });
                                $contactsToUpdate[$rowIndex]['_uid'] = Arr::get($contact, '_uid') ?: (string) Str::uuid();
                                if(!__isEmpty($contact)) {
                                    $contactId = Arr::get($contact, '_id');
                                } else {
                                    $newContactsCount++;
                                    $contactsToUpdate[$rowIndex][$dataStructure[$cellIndex]] = $cellValue;
                                    $phoneNumbers[] = $cellValue;
                                }
                            }
                            // if its _uid column
                            elseif ($dataStructure[$cellIndex] == '_uid' and !__isEmpty($contacts) and __isEmpty($contact)) {
                                $contact = Arr::get($contacts, $cellValue);
                                $contactId = Arr::get($contacts, $cellValue . '._id');
                            }
                            // if its country column
                            elseif ($dataStructure[$cellIndex] == 'countries__id') {
                                $getCountry = Arr::first($countries, function ($value, $key) use (&$cellValue) {
                                    return strtolower($value['name']) == strtolower($cellValue);
                                });
                                $contactsToUpdate[$rowIndex][$dataStructure[$cellIndex]] = Arr::get($getCountry, '_id');
                            } else {
                                $contactsToUpdate[$rowIndex][$dataStructure[$cellIndex]] = $cellValue;
                            }
                        }
                    }
                }
                // check the feature limit
                $vendorPlanDetails = vendorPlanDetails('contacts', (count($contacts) + $newContactsCount), $vendorId);
                if (!$vendorPlanDetails['is_limit_available']) {
                    return $this->engineResponse(22, null, $vendorPlanDetails['message']);
                }

                $totalContactsProcessed = count($contactsToUpdate);
                // update contacts
                if(!empty($contactsToUpdate)) {
                    $this->contactRepository->bunchInsertOrUpdate($contactsToUpdate, '_uid');
                }
                // re grab contacts
                $contacts = $this->contactRepository->with(['groups', 'customFieldValues'])
                ->fetchItAll([
                    'vendors__id' => $vendorId
                ], [
                    '_id',
                    '_uid',
                    'wa_id'
                    ])?->keyBy('_uid')?->toArray() ?: [];
                // next procedure to update related to models
                // loop though each row
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    // do stuff with the row
                    $cells = $row->getCells();
                    if($rowIndex != 1) {
                        $contactsToUpdate[$rowIndex]['vendors__id'] = $vendorId;
                    }
                    // loop through each cell of row
                    foreach ($cells as $cellIndex => $cell) {
                        $cellValue = $cell->getValue();
                        // if its not header row and upto 4 cells its contact basic fields
                        if(($rowIndex != 1) and ($cellIndex <= 5)) {
                            if($dataStructure[$cellIndex] == 'wa_id') {
                                // extract contact from array
                                $contact = Arr::first($contacts, function ($value, $key) use (&$cellValue) {
                                    return $value['wa_id'] == $cellValue;
                                });
                                // check if contact found
                                if(!__isEmpty($contact)) {
                                    $contactId = Arr::get($contact, '_id');
                                } else {
                                    // collect wa_id which is the phone numbers
                                    $phoneNumbers[] = $cellValue;
                                }
                            }
                            // if its _uid column
                            elseif ($dataStructure[$cellIndex] == '_uid' and !__isEmpty($contacts) and __isEmpty($contact)) { // contact and id
                                $contact = Arr::get($contacts, $cellValue);
                                $contactId = Arr::get($contacts, $cellValue . '._id');
                            }
                        } elseif (($cellIndex == 6) and ($rowIndex != 1)) { // groups
                            // get the group names and explode it from comma separated names
                            $extractedGroups = trim($cellValue) ? explode(',', $cellValue) : [];
                            $contactGroups = collect($contact['groups'] ?? [])->keyBy('_id');
                            // loop through the groups
                            foreach ($extractedGroups as $extractedGroup) {
                                $extractedGroup = trim($extractedGroup);
                                // get group id
                                $contactGroupId = Arr::get($vendorContactGroups, $extractedGroup . '._id');
                                if($contactId and $contactGroupId and !isset($contactGroups[$contactGroupId])) {
                                    // set it for update
                                    $contactGroupsToUpdate[] = [
                                        'contact_groups__id' => $contactGroupId,
                                        'contacts__id' => $contactId,
                                    ];
                                }
                            }
                        } elseif($cellIndex >= 7) { // custom field
                            // custom field values
                            if($rowIndex == 1) {
                                $customFieldStructure[$cellIndex] = $cellValue;
                            } else {
                                // get custom item field data based on column head
                                $customFieldItem = $vendorContactCustomFields[$customFieldStructure[ $cellIndex ?? null ]] ?? null;
                                if($customFieldItem and $contactId) {
                                    // extract the item from contact db custom field value
                                    $customFieldDbItem = Arr::first($contact['custom_field_values'] ?? [], function ($value, $key) use ($customFieldItem) {
                                        return $value['contact_custom_fields__id'] == Arr::get($customFieldItem, '_id');
                                    });
                                    $customFieldsToUpdate[] = [
                                        // get or set uuid
                                        '_uid' => Arr::get($customFieldDbItem, '_uid') ?: (string) Str::uuid(),
                                        'contact_custom_fields__id' => Arr::get($customFieldItem, '_id'),
                                        'contacts__id' => $contactId,
                                        'field_value' => $cellValue,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            // close the sheet
            $reader->close();
            // create or custom field values
            if(!empty($customFieldsToUpdate)) {
                $this->contactCustomFieldRepository->storeCustomValues($customFieldsToUpdate, '_uid');
            }
            // groups update
            if(!empty($contactGroupsToUpdate)) {
                $this->groupContactRepository->bunchInsertOrUpdate($contactGroupsToUpdate, '_uid');
            }
            if(!empty($duplicateEntries)) {
                return $this->engineSuccessResponse([], __tr('Total __totalContactsProcessed__ contact import processed, __duplicateEntries__ phone numbers found duplicate.', [
                    '__totalContactsProcessed__' => $totalContactsProcessed,
                    '__duplicateEntries__' => count($duplicateEntries),
                ]));
            }
            return $this->engineSuccessResponse([], __tr('Total __totalContactsProcessed__ contact import processed', [
                '__totalContactsProcessed__' => $totalContactsProcessed
            ]));
        } catch (\Throwable $th) {
            if(config('app.debug')) {
                throw $th;
            }
            return $this->engineFailedResponse([], __tr('Error occurred while importing data, please check and correct data and re-upload.'));
        }
    }

    /**
     * Assign User to Contact for chat
     *
     * @param BaseRequest $request
     * @return EngineResponse
     */
    public function processAssignChatUser($request)
    {
        $vendorId = getVendorId();
        if($request->assigned_users_uid == 'no_one') {
            if($this->contactRepository->updateIt([
                '_uid' => $request->contactIdOrUid,
                'vendors__id' => $vendorId,
            ], [
                'assigned_users__id' => null,
            ])) {
                return $this->engineSuccessResponse([], __tr('Unassigned user'));
            }
            return $this->engineFailedResponse([], __tr('Failed to unassign user'));
        }
        // get all the messaging vendor users
        $vendorMessagingUserUids = $this->userRepository->getVendorMessagingUsers($vendorId)->pluck('_uid')->toArray();
        // validate the vendor user
        if(!in_array($request->assigned_users_uid, $vendorMessagingUserUids)) {
            return $this->engineFailedResponse([], __tr('Invalid user'));
        }
        // get the user details
        $user = $this->userRepository->fetchIt([
            '_uid' => $request->assigned_users_uid,
        ]);
        if(__isEmpty($user)) {
            return $this->engineFailedResponse([], __tr('Failed to assign user'));
        }
        if($this->contactRepository->updateIt([
            '_uid' => $request->contactIdOrUid,
            'vendors__id' => $vendorId,
        ], [
            'assigned_users__id' => $user->_id,
        ])) {
            return $this->engineSuccessResponse([], __tr('__userFullName__ Assigned', [
                '__userFullName__' => $user->full_name
            ]));
        }
        return $this->engineResponse(14, [], __tr('No changes'));
    }

    /**
     * Assign Groups to selected contacts
     *
     * @param BaseRequest $request
     * @return void
     */
    function processAssignGroupsToSelectedContacts($request) {
        $groups = $this->contactGroupRepository->fetchItAll($request->get('selected_groups'), [], '_id');
        $contacts = $this->contactRepository->with(['groups'])->fetchItAll($request->get('selected_contacts'), [], '_uid');
        $contactGroupsToUpdate = [];
        foreach ($contacts as $contact) {
            $contactGroups = collect($contact['groups'] ?? [])->pluck('_id');
            $newGroupIds = array_diff($groups->pluck('_id')->toArray(), $contactGroups->toArray());
            foreach ($newGroupIds as $newGroupId) {
                $contactGroupsToUpdate[] = [
                    'contact_groups__id' => $newGroupId,
                    'contacts__id' => $contact->_id,
                ];
            }
        }
        if(!empty($contactGroupsToUpdate)) {
            $this->groupContactRepository->bunchInsertOrUpdate($contactGroupsToUpdate, '_uid');
            return $this->engineSuccessResponse([
                'reloadDatatableId' => '#lwContactList',
                'modalId' => '#lwAssignGroups',
            ], __tr('Groups assigned successfully.'));
        }
        return $this->engineResponse(14, [], __tr('No changes'));
    }
}
