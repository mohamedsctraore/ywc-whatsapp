<?php

/**
 * UserController.php - Controller file
 *
 * This file is part of the User component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\User\Controllers;

use App\Yantrana\Base\BaseController;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Components\Auth\Models\AuthModel;
use App\Yantrana\Components\User\UserEngine;
use App\Yantrana\Support\CommonClearPostRequest;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    /**
     * @var UserEngine - User Engine
     */
    protected $userEngine;

    /**
     * Constructor
     *
     * @param  UserEngine  $userEngine  - User Engine
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(UserEngine $userEngine)
    {
        $this->userEngine = $userEngine;
    }

    /**
     * Show the form for editing the profile.
     */
    public function profileEditForm()
    {
        return $this->loadView('user.profile-edit');
    }

    /**
     * Update Profile
     */
    public function updateProfile(CommonClearPostRequest $request)
    {
        // validate information
        $request->validate([
            'first_name' => ['required', 'min:3'],
            'last_name' => ['required', 'min:3'],
            'email' => [
                'required',
                'email',
                (getAppSettings('disallow_disposable_emails') ? 'indisposable' : ''),
                Rule::unique((new AuthModel())->getTable())->ignore(auth()->id(), '_id')
            ],
        ]);
        // process the request
        $processReaction = $this->userEngine->processUpdateProfile($request->all());

        // response
        return $this->processResponse($processReaction, [], [
            'show_message' => true,
        ], true);
    }

    /**
     * ChangeLocale - It also managed from index.php.
     *---------------------------------------------------------------- */
    protected function changeLocale(BaseRequest $request, $localeId = null)
    {
        if (is_string($localeId)) {
            changeAppLocale($localeId);

            if(!$request->ajax()) {
                return redirect('/');
            }

            return $this->processResponse(21, [], [
                'show_message' => true,
                'reloadPage' => true,
            ], true);
        }
        return abort(404);
    }


    /**
      * list of User
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function showUserView()
    {
        validateVendorAccess('administrative');
        // load the view
        return $this->loadView('user.list');
    }
    /**
      * list of User
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function prepareUserList()
    {
        validateVendorAccess('administrative');
        // respond with dataTables preparations
        return $this->userEngine->prepareUserDataTableSource();
    }

    /**
        * User process delete
        *
        * @param  mix $userIdOrUid
        *
        * @return  json object
        *---------------------------------------------------------------- */

    public function processUserDelete($userIdOrUid, BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // ask engine to process the request
        $processReaction = $this->userEngine->processUserDelete($userIdOrUid);
        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
      * User create process
      *
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function processUserCreate(BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // process the validation based on the provided rules
        $request->validate([
            'email' => 'required|string|email|unique:users,email' . (getAppSettings('disallow_disposable_emails') ? '|indisposable' : ''),
            'password' => 'required|string|min:8',
            'username' => 'required|string|unique:users|alpha_dash|min:2|max:45|unique:users,username',
            'first_name' => 'required|string|min:1|max:45',
            'last_name' => 'required|string|min:1|max:45',
            'mobile_number' => 'required|numeric|min_digits:9|max_digits:15',
        ]);
        // ask engine to process the request
        $processReaction = $this->userEngine->processUserCreate($request->all());
        // get back with response
        return $this->processResponse($processReaction);
    }

    /**
      * User login as
      *
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

      public function loginAsUser(BaseRequest $request, $userIdOrUid)
      {
          validateVendorAccess('administrative');
          // ask engine to process the request
          $processReaction = $this->userEngine->processLoginAs($userIdOrUid);
          if($processReaction->failed()) {
            return $this->processResponse($processReaction, [], [], true);
          }
          // get back with response
          return $this->responseAction(
            $this->processResponse($processReaction, [], [], true),
                $this->redirectTo(hasVendorAccess()
                ? 'vendor.console'
                : 'home')
        );
      }
    /**
      * User logout as
      *
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

      public function logoutAsUser(BaseRequest $request)
      {
        //   validateVendorAccess('administrative');
          // ask engine to process the request
          $processReaction = $this->userEngine->processLogoutAs();
          // get back with response
          return $this->responseAction(
            $this->processResponse($processReaction, [], [], true),
                $this->redirectTo(hasVendorAccess()
                ? 'vendor.console'
                : 'home')
        );
      }

    /**
      * User get update data
      *
      * @param  mix $userIdOrUid
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function updateUserData($userIdOrUid)
    {
        validateVendorAccess('administrative');
        // ask engine to process the request
        $processReaction = $this->userEngine->prepareUserUpdateData($userIdOrUid);
        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
      * User process update
      *
      * @param  mix @param  mix $userIdOrUid
      * @param  object BaseRequest $request
      *
      * @return  json object
      *---------------------------------------------------------------- */

    public function processUserUpdate(BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // process the validation based on the provided rules
        $request->validate([
            'userIdOrUid' => 'required',
            'email' => [
                'required',
                'email',
                (getAppSettings('disallow_disposable_emails') ? 'indisposable' : ''),
                Rule::unique((new AuthModel())->getTable())->ignore($request->get('userIdOrUid'), '_uid')
            ],
            'password' => 'nullable|string|min:8',
            'first_name' => 'required|string|min:1|max:45',
            'last_name' => 'required|string|min:1|max:45',
            'mobile_number' => 'required|numeric|min_digits:9|max_digits:15',
        ]);
        // ask engine to process the request
        $processReaction = $this->userEngine->processUserUpdate($request->get('userIdOrUid'), $request);
        // get back with response
        return $this->processResponse($processReaction, [], [], true);
    }
}
