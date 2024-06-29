@php
/**
* Component     : Contact
* Controller    : ContactGroupController
* File          : group.list.blade.php
----------------------------------------------------------------------------- */
@endphp
@extends('layouts.app', ['title' => __tr('Contact Groups')])
@section('content')
@include('users.partials.header', [
    'title' => __tr('Contact Groups'),
    'description' => '',
    'class' => 'col-lg-7'
])
<div class="container-fluid mt-lg--6">
    <div class="row">
                         <!-- button -->
        <div class="col-xl-12 mb-3">
            <button type="button" class="lw-btn btn btn-primary float-right" data-toggle="modal" data-target="#lwAddNewGroup"> {{ __tr('Add New Group') }}</button>
        </div>
        <!--/ button -->
        <!-- Add New Group Modal -->
    <x-lw.modal id="lwAddNewGroup" :header="__tr('Add New Group')" :hasForm="true">
        <!--  Add New Group Form -->
        <x-lw.form id="lwAddNewGroupForm" :action="route('vendor.contact.group.write.create')"  :data-callback-params="['modalId' => '#lwAddNewGroup', 'datatableId' => '#lwGroupList']" data-callback="appFuncs.modelSuccessCallback">
            <!-- form body -->
            <div class="lw-form-modal-body">
                <!-- form fields form fields -->
            <!-- Title -->
           <x-lw.input-field type="text" id="lwTitleField" data-form-group-class="" :label="__tr('Title')"  name="title"  required="true"                 />
                <!-- /Title -->
                <!-- Description -->
                <div class="form-group">
                <label for="lwDescriptionField">{{ __tr('Description') }}</label>
                <textarea cols="10" rows="3" id="lwDescriptionField"  class="lw-form-field form-control" placeholder="{{ __tr('Description') }}" name="description"          ></textarea>
            </div>
                <!-- /Description -->
             </div>
            <!-- form footer -->
            <div class="modal-footer">
                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Close') }}</button>
            </div>
        </x-lw.form>
        <!--/  Add New Group Form -->
    </x-lw.modal>
    <!--/ Add New Group Modal -->
        
                <!-- Edit Group Modal -->
                <x-lw.modal id="lwEditGroup" :header="__tr('Edit Group')" :hasForm="true">
                <!--  Edit Group Form -->
                <x-lw.form id="lwEditGroupForm" :action="route('vendor.contact.group.write.update')"  :data-callback-params="['modalId' => '#lwEditGroup', 'datatableId' => '#lwGroupList']" data-callback="appFuncs.modelSuccessCallback">
                    <!-- form body --> 
                    <div id="lwEditGroupBody" class="lw-form-modal-body"></div>
                    <script type="text/template" id="lwEditGroupBody-template">
                        
                        <input type="hidden" name="contactGroupIdOrUid" value="<%- __tData._uid %>" />
                        <!-- form fields -->
                        <!-- Title -->
           <x-lw.input-field type="text" id="lwTitleEditField" data-form-group-class="" :label="__tr('Title')" value="<%- __tData.title %>" name="title"  required="true"                 />
                <!-- /Title -->
                <!-- Description -->
                <div class="form-group">
                <label for="lwDescriptionEditField">{{ __tr('Description') }}</label>
                <textarea cols="10" rows="3" id="lwDescriptionEditField" value="<%- __tData.description %>" class="lw-form-field form-control" placeholder="{{ __tr('Description') }}" name="description"          ><%- __tData.description %></textarea>
            </div>
                <!-- /Description -->
                     </script>
                    <!-- form footer -->
                    <div class="modal-footer">
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Close') }}</button>
                    </div>
                </x-lw.form>
                <!--/  Edit Group Form -->
            </x-lw.modal>
            <!--/ Edit Group Modal -->
                        <div class="col-xl-12">
                        <x-lw.datatable data-page-length="50" id="lwGroupList" :url="route('vendor.contact.group.read.list')">
                                <th data-orderable="true" data-name="title">{{ __tr('Title') }}</th>
                                 <th  data-name="description">{{ __tr('Description') }}</th>
                                 <th data-template="#groupActionColumnTemplate" name="null">{{ __tr('Action') }}</th>
                            </x-lw.datatable>
            
            
        </div>
                
        <!-- action template -->
        <script type="text/template" id="groupActionColumnTemplate">
            <a title="{{  __tr('Group Contacts') }}" class="lw-btn btn btn-sm btn-warning" href="<%= __Utils.apiURL("{{ route('vendor.contact.read.list_view', [ 'groupUid']) }}", {'groupUid': __tData._uid}) %>"><i class="fa fa-users"></i> {{  __tr('Group Contacts') }}</a>
            <a data-pre-callback="appFuncs.clearContainer" title="{{  __tr('Edit') }}" class="lw-btn btn btn-sm btn-default lw-ajax-link-action" data-response-template="#lwEditGroupBody" href="<%= __Utils.apiURL("{{ route('vendor.contact.group.read.update.data', [ 'contactGroupIdOrUid']) }}", {'contactGroupIdOrUid': __tData._uid}) %>"  data-toggle="modal" data-target="#lwEditGroup"><i class="fa fa-edit"></i> {{  __tr('Edit') }}</a>
            <!--  Delete Action -->
            <a data-method="post" href="<%= __Utils.apiURL("{{ route('vendor.contact.group.write.delete', [ 'contactGroupIdOrUid']) }}", {'contactGroupIdOrUid': __tData._uid}) %>" class="btn btn-danger btn-sm lw-ajax-link-action-via-confirm" data-confirm="#lwDeleteGroup-template" title="{{ __tr('Delete') }}" data-callback-params="{{ json_encode(['datatableId' => '#lwGroupList']) }}" data-callback="appFuncs.modelSuccessCallback"><i class="fa fa-trash"></i> {{  __tr('Delete') }}</a>
    </script>
<!-- /action template -->
     
    <!-- Group delete template -->
    <script type="text/template" id="lwDeleteGroup-template">
            <h2>{{ __tr('Are You Sure!') }}</h2>
            <p>{{ __tr('Are you sure you want to delete this Group?') }}</p>
    </script>
    <!-- /Group delete template -->
        </div>
</div>
@endsection()