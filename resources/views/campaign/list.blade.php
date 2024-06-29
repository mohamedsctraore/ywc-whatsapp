@php
/**
* Component : Campaign
* Controller : CampaignController
* File : campaign.list.blade.php
* -----------------------------------------------------------------------------
*/
@endphp
@extends('layouts.app', ['title' => __tr('Campaigns')])
@section('content')
@include('users.partials.header', [
'title' => __tr('Campaigns'),
'description' => '',
'class' => 'col-lg-7'
])
<div class="container-fluid mt-lg--6">
    <div class="row">
        <!-- button -->
        <div class="col-xl-12 mb-3">
                <a class="lw-btn btn btn-primary float-right" href="{{ route('vendor.campaign.new.view') }}">{{ __tr('Create New Campaign') }}</a>
        </div>
        <!--/ button -->

        <div class="col-xl-12">
            <x-lw.datatable data-page-length="100" id="lwCampaignList" :url="route('vendor.campaign.read.list')">
                <th data-orderable="true" data-name="title">{{ __tr('Title') }}</th>
                <th data-orderable="true" data-name="template_name">{{ __tr('Template') }}</th>
                <th data-orderable="true" data-name="template_language">{{ __tr('Template Language') }}</th>
                <th data-orderable="true" data-name="created_at">{{ __tr('Created At') }}</th>
                <th data-orderable="true" data-order-type="desc" data-order-by="true" data-name="scheduled_at">{{ __tr('Schedule At') }}</th>
                <th data-template="#campaignActionColumnTemplate" name="null">{!! __tr('Action & Status') !!}</th>
            </x-lw.datatable>
        </div>
        <!-- action template -->
        <script type="text/template" id="campaignActionColumnTemplate">
<!--  Delete Action -->
<% if(__tData.delete_allowed) { %>
    <span class="badge badge-success"><%- __tData.scheduled_status %></span>
<a data-method="post" href="<%= __Utils.apiURL("{{ route('vendor.campaign.write.delete', [ 'campaignIdOrUid']) }}", {'campaignIdOrUid': __tData._uid}) %>" class="btn btn-danger btn-sm lw-ajax-link-action-via-confirm" data-confirm="#lwDeleteCampaign-template" title="{{ __tr('Delete') }}" data-callback-params="{{ json_encode(['datatableId' => '#lwCampaignList']) }}" data-callback="appFuncs.modelSuccessCallback"><i class="fa fa-trash"></i> {{  __tr('Delete') }}</a>
<% } else { %>
    <span class="badge badge-warning p-2"><%- __tData.scheduled_status %></span>
<% } %>
<a href="<%= __Utils.apiURL("{{ route('vendor.campaign.status.view', ['campaignUid' => 'campaignUid',]) }}", {'campaignUid': __tData._uid}) %>" class="btn btn-dark btn-sm" title="{{ __tr('Campaign Details') }}"><i class="fa fa-tachometer"></i> {{  __tr('Campaign Dashboard') }}</a>
    </script>
        <!-- /action template -->

        <!-- Campaign delete template -->
        <script type="text/template" id="lwDeleteCampaign-template">
            <h2>{{ __tr('Are You Sure!') }}</h2>
            <p>{{ __tr('Are you sure you want to delete this Campaign?') }}</p>
    </script>
        <!-- /Campaign delete template -->
    </div>
</div>
@endsection()