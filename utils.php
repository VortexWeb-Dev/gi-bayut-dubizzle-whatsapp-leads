<?php

require_once __DIR__ . "/crest/crest.php";

// Formats the comments for the call
function formatComments(array $data): string
{
    if ($data['type'] && $data['type'] == 'lead_created') {
        return formatLeadComments($data);
    }

    if (in_array($data['eventType'], ['smsEvent', 'aiTranscriptionSummary'])) {
        return "No data available for " . ($data['eventType'] === 'smsEvent' ? 'SMS events' : 'AI transcription summary') . ".";
    }

    $output = [];

    $output[] = "=== Call Information ===";
    $output[] = "Call ID: " . $data['callId'];
    $output[] = "Call Type: " . $data['type'];
    $output[] = "Event Type: " . $data['eventType'];

    if (isset($data['recordName'])) {
        $output[] = "Call Recording URL: " . $data['recordName'];
    }
    $output[] = "";

    $output[] = "=== Client Details ===";
    $output[] = "Client Phone: " . $data['clientPhone'];
    $output[] = "Line Number: " . $data['lineNumber'];
    $output[] = "";

    $output[] = "=== Agent Details ===";
    $output[] = "Brightcall User ID: " . $data['userId'];

    if (isset($data['agentId'])) {
        $output[] = "Brightcall Agent ID: " . $data['agentId'];
    }

    if (isset($data['agentName'])) {
        $output[] = "Agent Name: " . $data['agentName'];
    }

    if (isset($data['agentEmail'])) {
        $output[] = "Agent Email: " . $data['agentEmail'];
    }
    $output[] = "";

    $output[] = "=== Call Timing ===";

    if ($data['eventType'] === 'callEnded') {
        $output[] = "Call Start Time: " . tsToHuman($data['startTimestampMs']);

        if (isset($data['answerTimestampMs'])) {
            $output[] = "Call Answer Time: " . tsToHuman($data['answerTimestampMs']);
        }

        $output[] = "Call End Time: " . tsToHuman($data['endTimestampMs']);
    } else {
        $output[] = "Call Start Time: " . tsToHuman($data['timestampMs']);
    }

    if ($data['eventType'] === 'webphoneSummary') {
        $output[] = "";
        $output[] = "=== Lead Details ===";
        $output[] = "Goal: " . $data['goal'];
        $output[] = "Goal Type: " . $data['goalType'];
    }

    return implode("\n", $output);
}

function formatLeadComments(array $data): string
{
    $output = [];

    $output[] = "=== Lead Information ===";
    $output[] = "Call ID: " . $data['call_id'];
    $output[] = "Event Type: " . $data['type'];
    $output[] = "Lead ID: " . $data['lead']['lead_id'];
    $output[] = "Lead Source: " . $data['lead']['custom_params']['api_source'];
    $output[] = "";

    $output[] = "=== Client Details ===";
    $output[] = "Client Name: " . $data['lead']['custom_params']['lc_param_name'];
    $output[] = "Client Phone: " . $data['lead']['lead_phone'];
    $output[] = "Client Email: " . strtolower($data['lead']['custom_params']['lc_param_email']);
    $output[] = "";

    $output[] = "=== Lead Timing ===";
    $output[] = "Created Time: " . isoToHuman($data['lead']['time_created_iso_string']);

    return implode("\n", $output);
}

// Gets the responsible person ID from the agent email
function getResponsiblePersonId(string $agentEmail): ?int
{
    $responsiblePersonId = null;

    $response = CRest::call('user.get', [
        'filter' => [
            'EMAIL' => $agentEmail
        ]
    ]);

    if (isset($response['result'][0]['ID'])) {
        $responsiblePersonId = $response['result'][0]['ID'];
    }

    return $responsiblePersonId;
}

// Converts ISO 8601 string to human readable format
function isoToHuman($isoString)
{
    $ts = (new DateTime($isoString))->getTimestamp();
    return tsToHuman($ts * 1000);
}

// Converts timestamp in milliseconds to ISO 8601 format
function tsToIso($tsMs, $tz = 'Asia/Dubai')
{
    return (new DateTime("@" . ($tsMs / 1000)))->setTimezone(new DateTimeZone($tz))->format('Y-m-d\TH:i:sP');
}

// Converts timestamp in milliseconds to human readable format
function tsToHuman($tsMs, $tz = 'Asia/Dubai')
{
    $date = (new DateTime("@" . ($tsMs / 1000)))->setTimezone(new DateTimeZone($tz));
    $now = new DateTime('now', new DateTimeZone($tz));
    $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');

    $dateFormatted = $date->format('Y-m-d');
    $timeFormatted = $date->format('h:i A');

    if ($dateFormatted === $now->format('Y-m-d')) {
        return "Today at $timeFormatted";
    } elseif ($dateFormatted === $yesterday) {
        return "Yesterday at $timeFormatted";
    } else {
        return $date->format('F j, Y \a\t h:i A');
    }
}

// Converts time in HH:MM:SS format to seconds
function timeToSec($time)
{
    $time = explode(':', $time);
    return $time[0] * 3600 + $time[1] * 60 + $time[2];
}
