<?php
require_once __DIR__ . "/../crest/crest.php";
require_once __DIR__ . "/../utils.php";

define('CONFIG', require_once __DIR__ . '/../config.php');

class WebhookController
{
    private const ALLOWED_ROUTES = [
        'bayut-whatsapp' => 'handleBayutWhatsapp',
        'dubizzle-whatsapp' => 'handleDubizzleWhatsapp',
    ];

    private LoggerController $logger;
    private BitrixController $bitrix;

    public function __construct()
    {
        $this->logger = new LoggerController();
        $this->bitrix = new BitrixController();
    }

    // Handles incoming webhooks
    public function handleRequest(string $route): void
    {
        try {
            $this->logger->logRequest($route);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed. Only POST is accepted.'
                ]);
            }

            if (!array_key_exists($route, self::ALLOWED_ROUTES)) {
                $this->sendResponse(404, [
                    'error' => 'Resource not found'
                ]);
            }

            $handlerMethod = self::ALLOWED_ROUTES[$route];

            $data = $this->parseRequestData();
            if ($data === null) {
                $this->sendResponse(400, [
                    'error' => 'Invalid JSON data'
                ]);
            }

            $this->$handlerMethod($data);
        } catch (Throwable $e) {
            $this->logger->logError('Error processing request', $e);
            $this->sendResponse(500, [
                'error' => 'Internal server error'
            ]);
        }
    }

    // Parses incoming JSON data
    private function parseRequestData(): ?array
    {
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        return $data;
    }

    // Sends response back to the webhook
    private function sendResponse(int $statusCode, array $data): void
    {
        header("Content-Type: application/json");
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    // Handles bayut-whatsapp webhook event
    public function handleBayutWhatsapp(array $data): void
    {
        $this->logger->logWebhook('bayut-whatsapp', $data);
        // Process lead data
        $this->sendResponse(200, [
            'message' => 'Lead data processed successfully',
        ]);
    }

    // Handles dubizzle-whatsapp webhook event
    public function handleDubizzleWhatsapp(array $data): void
    {
        $this->logger->logWebhook('dubizzle-whatsapp', $data);

        $assignedById = !empty($data['listing']['reference']) ? getResponsiblePerson($data['listing']['reference'], 'reference') : CONFIG['DEFAULT_RESPONSIBLE_PERSON_ID'];
        $title = "Dubizzle - WhatsApp - " . ($data['listing']['reference'] !== "" ? $data['listing']['reference'] : 'No reference');

        $contactId = $this->bitrix->createContact([
            'NAME' => $data['enquirer']['name'] ?? $title,
            'PHONE' => [
                [
                    'VALUE' => $data['enquirer']['phone_number'],
                    'VALUE_TYPE' => 'WORK',
                ]
            ],
            'SOURCE_ID' => CONFIG['DUBIZZLE_WHATSAPP'],
            'ASSIGNED_BY_ID' => $assignedById
        ]);

        $fields = [
            'TITLE' => $title,
            'CATEGORY_ID' => CONFIG['SECONDARY_PIPELINE_ID'],
            'ASSIGNED_BY_ID' => $assignedById,
            'SOURCE_ID' => CONFIG['DUBIZZLE_WHATSAPP'],
            'UF_CRM_1721198189214' => $data['enquirer']['name'] ?? 'Unknown',
            'UF_CRM_1736406984' => $data['enquirer']['phone_number'],
            'UF_CRM_1739873044322' => $data['enquirer']['contact_link'],
            'UF_CRM_1739890146108' => $data['listing']['reference'],
            'UF_CRM_1739945676' => $data['listing']['url'],
            'OPPORTUNITY' => getPropertyPrice($data['listing']['reference']) ?? '',
            'CONTACT_ID' => $contactId,
        ];

        $leadId = $this->bitrix->addLead($fields);

        $this->sendResponse(200, [
            'message' => 'Lead data processed successfully and lead created with ID: ' . $leadId,
        ]);
    }
}
