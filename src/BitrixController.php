<?php

require_once __DIR__ . '/../crest/crest.php';

class BitrixController
{
    public function addLead(array $leadData): ?int
    {
        if (empty($leadData['TITLE'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Missing required lead field: TITLE']);
            exit;
        }

        $result = CRest::call('crm.deal.add', [
            'fields' => $leadData,
            'params' => ["REGISTER_SONET_EVENT" => "Y"]
        ]);

        if (isset($result['result'])) {
            return $result['result'];
        } else {
            return null;
        }
        exit;
    }

    public function createContact(array $contactData): ?int
    {
        $result = CRest::call('crm.contact.add', [
            'fields' => $contactData,
            'params' => ["REGISTER_SONET_EVENT" => "Y"]
        ]);

        if (isset($result['result'])) {
            return $result['result'];
        } else {
            return null;
        }
        exit;
    }
}
