<?php

// Use PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Load the composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Initialize the logger
Logger::configure("./config/logger.xml");
$logger = Logger::getLogger("default");
$logger->info("New query : " . $_SERVER['REQUEST_URI'] . " from " . $_SERVER['REMOTE_ADDR']);

// Load the configuration file
$config = include('config/config.php');

// We enable errors in test mode
if ($config['test_mode']) {
    ini_set('display_startup_errors', 1);
    ini_set('display_errors', 1);
    error_reporting(-1);
}

// Set up the default query path
$uri = $config['path']."/v3/".$config['domain'];

// We return only JSON content
header("Content-Type: application/json");

// If we have to send a message
if ($_SERVER['REQUEST_URI'] == $uri."/messages"){

    $logger->info('New message received');

    // Check the email id
    $id = $_POST["v:email-id"];
    if (empty($id)) {
        $error = "Missing 'id'";
    }

    // Check the reply to
    $reply_to = $_POST["h:Reply-To"];
    if (empty($reply_to)) {
        $error = "Missing 'reply_to' address";
    }

    // Check the from
    $from =  $_POST["from"];
    if (empty($from)) {
        $error = "Missing 'from' address";
    }

    // Check the subject
    $subject = $_POST["subject"];
    if (empty($subject)) {
        $error = "Missing 'subject' content";
    }

    // Check the html content
    $html = $_POST["html"];
    if (empty($html)) {
        $error = "Missing 'html' content";
    }

    // Check the text content
    $text = $_POST["text"];
    if (empty($text)) {
        $error = "Missing 'text' content";
    }

    // Check the recipient
    $recipients_json= $_POST["recipient-variables"];

    if (empty($recipients_json)) {
        $error = "Missing 'recipient' addresses";
    } else {
        $recipients = json_decode($recipients_json, true);
    }

     // If there is an error, log it and return
    if (!empty($error)) {
        $logger->error($error);
        throw new Exception($error);
    }

    // Send the email
    sendMessage(
        $id,
        $from, 
        $reply_to, 
        $subject,
        $html,
        $text,
        $recipients);

    // Success
    http_response_code(200);
    $response = array(
            "message" => "Queued. Thank you.",
            "id" => "<'.$id.'@mailknife.org>"
    ); 
                      
    echo json_encode($response);
    $logger->info('Finish to send : ' . $_POST['v:email-id']);

} else {
    $error = "Unsupported query";
    $logger->error($error . " : " . $_SERVER['REQUEST_URI']);
    http_response_code(400);
    $response = array(
            "message" => $error
    );
    echo json_encode($response);
}

/**
 * Send the email to SMTP Server
 */
function sendMessage($id, $from, $reply_to, $subject, $html, $text, $recipients){

    global $logger, $config;

    $logger->info('Preparing to send a new message : ' . $id);

    $mail = getMailerInstance();

    // Set the mail content
    $mail->setFrom($reply_to);
    //$mail->addReplyTo($reply_to);
    $mail->Subject = $subject;

    // For each recipient we send a new email
    foreach ($recipients as $email => $recipient_array) {

        // If the test mode is disable or the target email is the test email
        if (! $config['test_mode'] 
            || ($config['test_mode'] && $email == $config['test_email'])){

            $logger->info('Sending email to  : ' . $email);
            $mail->addAddress($email);

            // We search and replace recipient variables
            foreach ($recipient_array as $key => $value) {
                $html = str_replace("%recipient." . $key . "%", $value, $html);
                $text = str_replace("%recipient." . $key . "%", $value, $text);
            }
            
            // Let's define the mail content
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;

            // Send the email
            if(!$mail->send()){
                $logger->error($mail->ErrorInfo);
                return false;
            }else{
                $logger->info('Message correctly sent : ' . $id);
                return true;
            }

            $mail->clearAddresses();
        }
    }
    
}

/**
 * Return a PhpMailer instance
 */
function getMailerInstance(){

    global $config;
    global $logger;
    
    $logger->debug("Instanciating a new PhpMailer instance");

    $mail = new PHPMailer();
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $config['smtp']['port'];
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;

    $logger->info("SMTP connection established");

    return  $mail;
}
