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
        'hd'   => $hd,       // 1 for HD, 0 for normal quality
    ];

    // Construct the API URL with parameters
    $api_url = $path_api_get_video_no_watermark . '?' . http_build_query($params);

    // Retrieve the API response using file_get_contents (without using cURL)
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
function sendMessage($chat_id, $text, $reply_to_msg_id = null)
{
    $url = API_URL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text'    => $text
    ];
    if ($reply_to_msg_id !== null) {
        $data['reply_to_message_id'] = $reply_to_msg_id;
    }
    file_get_contents($url . "?" . http_build_query($data));
}

/**
 * Send a chat action to display a status in Telegram's action bar.
 *
 * @param int    $chat_id The recipient chat ID.
 * @param string $action  The action to display (e.g., "upload_video").
 */
function sendChatAction($chat_id, $action)
{
    $url = API_URL . "sendChatAction";
    $data = [
        'chat_id' => $chat_id,
        'action'  => $action
    ];
    file_get_contents($url . "?" . http_build_query($data));
}
/**
 * Send an audio file via Telegram.
 *
 * @param int    $chat_id         The recipient chat ID.
 * @param string $audio_url       The audio URL to send.
 * @param int    $reply_to_msg_id (Optional) Message ID to which the audio reply is attached.
 * @param string $caption         (Optional) Caption for the audio.
 */
function sendAudio($chat_id, $audio_url, $reply_to_msg_id = null, $caption = '')
{
    $url = API_URL . "sendAudio";
    $data = [
        'chat_id' => $chat_id,
        'audio'   => $audio_url,
        'caption' => $caption
    ];
    // If a reply_to_message_id is provided, set it
    //if ($reply_to_msg_id !== null) {
    //     $data['reply_to_message_id'] = $reply_to_msg_id;
    // }
    file_get_contents($url . "?" . http_build_query($data));
}

/**
 * Send a video via Telegram.
 *
 * @param int    $chat_id   The recipient chat ID.
 * @param string $video_url The video URL to send.
 * @param string $caption   (Optional) Caption for the video.
 */
function sendVideo($chat_id, $video_url, $caption = '', $reply_to_msg_id = null)
{
    $url = API_URL . "sendVideo";
    $data = [
        'chat_id' => $chat_id,
        'video'   => $video_url,
        'caption' => $caption
    ];
    if ($reply_to_msg_id !== null) {
        $data['reply_to_message_id'] = $reply_to_msg_id;
    }
    // Send the video using file_get_contents
    file_get_contents($url . "?" . http_build_query($data));
}

/**
 * (Hypothetical) React to a user's message.
 *
 * This function uses a theoretical endpoint "sendReaction"—adjust if you have access to an API version
 * that supports message reactions. Otherwise, this is just a placeholder.
 *
 * @param int    $chat_id   The chat ID.
 * @param int    $message_id The ID of the message to react to.
 * @param string $reaction  The reaction emoji (e.g., "❤️").
 */
function sendReaction($chat_id, $message_id, $reaction)
{
    $url = API_URL . "sendReaction";
    $data = [
        'chat_id'   => $chat_id,
        'message_id' => $message_id,
        'reaction'  => $reaction
    ];
    file_get_contents($url . "?" . http_build_query($data));
}


// Process the incoming Telegram update
$update = json_decode(file_get_contents("php://input"), true);

// Exit if the update format is invalid or missing a message
if (!$update || !isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$message_text = $update['message']['text'] ?? '';
$message_id = $update['message']['message_id'];

// Proceed only if the message contains text
if ($message_text) {
    // Check if the message likely contains a TikTok URL
    if (stripos($message_text, 'tiktok') !== false) {
        // Retrieve the video URL from the TikWM API.
        // The third parameter '1' indicates HD mode; change to '0' for normal quality.
        $result = getVideoNoWaterMark("get", $message_text, 1);
        sendChatAction($chat_id, "upload_video");
        sendReaction($chat_id, $message_id, "❤️");
        // Verify the API call was successful and the expected video URL is present
        if (
            isset($result['msg']) && $result['msg'] == 'success' &&
            isset($result['data']['play']) && !empty($result['data']['play'])
        ) {

            // Extract the video URL from the API response
            if (isset($result['data']['size']) && $result['data']['size'] > (20 * 1024 * 1024)) {
                // If the video size exceeds 20 MB, inform the user
                sendMessage($chat_id, "The video exceeds 20 MB and cannot be sent.", $message_id);
            } else {
                $video_url = $result['data']['play'];
                sendVideo($chat_id, $video_url, $result['data']['title'], $message_id);
            }
            $audio_url = $result['data']['music'];
            // Send the audio as a reply to the user's original message
            sendAudio($chat_id, $audio_url, $message_id, $result['data']['music_info']['title']);
        } else {
            // In case of error or unexpected API response structure, notify the user
            sendMessage($chat_id, "Sorry, I couldn't download the video. Please verify the URL or try again later.");
        }
    } else {
        sendMessage($chat_id, "Please send a valid TikTok video URL.");
    }
}
