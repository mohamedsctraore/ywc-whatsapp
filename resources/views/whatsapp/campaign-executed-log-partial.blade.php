   
{{-- datatable executed log --}}
<x-lw.datatable lw-card-classes="border-0" data-page-length="100" id="lwCampaignQueueLog" :url="route('vendor.campaign.executed.log.list.view', ['campaignUid' => $campaignUid])">
    <th data-orderable="true" data-name="full_name">{{ __tr('Name') }}</th>
    {{-- <th data-orderable="true" data-name="last_name">{{ __tr('Last Name') }}</th> --}}
    <th data-orderable="true" data-name="contact_wa_id">{{ __tr('Phone Number') }}</th>
    <th data-orderable="true" data-name="status">{{ __tr('Status') }}</th>

    <th data-orderable="true" data-name="messaged_at">{{ __tr('Message Delivered at') }}</th>
    <th data-orderable="true" data-order-by="true" data-order-type="desc" data-name="updated_at">{{ __tr('Last Status Updated at') }}</th>
</x-lw.datatable>
{{-- /datatable executed log --}}
