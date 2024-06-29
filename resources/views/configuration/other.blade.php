<!-- Page Heading -->
<section>
    <h1>{!! __tr('Setup & Integrations') !!}</h1>
    <fieldset>
        <legend>{{ __tr('Cron Job - required for the campaigns to run as per schedule') }}</legend>
        <p>{{  __tr('You need to setup cron as given below for every minute') }}</p>
        <div class="col-sm-12 col-md-10">
            <fieldset>
                <legend>{{  __tr('Recommended') }}</legend>
                @if (defined('PHP_BINDIR') and PHP_BINDIR)
            <p>{{  __tr('You need to add a single cron configuration entry to your server that runs the schedule:run command every minute.') }}</p>
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwArtisanPHPBinaryOption" value="{{ isDemo() ? __tr('/path-to-the-php-binary') : PHP_BINDIR }}/php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run >> /dev/null 2>&1">
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanPHPBinaryOption')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
            <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
            <div class="text-orange my-2">{{  __tr('If special characters not accepting you can use this') }}</div>
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwArtisanPHPBinaryOption2" value="{{ isDemo() ? __tr('/path-to-the-php-binary') : PHP_BINDIR }}/php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run">
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanPHPBinaryOption2')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
            <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
            @endif
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwArtisanOption" value="php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run >> /dev/null 2>&1">
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanOption')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
            <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
            <div class="text-orange my-2">{{  __tr('If special characters not accepting you can use this') }}</div>
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwArtisanOption2" value="php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run">
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanOption2')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
            @if (getAppSettings('cron_setup_using_artisan_at'))
            <h3 class="text-success my-4">
                <i class="fa fa-check"></i> {{  __tr('As \'artisan schedule:run\' command executed, it seems that cron setup is done.') }}
            </h3>
            @else
            <h3 class="text-orange my-4">
                {{ __tr('System does not recognized that cron job has been setup using artisan schedule:run schedule command. This is just for information you can use other ways as stated on this page.')  }}
            </h3>
            @endif
            </fieldset>
            <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwWgetOption" value='wget -O - -q "{{ route('campaign.run_schedule.process') }}" --user-agent="cron" > /dev/null 2>&1'>
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwWgetOption')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
            <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
            <p>{{  __tr('Or you can find other ways on net to run cron job you need to access following url for the same.') }}</p>
            <div class="input-group">
                <input type="text" class="form-control" readonly id="lwCommonUrlOption" value='{{ route('campaign.run_schedule.process') }}'>
                <div class="input-group-append">
                    <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwCommonUrlOption')">
                        <?= __tr('Copy') ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-12">
            @if (getAppSettings('cron_setup_done_at'))
            <h2 class="text-success my-4">
                <i class="fa fa-check"></i> {{  __tr('You have confirmed that cron setup has been done at __doneAt__', [
                    '__doneAt__' => formatDateTime(getAppSettings('cron_setup_done_at'))
                ]) }}
            </h2>
           @else
            <form class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'internals']) ?>" >
            <div class="my-4">
                <input type="hidden" name="cron_setup_done_at" value="{{ now() }}">
                {{-- submit button --}}
                <button type="submit" href class="btn btn-primary btn-user lw-btn-block-mobile">
                    <?= __tr('Cron Setup: Mark as Done') ?>
                </button>
            </div>
            </form>
            @endif
        </div>
    </fieldset>
    <fieldset>
        <legend>{{ __tr('Pusher - required for realtime updates') }}</legend>
        <form class="lw-ajax-form lw-form" method="post"
        action="<?= route('manage.configuration.write', ['pageType' => 'pusher']) ?>" x-cloak
        x-data="{pusherSettingsExists: {{ getAppSettings('pusher_app_id') ? 1 : 0 }}}">
        <div x-show="pusherSettingsExists"></div>
        <div class="form-group" x-cloak x-show="pusherSettingsExists">
            <div class="btn-group">
                <button type="button" disabled="true" class="btn btn-success lw-btn">
                    {{ __tr('Pusher Settings are exist') }}
                </button>
                <button type="button" @click="pusherSettingsExists = !pusherSettingsExists"
                    class="btn btn-light lw-btn">{{ __tr('Update') }}</button>
            </div>
        </div>
        <div x-show="!pusherSettingsExists" class="col-sm-12 col-md-6 col-lg-4">
            <x-lw.input-field type="text" id="lwPusherAppId" data-form-group-class="" :label="__tr('App ID')" name="pusher_app_id" required="true" />
            <x-lw.input-field type="text" id="lwPusherKey" data-form-group-class="" :label="__tr('App Key')" name="pusher_app_key" required="true" />
            <x-lw.input-field type="text" id="lwPusherAppSecret" data-form-group-class="" :label="__tr('App Secret')" name="pusher_app_secret" required="true" />
            <x-lw.input-field type="text" id="lwPusherAppCluster" data-form-group-class="" :label="__tr('App Cluster')" name="pusher_app_cluster" required="true" />
        <div class="form-group">
            {{-- submit button --}}
        <button type="submit" href class="btn btn-primary btn-user lw-btn-block-mobile">
            <?= __tr('Save') ?>
        </button>
        </div>
    </div>
        </form>
    </fieldset>
    <form class="lw-ajax-form lw-form" method="post"
        action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>"
        x-data="{disableMicrosoftTranslatorKeyUpdate:'{{ getAppSettings("microsoft_translator_api_key")}}'}">
        <fieldset>
            <legend>{{ __tr('Microsoft Translator API') }}</legend>
            <div class="alert alert-light">
                <a target="_blank"
                    href="https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator-text-api/">https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator-text-api/</a>
            </div>
            <div class="form-group " >
                <div x-cloak x-show="!disableMicrosoftTranslatorKeyUpdate">
                       <!--microsift translator -->
                <label for="lwMicrosoftTranslatorAPIKey">
                    <?= __tr('Microsoft Translator API Key') ?>
                </label>
                <input type="text" class="form-control form-control-user" name="microsoft_translator_api_key"
                    id="lwMicrosoftTranslatorAPIKey">
                    <div class="form-group">
                        <!-- /microsift translator -->

                </div>
                  <!--microsift translator api Region -->
                <div x-cloak x-show="!disableMicrosoftTranslatorKeyUpdate">
                    <label for="lwMicrosoftTranslatorAPIRegionKey">
                        <?= __tr('Region') ?>
                    </label>
                    <input type="text" class="form-control form-control-user" name="microsoft_translator_api_region"
                        id="lwMicrosoftTranslatorAPIRegionKey">
                        <div class="form-group">
                </div>
                  <!--/microsift translator api Region -->
                    <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                    </div>
            </div>
            <div class="form-group" x-cloak x-show="disableMicrosoftTranslatorKeyUpdate">
                <div class="btn-group" id="lwLiveStripeCheckoutExists">
                    <button type="button" disabled="true" class="btn btn-success lw-btn">
                        {{ __tr('Microsoft Translator API Key exist') }}
                    </button>
                    <button type="button"
                        @click="disableMicrosoftTranslatorKeyUpdate = !disableMicrosoftTranslatorKeyUpdate"
                        class="btn btn-light lw-btn">{{ __tr('Update Key') }}</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="lw-ajax-form lw-form" method="post"
        action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
        <!-- Start recaptcha settings -->
        <fieldset>
            <legend>{{ __tr("Google Re-Captcha") }}</legend>
            <div class="form-group">
                <!-- Recaptcha login settings -->
                <!-- Enable Recaptcha login hidden field -->
                <input type="hidden" name="enable_recaptcha" value="0" />
                <!-- /Enable Recaptcha login hidden field -->
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" onclick="allowFunction()" id="lwAllowRecaptcha"
                        name="enable_recaptcha" value="1" <?=getAppSettings('enable_recaptcha')==true ? 'checked' : ''
                        ?>>
                        <label class="custom-control-label" for="lwAllowRecaptcha">
                            {{ __tr('Enable ReCaptcha') }}
                         </label>
                </div>
                <!-- /allow Recaptcha login input radio field -->
                <div class="mt-3" id="inputFieldShow" style="display:none">
                    <!-- Show after Recaptcha login allow information -->

                    <div class="btn-group mx-4" id="lwIsReCaptchaKeysExist">
                        <button type="button" disabled="true" class="btn btn-success lw-btn lw-payment-mbl-view">
                            <?= __tr('ReCaptcha keys are installed.') ?>
                        </button>
                        <button type="button" class="btn btn-light lw-btn" id="lwAddReCaptchaKeys">
                            <?= __tr('Update') ?>
                        </button>
                    </div>
                    <!-- Show after Recaptcha login allow information -->

                    <!-- Recaptcha key exists hidden field -->
                    <input type="hidden" name="reacptcha_keys_exist" id="lwRecaptchaKeysExist" value="" />
                    <!-- Recaptcha key exists hidden field -->
                    <!-- Enable/Disable check box -->
                    <div id="lwReCaptchaLoginInputField" class="px-1">
                        <!-- /Recaptcha Client Secret -->
                        <!--Recaptcha Client Secret -->
                        <div class="mx-4">
                            <label for="lwReCaptchaClientSecret">
                                <?= __tr('Site Key') ?>
                            </label>
                            <input type="text" class="form-control form-control-user" name="recaptcha_site_key"
                                placeholder="<?= __tr('Recaptcha Site Key') ?>" id="lwReCaptchaClientSecret">
                        </div>
                        <div class="mx-4">
                            <label for="lwSecretKey">
                                <?= __tr('Secret Key') ?>
                            </label>
                            <input type="text" class="form-control form-control-user" name="recaptcha_secret_key"
                                id="lwSecretKey" placeholder="<?= __tr('Recaptcha Secret Key') ?>">
                        </div>
                    </div>
                </div>
                <!-- Enable/Disable check box -->
                <div class="form-group">
                    <!-- /recaptcha -->
                <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                </div>
                <!-- / Recaptcha login settings -->
            </div>
        </fieldset>
    </form>
    <form class="lw-ajax-form lw-form" method="post"
    action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
    <fieldset>
        <legend>{{ __tr('API Documentation URL for vendors') }}</legend>
        <div class="alert alert-light">
            {{  __tr('Default API Documentation URL') }}
            <div><a target="_blank" href="https://documenter.getpostman.com/view/17404097/2sA35D4hpx">https://documenter.getpostman.com/view/17404097/2sA35D4hpx</a></div>
        </div>
        <x-lw.input-field type="text" id="lwApiDocumentationUrl" data-form-group-class="" value="{{ getAppSettings('api_documentation_url') }}" :label="__tr('API Documentation URL')" name="api_documentation_url" required="true" />
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
        </div>
    </fieldset>
