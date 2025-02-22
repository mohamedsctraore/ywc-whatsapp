<?php

/**
 * HomeEngine.php - Main component file
 *
 * This file is part of the Home component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Home;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Base\BaseMailer;
use App\Yantrana\Components\Home\Interfaces\HomeEngineInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class HomeEngine extends BaseEngine implements HomeEngineInterface
{
    public function __construct(BaseMailer $baseMailer)
    {
        $this->baseMailer = $baseMailer;
    }

    public function processContactEmail($inputData)
    {
        if (getAppSettings('enable_recaptcha') && ! $this->verifyRecaptcha($inputData)) {
            return $this->engineReaction(2, null, __tr('Invalid Recaptcha'));
        }
        //contact email data
        $emailData = [
            'userName' => $inputData['full_name'],
            'senderEmail' => $inputData['email'],
            'toEmail' => getAppSettings('contact_email'),
            'subject' => $inputData['subject'],
            'messageText' => $inputData['message'],
        ];
        if ($this->baseMailer->notifyAdmin($inputData['subject'], 'contact', $emailData, 2)) {
            return $this->engineReaction(1, null, __tr('Thank you for contacting us, your request has been submitted successfully, we will get back to you soon.'));
        }

        return $this->engineReaction(2, null, __tr('Fail to Send Mail'));
    }

    public function verifyRecaptcha($inputData)
    {
        $recaptcha_token = $inputData['g-recaptcha-response'];
        try {
            // Make a POST request to the reCAPTCHA verification endpoint
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => getAppSettings('recaptcha_secret_key'),
                'response' => $recaptcha_token, // The token generated by the reCAPTCHA client-side library
                'remoteip' => request()->ip(), // The IP address of the user submitting the reCAPTCHA
            ]);
            $responseData = $response->json();

            // Check if the verification was successful
            if (isset($responseData['success']) && $responseData['success'] == 1) {
                return true;
            } else {
                return false;
            }
        } catch (RequestException $e) {
            // An error occurred while making the request
            // Handle the error here
            return false;
        }
    }
}
