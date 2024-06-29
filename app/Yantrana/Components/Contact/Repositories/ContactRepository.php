<?php
/**
* ContactRepository.php - Repository file
*
* This file is part of the Contact component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Contact\Repositories;

use App\Yantrana\Base\BaseRepository;
use App\Yantrana\Components\Contact\Interfaces\ContactRepositoryInterface;
use App\Yantrana\Components\Contact\Models\ContactModel;
use App\Yantrana\Support\Country\Models\Country;

class ContactRepository extends BaseRepository implements ContactRepositoryInterface
{
    /**
     * primary model instance
     *
     * @var object
     */
    protected $primaryModel = ContactModel::class;

    /**
     * Fetch contact datatable source
     *
     * @return mixed
     *---------------------------------------------------------------- */
    public function fetchContactDataTableSource($groupContactIds = null, $contactGroupUid = null)
    {
        // basic configurations for dataTables data
        $dataTableConfig = [
            // searchable columns
            'searchable' => [
                'first_name',
                'last_name',
                'countries__id',
                'wa_id',
                'email',
            ],
        ];

        // get Model result for dataTables
        $query = $this->primaryModel::where([
            'vendors__id' => getVendorId()
        ]);
        if ($contactGroupUid) {
            $query->whereIn('_id', $groupContactIds);
        }
        return $query->dataTables($dataTableConfig)->toArray();
    }

    /**
     * Delete $contact record and return response
     *
     * @param  object  $inputData
     * @return mixed
     *---------------------------------------------------------------- */
    public function deleteContact($contact)
    {
        // Check if $contact deleted
        if ($contact->deleteIt()) {
            // if deleted
            return true;
        }

        // if failed to delete
        return false;
    }

    /**
     * Store new contact record and return response
     *
     * @param  array  $inputData
     * @return mixed
     *---------------------------------------------------------------- */
    public function storeContact($inputData, $vendorId = null)
    {
        // prepare data to store
        $keyValues = [
            'first_name',
            'last_name',
            'countries__id' => $inputData['country'] ?? null,
            'email',
            'language_code',
            'disable_ai_bot' => (isset($inputData['enable_ai_bot']) and $inputData['enable_ai_bot']) ? 0 : 1,
            'wa_id' => $inputData['phone_number'],
            'vendors__id' => $vendorId ?: getVendorId(),
        ];

        return $this->storeIt($inputData, $keyValues);
    }
    /**
     * Get vendor contact based on _id,_uid or phone_number which is wa_id
     *
     * @param string|integer|null $contactIdOrUid
     * @param string|null $vendorId
     * @return Eloquent object
     */
    public function getVendorContact(string|int|null $contactIdOrUid, ?string $vendorId = null)
    {
        $findBy = [
            'vendors__id' => $vendorId ? $vendorId : getVendorId(),
        ];

        if(request()->phone_number and isExternalApiRequest()) {
            $findBy['wa_id'] = (string) request()->phone_number;
        } else {
            if (is_numeric($contactIdOrUid)) {
                $findBy['_id'] = $contactIdOrUid;
            } else {
                $findBy['_uid'] = $contactIdOrUid;
            }
        }

        return $this->with([
            'lastMessage',
            'customFieldValues'
        ])->fetchIt($findBy);
    }

    /**
     * Get contact by phone number and vendor id
     *
     * @param integer $waId
     * @param string|null $vendorId
     * @return Eloquent
     */
    public function getVendorContactByWaId(int $waId, ?string $vendorId = null)
    {
        return $this->fetchIt([
            'vendors__id' => $vendorId ? $vendorId : getVendorId(),
            'wa_id' => (string) $waId,
        ]);
    }

    /**
     * Get the contact with unread message details using contact uid and vendor uid
     *
     * @param string|null $contactUid
     * @param int|null $vendorId
     * @return Eloquent
     */
    public function getVendorContactWithUnreadDetails($contactUid = null, $vendorId = null)
    {
        $whereClause = [
            'vendors__id' => $vendorId ?: getVendorId(),
        ];
        if($contactUid) {
            $whereClause['_uid'] = $contactUid;
        }
        $query = $this->primaryModel::where($whereClause)->with([
            'lastMessage',
            'lastUnreadMessage',
            'lastIncomingMessage',
        ])->withCount('unreadMessages');
        if(!$contactUid) {
            $query->has('lastIncomingMessage');
        }
        return $query->first();
    }

    /**
     * Get contacts by vendor id
     *
     * @param string|null $vendorId
     * @return Eloquent
     */
    public function getVendorContactsWithUnreadDetails($vendorId = null)
    {
        return $this->primaryModel::where([
            'vendors__id' => $vendorId ?: getVendorId(),
        ])->with('lastMessage')
            ->withCount('unreadMessages')
            ->has('lastIncomingMessage')
            ->get();
    }

    /**
     * Delete the selected contacts based on uids provided
     * for the logged in vendor
     *
     * @param array $contactUids
     * @param integer|null $vendorId
     * @return mixed
     */
    public function deleteSelectedContacts(array $contactUids, int|null $vendorId = null)
    {
        return $this->primaryModel::where([
            'vendors__id' => $vendorId ?: getVendorId()
        ])->whereIn('_uid', $contactUids)->delete();
    }
}
