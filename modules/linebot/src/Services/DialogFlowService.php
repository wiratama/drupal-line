<?php

namespace  Drupal\linebot\Services;

use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;

class DialogFlowService {
    public function getDialogFlowIntents($params = array()) {
        if(!empty($params)) {
            $projectId = 'wa-bot1-elrggg';
            $languageCode = 'en';
            $text = $params['text'];

            // new session
            $creds_path = getcwd().'/modules/linebot/config/creds/creds_just_coffe_shop_bot.json';
            $creds = array('credentials' => $creds_path);
            $sessionsClient = new SessionsClient($creds);
            $session = $sessionsClient->sessionName($projectId, uniqid());
        
            $textInput = new TextInput();
            $textInput->setText($text);
            $textInput->setLanguageCode($languageCode);
        
            $queryInput = new QueryInput();
            $queryInput->setText($textInput);
        
            // get response and relevant info
            $response = $sessionsClient->detectIntent($session, $queryInput);
            $queryResult = $response->getQueryResult();
            $queryText = $queryResult->getQueryText();
            $intent = $queryResult->getIntent();
            $displayName = $intent->getDisplayName();
            $confidence = $queryResult->getIntentDetectionConfidence();
            $fulfilmentText = $queryResult->getFulfillmentText();
        
            $sessionsClient->close();

            $data_return = [
                'intentName'     => $displayName,
                'responseText'   => $fulfilmentText,
            ];

            return $data_return;
        }
        return false;
    }
}