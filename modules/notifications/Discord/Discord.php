<?php

/******************************************************************************
 * Copyright (c) William Beacroft 2023.                                       *
 *                                                                            *
 * @project WHMCS Discord Notifications                                       *
 * @author William Beacroft                                                   *
 * @site https://billyab.co.uk                                                *
 *                                                                            *
 ******************************************************************************/

namespace WHMCS\Module\Notification\Discord;

use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Notification\Contracts\NotificationInterface;
use WHMCS\Config\Setting;
use WHMCS\Exception;

/**
 * Notification module for delivering notifications via Discord
 */
class Discord implements NotificationModuleInterface
{
    use DescriptionTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDisplayName('Discord')
            ->setLogoFileName('logo.png');
    }

    /**
     * Settings required for module configuration
     *
     * @return array
     */
    public function settings()
    {
        return [
            'webhookURL' => [
                'FriendlyName' => 'Discord Webhook URL',
                'Type' => 'text',
                'Description' => 'The Discord Webhook URL that notifications should be sent to.',
            ],
            'companyName' => [
                'FriendlyName' => 'Company Name (Optional)',
                'Type' => 'text',
                'Description' => 'This will be the name of the user that sends the message in the Discord channel.',
                'Placeholder' => Setting::getValue('CompanyName'),
            ],
            'discordColor' => [
                'FriendlyName' => 'Message Colour (Optional)',
                'Type' => 'text',
                'Description' => 'This is the side line colour for the message. The color code format within this script is standard hex. Exclude the beginning # character if one is present.',
                'Placeholder' => '73CB0B',
            ],
            'discordGroupID' => [
                'FriendlyName' => 'Notification Role ID (Optional)',
                'Type' => 'text',
                'Description' => 'If you\'d like to have a specific group pinged on each message, please place the ID here. An example of a group ID is: 343029528563548162 (Only include the numerical ID and no other formatting)',
            ],
            'discordWebHookAvatar' => [
                'FriendlyName' => 'Webhook Avatar Image (Optional)',
                'Type' => 'text',
                'Description' => 'Your desired Webhook Avatar. Please make sure you enter a direct link to the image (E.G. https://example.com/iownpaypal.png )',
            ],
        ];
    }

    /**
     * Validate settings for notification module
     *
     * This method will be invoked prior to saving any settings via the UI.
     *
     * @param array $settings Settings present in configuration modal
     *
     * @return boolean
     * @throws Exception webhookURL value is missing
     */
    public function testConnection($settings): bool
    {
        $webhookURL = $settings['webhookURL'] ?? '';

        if (!filter_var(trim((string)$webhookURL), FILTER_VALIDATE_URL)) {
            throw new Exception("You must provide a valid webhook URL");
        }
        return true;
    }

    /**
     * The individual customisable settings for a notification.
     *
     * @return array
     */
    public function notificationSettings()
    {
        return [
            'message' => [
                'FriendlyName' => 'Customise Message (Optional)',
                'Type' => 'text',
                'Description' => 'Allows you to customise the primary display message shown in the notification.',
            ],
            'webhookURL' => [
                'FriendlyName' => 'Discord Webhook URL (Optional)',
                'Type' => 'text',
                'Description' => 'Override the Discord Webhook URL for this notification to send to a different channel.',
            ],
            'discordColor' => [
                'FriendlyName' => 'Message Colour (Optional)',
                'Type' => 'text',
                'Description' => 'Override the side line colour for the message. The color code format within this script is standard hex. Exclude the beginning # character if one is present.',
                'Placeholder' => '73CB0B',
            ],
            'discordGroupID' => [
                'FriendlyName' => 'Notification Role ID (Optional)',
                'Type' => 'text',
                'Description' => 'If you\'d like to have a specific group pinged for this message type, please place the ID here (this is in addition to the module setting). An example of a group ID is: 343029528563548162 (Only include the numerical ID and no other formatting)',
            ],
        ];
    }
    
    /**
     * The option values available for a 'dynamic' Type notification setting
     */
    public function getDynamicField($fieldName, $settings)
    {
        return [];
    }

    /**
     * Deliver notification
     *
     * This method is invoked when rule criteria are met.
     *
     * @param NotificationInterface $notification A notification to send
     * @param array $moduleSettings Configured settings of the notification module
     * @param array $notificationSettings Configured notification settings set by the triggered rule
     * 
     */
    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
        $messageBody = !empty($notificationSettings["message"]) ? $notificationSettings["message"] : $notification->getMessage();
        $webhookURL = trim(!empty($notificationSettings["webhookURL"]) ? $notificationSettings["webhookURL"] : ($moduleSettings["webhookURL"] ?? ''));
        if (!filter_var($webhookURL, FILTER_VALIDATE_URL)) {
            throw new Exception("Discord webhook URL is missing or invalid");
        }

        // Accept "#73CB0B" / "73CB0B"; ignore anything that isn't 6 hex digits.
        $discordColor = 7588619;
        foreach ([$notificationSettings["discordColor"] ?? '', $moduleSettings["discordColor"] ?? ''] as $hex) {
            $hex = ltrim(trim($hex), '#');
            if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                $discordColor = hexdec($hex);
                break;
            }
        }

        // Discord limits: title 256, description 4096, 25 fields, name 256, value 1024, 6000 chars total.
        $cut = function ($s, $n) {
            $s = (string)$s;
            return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . "\u{2026}" : $s;
        };
        $validUrl = function ($u) {
            $u = trim((string)$u);
            return filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u) ? $u : '';
        };
        $embed = (new Embed())->title($cut(\WHMCS\Input\Sanitize::decode($notification->getTitle()), 256))
            ->url($validUrl($notification->getUrl()))
            ->description($cut($messageBody, 4096))
            ->timestamp(date(\DateTime::ATOM))
            ->color($discordColor)
            ->footer("WHMCS Discord module by BillyAB");
        $total = mb_strlen($embed->title) + mb_strlen($embed->description) + 40; // +footer
        $count = 0;
        foreach ($notification->getAttributes() as $attribute) {
            $value = (string)$attribute->getValue();
            $link = $validUrl($attribute->getUrl());
            if ($link !== '' && mb_strlen($link) <= 800) {
                // cut the label first so the closing "](url)" is never truncated
                $value = "[" . $cut($value, 1024 - mb_strlen($link) - 4) . "](" . $link . ")";
            }
            $name = $cut($attribute->getLabel(), 256);
            $value = $cut($value, 1024);
            // Discord rejects the whole message (400) on an empty name/value.
            if (trim($name) === '' || trim($value) === '') {
                continue;
            }
            $total += mb_strlen($name) + mb_strlen($value);
            if ($count >= 25 || $total > 5900) {
                break;
            }
            $count++;
            $embed->addField((new Field())->name($name)->value($value));
        }

        if ($embed->title === '' && $embed->description === '' && $count === 0) {
            $embed->description("(empty notification)"); // Discord rejects an empty embed
        }

        // array_unique: same role in module + rule settings must not ping twice
        // digits only: blocks injecting "@everyone" or broken markup via the setting
        $roles = array_unique(array_filter(array_map('trim', [$moduleSettings["discordGroupID"] ?? '', $notificationSettings["discordGroupID"] ?? '']), 'ctype_digit'));
        $mentions = implode('', array_map(function ($id) { return "<@&" . $id . ">"; }, $roles));

        // Discord 400s on webhook usernames containing "discord" or "clyde"; fall back to webhook default.
        $username = $cut(trim((string)($moduleSettings["companyName"] ?? '')), 80);
        if (preg_match('/discord|clyde/i', $username)) {
            $username = '';
        }

        $message = (new Message())->content($mentions)
            ->username($username)
            ->avatarUrl($validUrl($moduleSettings["discordWebHookAvatar"] ?? ''))
            ->allowedRoles(array_values($roles))
            ->embed($embed);
        $payload = $message->toArray();

        // Duplicate guard: identical payload to the same webhook within 30s is sent once.
        $guard = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'whmcs_discord_' . md5($webhookURL . json_encode(array_map(function ($e) { unset($e['timestamp']); return $e; }, $payload['embeds'] ?? [])) . ($payload['content'] ?? ''));
        if (is_file($guard) && time() - filemtime($guard) < 30) {
            logModuleCall('discord', 'Duplicate Notification Skipped', $payload, '', '');
            return;
        }
        @touch($guard);
        try {
            $this->call($moduleSettings, $webhookURL, $payload);
        } catch (\Exception $e) {
            @unlink($guard); // failed send must stay retryable
            throw $e;
        }
    }

    protected function call(array $settings, $url, array $postdata = array())
    {
        $json = json_encode($postdata, JSON_INVALID_UTF8_SUBSTITUTE);
        $attempt = 0;
        do {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => 1,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => 1,
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $decoded = json_decode((string)$response);

            // 429: Discord did NOT deliver it, so one bounded retry cannot duplicate.
            $retry = $code == 429 && $attempt++ < 1;
            if ($retry) {
                usleep((int)(min(5, max(0.5, (float)($decoded->retry_after ?? 1))) * 1000000));
            }
        } while ($retry);

        // Any 2xx = accepted. Never retry on timeout/5xx: the message may already be delivered.
        if ($code < 200 || $code >= 300) {
            logModuleCall('discord', 'Notification Sending Failed', $postdata, $response ?: $curlError, $decoded);
            throw new Exception("Discord error (HTTP $code): " . ($response ?: $curlError));
        }
        logModuleCall('discord', 'Notification Successfully Sent', $postdata, $response, $decoded);

        return $decoded;
    }

}