</form>
    <form class="lw-ajax-form lw-form" method="post"
    action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
    <fieldset>
        <legend>{{ __tr('Footer Code (Google Analytics etc)') }}</legend>
        <div class="alert alert-light">
            {{ __tr('Use script tag as required. You can use this place for various codes like Google Analytics etc') }}
        </div>
        <div class="mb-3 mb-sm-0">
            <label id="lwFooterCode">{{  __tr('For all the users') }} </label>
            <textarea rows="10" id="lwFooterCode" class="lw-form-field form-control" placeholder="{{ __tr('You can add your required js code etc here using script tags it will executed all the user pages') }}" name="page_footer_code_all">{!! getAppSettings('page_footer_code_all') !!}</textarea>
        </div>
        <div class="my-4 mb-sm-0">
            <label id="lwFooterCodeLoggedIn">{{  __tr('Restricted to logged in users') }} </label>
            <textarea rows="10" id="lwFooterCodeLoggedIn" class="lw-form-field form-control" placeholder="{{ __tr('You can add your required js code etc here using script tags it will only executed on logged in user pages') }}"  name="page_footer_code_logged_user_only">{!! getAppSettings('page_footer_code_logged_user_only') !!}</textarea>
        </div>
        <div class="form-group" name="footer_code">
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
        </div>
    </fieldset>
