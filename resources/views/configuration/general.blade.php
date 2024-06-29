<!-- Page Heading -->
<h1><?= __tr('General Settings') ?></h1>
<!-- Page Heading -->
<hr>
<!-- General setting form -->
<form class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => request()->pageType]) ?>">
<div class="row">
</div>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-default">
                {{  __tr('Upload will be processed automatically on valid selection.') }}
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group float-left mr-4">
                <label for="lwUploadLogo"><?= __tr('Logo') ?></label>
            <input type="file" data-lw-plugin="lwUploader" data-label-idle="{{ __tr('Select New Logo') }}" data-allow-revert="true" data-instant-upload="true" data-action="<?= route('media.upload_logo') ?>" id="lwUploadLogo" data-callback="afterUploadedFile" data-default-image-url="<?= getAppSettings('logo_image_url') ?>">
            </div>
            <div class="form-group float-right">
                <label for="lwUploadFavicon"><?= __tr('Favicon') ?></label>
                <input type="file" data-lw-plugin="lwUploader" data-label-idle="{{ __tr('Select New Favicon') }}" data-instant-upload="true" data-action="<?= route('media.upload_favicon') ?>" data-callback="afterUploadedFile" id="lwUploadFavicon" data-default-image-url="<?= getAppSettings('favicon_image_url') ?>">
            </div>
        </div>
    </div>
    <hr>
    <!-- Website Name -->
    <div class="form-group">
        <label for="lwWebsiteName"><?= __tr('Your Website Name') ?></label>
        <input type="text" class="form-control form-control-user" name="name" id="lwWebsiteName" value="<?= $configurationData['name'] ?>" required>
    </div>
    <!-- /Website Name -->
    <!-- Contact Email -->
    <div class="form-group">
        <label for="lwContactEmail"><?= __tr('Contact Email') ?></label>
        <input type="email" class="form-control form-control-user" name="contact_email" id="lwContactEmail" value="<?= $configurationData['contact_email'] ?>">
    </div>
    <!-- /Contact Email -->

    <!-- Select Timezone -->
    <div class="form-group">
        <label for="lwSelectTimezone"><?= __tr('Select Timezone') ?></label>
        <select data-lw-plugin="lwSelectize" data-label-field="name" data-selected="{{ $configurationData['timezone'] }}" data-search-field="{{ json_encode(['id','name']) }}" data-value-field="id" id="lwSelectTimezone" class="form-control form-control-user" name="timezone" required>
            @foreach($configurationData['timezone_list'] as $timezone)
            <option value="<?= $timezone['value'] ?>"><?= $timezone['text'] ?></option>
            @endforeach
        </select>
    </div>
    <!-- /Select Timezone -->

    <!-- Select Default language -->
    <div class="form-group mt-2">
        <label for="lwSelectDefaultLanguage"><?= __tr('Default Language') ?></label>
        <select id="lwSelectDefaultLanguage" data-lw-plugin="lwSelectize" placeholder="Default Language..." name="default_language">
            @if(!__isEmpty($configurationData['languageList']))
            @foreach($configurationData['languageList'] as $key => $language)
            <option value="<?= $language['id'] ?>" <?= $configurationData['default_language'] == $language['id'] ? 'selected' : '' ?> required><?= $language['name'] ?></option>
            @endforeach
            @endif
        </select>
    </div>
    <!-- /Select Default language -->

    <!-- Update Button -->
    <a href class="lw-ajax-form-submit-action btn btn-primary btn-user lw-btn-block-mobile">
        <?= __tr('Save') ?>
    </a>
    <!-- /Update Button -->
</form>
<!-- /General setting form -->
@push('appScripts')
<script>
    (function($) {
        'use strict';
    // After file successfully uploaded then this function is called
    window.afterUploadedFile = function (responseData) {
        var requestData = responseData.data;
        $('#lwUploadedLogo').attr('src', requestData.path);
    }
    })(jQuery);
</script>
@endpush