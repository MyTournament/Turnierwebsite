<?php
$name = $_POST['name'];
$email = $_POST['email'];
$message = $_POST['message'];

include_once 'send_mail.php';

$SECRET_KEY = "f3591a3b-4fdc-490c-99b0-a0b84ba5d938";    # replace with your secret key
$VERIFY_URL = "https://hcaptcha.com/siteverify";
# Retrieve token from post data with key 'h-captcha-response'.
$token = request.POST_DATA['h-captcha-response'];
# Build payload with secret key and token.
$data = { 'secret': $SECRET_KEY, 'response': $token };
# Make POST request with data payload to hCaptcha API endpoint.
$response = http.post(url=$VERIFY_URL, data=$data);
# Parse JSON from response. Check for success or error codes.
$response_json = JSON.parse(response.content);
$success = response_json['success'];
/*
{
    "success": true|false,     // is the passcode valid, and does it meet security criteria you specified, e.g. sitekey?
    "challenge_ts": timestamp, // timestamp of the challenge (ISO format yyyy-MM-dd'T'HH:mm:ssZZ)
    "hostname": string,        // the hostname of the site where the challenge was solved
    "credit": true|false,      // optional: whether the response will be credited
    "error-codes": [...]       // optional: any error codes
    "score": float,            // ENTERPRISE feature: a score denoting malicious activity.
    "score_reason": [...]      // ENTERPRISE feature: reason(s) for score.
 }
*/
if($success){
    $action = $_POST['action'];
    if($action == "send_message"){
        //PER MAIL VERSENDEN
        mail_att("kummerkasten@blankiball.de", $email, $name." hat das Kontaktformular benutzt", $message);
    }
}

header("Location: /#kontakt_success");
?>