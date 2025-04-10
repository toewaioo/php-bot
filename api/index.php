<?php
// Set your Telegram Bot API token
define('BOT_TOKEN', '6976526108:AAFi1TIAdLPg1n9wz-bWH89uUKE_1GWkbts');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// TikWM API endpoint for downloading videos without watermark
$path_api_get_video_no_watermark = "https://tikwm.com/api";

/**
 * Retrieve the TikTok video URL (without watermark) using the TikWM API.
 *
 * @param string $method     API method or parameter (e.g., "get")
 * @param string $tiktok_url TikTok video URL provided by the user.
 * @param int    $hd         1 for HD, 0 for normal quality.
 *
 * @return array             Returns the decoded JSON response from the API.
 */
function getVideoNoWaterMark(string $method, string $tiktok_url, int $hd = 1): array
{
    global $path_api_get_video_no_watermark;

    // Build query parameters for the API call
    $params = [
        'url'  => $tiktok_url,
        'hd'   => $hd,       // 1 for HD, 0 for normal
        'type' => $method    // API method parameter (e.g., "get")
    ];

    // Construct the API URL with parameters
    $api_url = $path_api_get_video_no_watermark . '?' . http_build_query($params);

    // Retrieve the response using file_get_contents
    $response = file_get_contents($api_url);
    if ($response === false) {
        return ['error' => 'Failed to retrieve data from API'];
    }

    // Decode JSON response into an associative array
    $data = json_decode($response, true);
    return $data;
}

/**
 * Send a text message via Telegram.
 *
 * @param int    $chat_id The recipient chat ID.
 * @param string $text    The text message to send.
 */
function sendMessage($chat_id, $text)
{
    $url = API_URL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text'    => $text
    ];
    // Using file_get_contents for sending the message request
    file_get_contents($url . "?" . http_build_query($data));
}

/**
 * Send a video message via Telegram.
 *
 * @param int    $chat_id   The recipient chat ID.
 * @param string $video_url The video URL to send.
 * @param string $caption   (Optional) Caption for the video.
 */
function sendVideo($chat_id, $video_url, $caption = '')
{
    $url = API_URL . "sendVideo";
    $data = [
        'chat_id' => $chat_id,
        'video'   => $video_url,
        'caption' => $caption
    ];
    file_get_contents($url . "?" . http_build_query($data));
}

/**
 * Main logic to process incoming Telegram updates.
 */
$update = json_decode(file_get_contents("php://input"), true);

// Validate the incoming update structure
if (!$update || !isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$message_text = $update['message']['text'] ?? '';

// Process the message if it contains text
if ($message_text) {
    // Check if the message includes "tiktok" (case insensitive)
    if (stripos($message_text, 'tiktok') !== false) {
        // Retrieve the video URL from TikWM API.
        // Change the third parameter to 0 for normal quality if needed.
        $result = getVideoNoWaterMark("get", $message_text, 1);

        // Check if the API response contains the expected video URL key
        if (isset($result['video']) && !empty($result['video'])) {
            sendMessage(($chat_id), "Downloading your TikTok video...");
            // Send the video to the user
           // sendVideo($chat_id, $result['video'], "Here is your TikTok video without watermark!");
        } else {
            sendMessage($chat_id, "Sorry, I couldn't download the video. Please verify the URL or try again later.");
        }
    } else {
        sendMessage($chat_id, "Please send a valid TikTok video URL.");
    }
}
