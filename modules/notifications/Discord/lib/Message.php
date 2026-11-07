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

class Message
{
    public $content = "";
    public $username = "";
    public $avatarUrl = "";
    public $embeds = array();
    public $allowedRoles = array();

    public function content($content)
    {
        $this->content = trim($content);
        return $this;
    }

    public function username($username)
    {
        $this->username = trim($username);
        return $this;
    }

    public function avatarUrl($avatarUrl)
    {
        $this->avatarUrl = trim($avatarUrl);
        return $this;
    }

    public function allowedRoles(array $roles)
    {
        $this->allowedRoles = $roles;
        return $this;
    }

    public function embed($embed)
    {
        $this->embeds[] = $embed;
        return $this;
    }

    public function toArray()
    {

        $message = array();

        if (!empty($this->content)) {
            $message["content"] = $this->content;
        }    
        if (!empty($this->avatarUrl)) {
            $message["avatar_url"] = $this->avatarUrl;
        }
        if (!empty($this->username)) {
            $message["username"] = $this->username;
        }
        // only the configured roles may ping; user-supplied text can never mention anyone
        $message["allowed_mentions"] = ["parse" => [], "roles" => $this->allowedRoles];
        if (!empty($this->embeds)) {
            // serialise via toArray() so empty props (url "", color "") aren't sent
            $message["embeds"] = array_map(function ($e) {
                return $e instanceof Embed ? $e->toArray() : $e;
            }, $this->embeds);
        }

        return $message;
    }
}