</form>
</section>


@push('appScripts')
<script>
    "use strict";
        function allowFunction() {
        var checkBox = document.getElementById("lwAllowRecaptcha");
        var text = document.getElementById("inputFieldShow");
        if (checkBox.checked == true) {
            text.style.display = "block";
        } else {
            text.style.display = "none";
        }
    }


    //recaptcha login js block start
    $(document).ready(function() {
        var allowReCaptchaLogin = $("#lwAllowRecaptcha").is(':checked');
        if (allowReCaptchaLogin) {
            $("#inputFieldShow").show()
        }
        //is true then disable input field
        if (!allowReCaptchaLogin) {
            $("#lwReCaptchaLoginInputField").addClass('lw-disabled-block-content');
            $('#lwAddReCaptchaKeys').attr("disabled", true);
        }

        //allow Recaptcha switch on change event
        $("#lwAllowRecaptcha").on('change', function(e) {
            allowReCaptchaLogin = $(this).is(":checked");

            //if condition false then add class
            if (!allowReCaptchaLogin) {
                $("#lwReCaptchaLoginInputField").addClass('lw-disabled-block-content');
                $('#lwAddReCaptchaKeys').attr("disabled", true);
            } else {
                $("#lwReCaptchaLoginInputField").removeClass('lw-disabled-block-content');
                $('#lwAddReCaptchaKeys').attr("disabled", false);
            }
        });

        /*********** Recaptcha Keys setting start here ***********/
        var isReCaptchaKeysInstalled = "<?= getAppSettings('enable_recaptcha') ?>",
          lwReCaptchaLoginInputField = $('#lwReCaptchaLoginInputField'),
          lwIsReCaptchaKeysExist = $('#lwIsReCaptchaKeysExist');

        // Check if test Recaptcha login keys are installed
        if (isReCaptchaKeysInstalled) {
            lwReCaptchaLoginInputField.hide();
            lwIsReCaptchaKeysExist.show();
        } else {
            lwIsReCaptchaKeysExist.hide();
        }
        // Update Recaptcha login checkout testing keys
        $('#lwAddReCaptchaKeys').click(function() {
            $("#lwRecaptchaKeysExist").val(0);
            lwReCaptchaLoginInputField.show();
            lwIsReCaptchaKeysExist.hide();
        });
        /*********** Recaptcha Keys setting end here ***********/
    });
    //Recaptcha login js block end

    //on integration setting success callback function
    function onIntegrationSettingCallback(responseData) {
        //check reaction code is 1 then reload view
        if (responseData.reaction == 1) {
            showConfirmation("{{ __tr('Settings Updated Successfully') }}", function() {
                __Utils.viewReload();
            }, {
                confirmButtonText: "{{ __tr('Reload Page') }}",
                type: "success"
            });
        }
    };
</script>
@endpush