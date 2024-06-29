<?php
/**
* ManualSubscriptionEngine.php - Main component file
*
* This file is part of the Subscription component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Subscription;

use Illuminate\Support\Arr;
use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\Vendor\Repositories\VendorRepository;
use App\Yantrana\Components\Subscription\Repositories\ManualSubscriptionRepository;
use App\Yantrana\Components\Subscription\Interfaces\ManualSubscriptionEngineInterface;
use Illuminate\Support\Carbon;

class ManualSubscriptionEngine extends BaseEngine implements ManualSubscriptionEngineInterface
{
    /**
     * @var  ManualSubscriptionRepository $manualSubscriptionRepository - ManualSubscription Repository
     */
    protected $manualSubscriptionRepository;

    /**
     * @var VendorRepository - Vendor Repository
     */
    protected $vendorRepository;

    /**
      * Constructor
      *
      * @param  ManualSubscriptionRepository $manualSubscriptionRepository - ManualSubscription Repository
      * @param  VendorRepository $vendorRepository - Vendor Repository
      *
      * @return  void
      *-----------------------------------------------------------------------*/

    public function __construct(
        ManualSubscriptionRepository $manualSubscriptionRepository,
        VendorRepository $vendorRepository
    ) {
        $this->manualSubscriptionRepository = $manualSubscriptionRepository;
        $this->vendorRepository = $vendorRepository;
    }


    /**
      * ManualSubscription datatable source
      *
      * @return  array
      *---------------------------------------------------------------- */
    public function prepareManualSubscriptionDataTableSource($vendorUid = null)
    {
        $vendorId = null;
        if($vendorUid) {
            $vendor = $this->vendorRepository->fetchIt($vendorUid);
            abortIf(__isEmpty($vendor));
            $vendorId = $vendor->_id;
        }
        $manualSubscriptionCollection = $this->manualSubscriptionRepository->fetchManualSubscriptionDataTableSource($vendorId);
        $subscriptionPlans = getPaidPlans();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'plan_id',
            'charges_frequency',
            'vendor_title' => function ($rowData) {
                return $rowData['vendor']['title'] ?? '';
            },
            'vendor_uid' => function ($rowData) {
                return $rowData['vendor']['_uid'] ?? '';
            },
            'options' => function ($rowData) {
                return [
                    'is_expired' => $rowData['ends_at'] < now()
                ];
            },
            'charges' => function ($rowData) {
                return formatAmount($rowData['charges'], true, true);
            },
            'plan_id' => function ($rowData) use (&$subscriptionPlans) {
                return Arr::get($subscriptionPlans, $rowData['plan_id'] . '.title');
            },
            'created_at' => function ($rowData) {
                return formatDate($rowData['created_at']);
            },
            'ends_at' => function ($rowData) {
                return formatDate($rowData['ends_at']);
            },
            'status',
            'remarks',
        ];
        // prepare data for the DataTables
        return $this->dataTableResponse($manualSubscriptionCollection, $requireColumns);
    }


    /**
      * ManualSubscription delete process
      *
      * @param  mix $manualSubscriptionIdOrUid
      *
      * @return  EngineResponse
      *---------------------------------------------------------------- */

    public function processManualSubscriptionDelete($manualSubscriptionIdOrUid)
    {
        // fetch the record
        $manualSubscription = $this->manualSubscriptionRepository->fetchIt($manualSubscriptionIdOrUid);
        // check if the record found
        if (__isEmpty($manualSubscription)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Manual Subscription not found'));
        }
        // ask to delete the record
        if ($this->manualSubscriptionRepository->deleteIt($manualSubscription)) {
            // if successful
            return $this->engineResponse(1, null, __tr('Manual Subscription deleted successfully'));
        }
        // if failed to delete
        return $this->engineResponse(2, null, __tr('Failed to delete ManualSubscription'));
    }

    /**
      * ManualSubscription create
      *
      * @param  BaseRequest $request
      *
      * @return  EngineResponse
      *---------------------------------------------------------------- */

    public function processManualSubscriptionCreate($request)
    {
        $vendor = $this->vendorRepository->fetchIt($request->vendor_uid);
        $planRequest = explode('___', $request->plan);
        abortIf(__isEmpty($vendor) or (!isset($planRequest[0]) or !isset($planRequest[1])));
        // ask to add record
        $engineResponse = $this->manualSubscriptionRepository->processTransaction(function () use (&$planRequest, &$vendor, &$request) {
            $subscriptionPlans = getPaidPlans();
            $planId = $planRequest[0];
            $planFrequencyKey = $planRequest[1];
            $getPlanDetails = Arr::get($subscriptionPlans, $planId);
            $planCharge = Arr::get($getPlanDetails, 'charges.'.$planRequest[1].'.charge');
            // set the existing subscription as cancelled
            $this->manualSubscriptionRepository->updateItAll([
                'status' => 'active',
                'vendors__id' => $vendor->_id,
            ], [
                'status' => 'cancelled',
            ]);
            if ($this->manualSubscriptionRepository->storeIt([
                'plan_id' => $planId,
                'charges_frequency' => $planFrequencyKey,
                'charges' => $planCharge,
                'remarks' => $request->remarks,
                'ends_at' => $request->ends_at,
                'status' => 'active',
                'vendors__id' => $vendor->_id,
            ])) {
                return $this->manualSubscriptionRepository->transactionResponse(1, [], __tr('Manual Subscription added.'));
            }
            return $this->manualSubscriptionRepository->transactionResponse(2, [], __tr('Manual Subscription not added.'));
        });
        return $this->engineResponse($engineResponse);
    }

    /**
      * ManualSubscription prepare update data
      *
      * @param  mix $manualSubscriptionIdOrUid
      *
      * @return  array
      *---------------------------------------------------------------- */

    public function prepareManualSubscriptionUpdateData($manualSubscriptionIdOrUid)
    {
        $manualSubscription = $this->manualSubscriptionRepository->fetchIt($manualSubscriptionIdOrUid);

        // Check if $manualSubscription not exist then throw not found
        // exception
        if (__isEmpty($manualSubscription)) {
            return $this->engineResponse(18, null, __tr('Manual Subscription not found.'));
        }
        $manualSubscriptionArray = $manualSubscription->toArray();
        $manualSubscriptionArray['ends_at'] = Carbon::parse($manualSubscriptionArray['ends_at'])->format('Y-m-d');
        return $this->engineResponse(1, $manualSubscriptionArray);
    }

    /**
      * ManualSubscription process update
      *
      * @param  mixed $manualSubscriptionIdOrUid
      * @param  array $inputData
      *
      * @return  EngineResponse
      *---------------------------------------------------------------- */

    public function processManualSubscriptionUpdate($manualSubscriptionIdOrUid, $inputData)
    {
        $manualSubscription = $this->manualSubscriptionRepository->fetchIt($manualSubscriptionIdOrUid);

        // Check if $manualSubscription not exist then throw not found
        // exception
        if (__isEmpty($manualSubscription)) {
            return $this->engineResponse(18, null, __tr('Manual Subscription not found.'));
        }

        $updateData = [
            'ends_at' => $inputData['ends_at'],
            'status' => $inputData['status'],
            'remarks' => $inputData['remarks'],
            'charges' => $inputData['charges'],
        ];

        // Check if ManualSubscription updated
        if ($this->manualSubscriptionRepository->updateIt($manualSubscription, $updateData)) {
            return $this->engineResponse(1, null, __tr('Manual Subscription updated.'));
        }
        return $this->engineResponse(14, null, __tr('Manual Subscription not updated.'));
    }

    public function prepareSelectedPlanDetails($request)
    {
        $planRequest = explode('___', $request->selected_plan);
        abortIf(!isset($planRequest[0]) or !isset($planRequest[1]), null, __tr('Invalid Plan or Frequency'));
        $planFrequencyKey = $planRequest[1];
        $planDetails = getPaidPlans($planRequest[0]);
        $planCharges = formatAmount($planDetails['charges'][$planFrequencyKey]['charge'], true, true);
        $endsAt = $planFrequencyKey == 'monthly' ? now()->addMonth() : now()->addYear();
        updateClientModels([
            'calculated_ends_at' => $endsAt->format('Y-m-d')
        ]);
        return $this->engineSuccessResponse();
    }
    public function processManualPayPreparation($request)
    {
        $planRequest = explode('___', $request->selected_plan);
        abortIf(!isset($planRequest[0]) or !isset($planRequest[1]), null, __tr('Invalid Plan or Frequency'));
        $planFrequencyKey = $planRequest[1];
        $planDetails = getPaidPlans($planRequest[0]);
        abortIf(!$planDetails, null, __tr('Invalid Plan or Frequency'));
        $planCharges = $planDetails['charges'][$planFrequencyKey]['charge'];
        $planChargesFormatted = formatAmount($planCharges, true, true);
        $planFrequencyTitle = $planDetails['charges'][$planFrequencyKey]['title'];
        $endsAt = $planFrequencyKey == 'monthly' ? now()->addMonth() : now()->addYear();
        $vendorId = getVendorId();
        $existingRequestExist = false;
        // existing pending request
        $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
            'vendors__id' => $vendorId,
            'status' => 'initiated',
        ]);
        if(__isEmpty($subscriptionRequestRecord)) {
            $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
                'vendors__id' => $vendorId,
                'status' => 'pending',
            ]);
        }
        if(__isEmpty($subscriptionRequestRecord)) {
            $subscriptionRequestRecord = $this->manualSubscriptionRepository->storeIt([
                'plan_id' => $planDetails['id'],
                'charges_frequency' => $planFrequencyKey,
                'charges' => $planCharges,
                'remarks' => '',
                'ends_at' => $endsAt,
                'status' => 'initiated',
                'vendors__id' => $vendorId,
                '__data' => [
                    'manual_txn_details' => [
                        'selected_payment_method' => $request->payment_method
                    ]
                ],
            ]);
            abortIf(!$subscriptionRequestRecord, null, __tr('Failed to create subscription'));
        } else {
            $existingRequestExist = true;
            $planDetails['id'] = $subscriptionRequestRecord->plan_id;
            $planCharges = $subscriptionRequestRecord->charges;
            $planDetails['charges'][$planFrequencyKey]['charge'] = $planCharges;
            $planChargesFormatted = formatAmount($planCharges, true, true);
        }

        // Example usage
        $upiId = getAppSettings('payment_upi_address');
        $payeeName = getAppSettings('name');
        $amount = $planCharges;
        $transactionRef = 'txn_ref_' . $subscriptionRequestRecord->_id;
        $transactionNote = "$payeeName-{$planDetails['id']}-$planFrequencyTitle-Subscription-" . $subscriptionRequestRecord->_id;
        $upiPaymentLink = createUpiLink($upiId, $payeeName, $amount, $transactionRef, $transactionNote);

        return $this->engineSuccessResponse([
            'subscriptionRequestRecord' => $subscriptionRequestRecord,
            'existingRequestExist' => $existingRequestExist,
            'expiryDate' => $endsAt->format('Y-m-d'),
            'planChargesFormatted' => $planChargesFormatted,
            'planDetails' => $planDetails,
            'planFrequencyTitle' => $planFrequencyTitle,
            'planCharges' => $planCharges,
            'upiPaymentQRImageUrl' => route('vendor.generate.upi_payment_request', [
                'url' => base64_encode($upiPaymentLink)
            ]),
        ]);
    }

    function recordSentPaymentDetails($request) {
        $vendorId = getVendorId();
        $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
            'vendors__id' => $vendorId,
            'status' => 'initiated',
            '_uid' => $request->manual_subscription_uid,
        ]);

        if (__isEmpty($subscriptionRequestRecord)) {
            return $this->engineFailedResponse([], __tr('Invalid Subscription Request'));
        }

        $isTxnReferenceExists = $this->manualSubscriptionRepository->fetchIt([
            'vendors__id' => $vendorId,
            '__data->manual_txn_details->txn_reference' => $request->txn_reference,
        ]);
        if(!__isEmpty($isTxnReferenceExists)) {
            return $this->engineFailedResponse([], __tr('This Txn is already processed'));
        }

        if($this->manualSubscriptionRepository->updateIt($subscriptionRequestRecord, [
            'status' => 'pending',
            '__data' => [
                'manual_txn_details' => [
                    'txn_reference' => $request->txn_reference,
                    'txn_date' => $request->txn_date,
                ]
            ]
        ])) {
            return $this->engineSuccessResponse();
        }

        return $this->engineFailedResponse([], __tr('Failed to record your payment details'));
    }

    /**
     * Delete Vendor Manual Subscription Request
     *
     * @param BaseRequest $request
     * @return EngineResponse
     */
    function processDeleteRequest($request) {
        $vendorId = getVendorId();
        $subscriptionRequestDeleted = $this->manualSubscriptionRepository->deleteItAll([
            'vendors__id' => $vendorId,
            'status' => 'initiated',
        ]);

        if ($subscriptionRequestDeleted) {
            return $this->engineSuccessResponse();
        }

        return $this->engineFailedResponse([], __tr('Failed to delete your request'));
    }
}
