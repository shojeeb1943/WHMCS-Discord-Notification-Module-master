<?php

/******************************************************************************
 * Copyright (c) William Beacroft 2023.                                       *
 *                                                                            *
 * @project WHMCS Discord Notifications                                       *
 * @author Bytesis (original module by William Beacroft)                      *
 * @site Bytesis                                                              *
 *                                                                            *
 ******************************************************************************/

namespace WHMCS\Module\Notification\Discord;

class Embed
{
    public $title = "";
    public $url = "";
    public $description = "";
    public $fields = NULL;
    public $timestamp = "";
    public $color = "";
    public $footer = array();

    public function title($title)
    {
        $this->title = trim($title);
        return $this;
    }

    public function url($url)
    {
        $this->url = trim($url);
        return $this;
    }

    public function description($description)
    {
        $this->description = trim($description);
        return $this;
    }

    public function timestamp($timestamp)
    {
        $this->timestamp = trim($timestamp);
        return $this;
    }

    public function color($color)
    {
        $this->color = $color;
        return $this;
    }

    public function footer($footertext, $icon = "")
    {
        $this->footer["text"] = trim($footertext);
        $this->footer["icon_url"] = trim($icon);
        return $this; 
    }

    public function addField(Field $field)
    {
        $this->fields[] = $field;
        return $this;
    }

    public function toArray()
    {
        $embed = [];

        if (!empty($this->title)) {
            $embed["title"] = $this->title;
        }
        if (!empty($this->url)) {
            $embed["url"] = $this->url;
        }
        if (!empty($this->description)) {
            $embed["description"] = $this->description;
        }
        if (!empty($this->timestamp)) {
            $embed["timestamp"] = $this->timestamp;
        }
        if ($this->color !== "" && $this->color !== null) { // 0 (000000) is a valid colour
            $embed["color"] = $this->color;
        }
        if (!empty($this->footer["text"])) {
            // empty icon_url is rejected by Discord
            $embed["footer"] = array_filter($this->footer, 'strlen');
        }

        if (!empty($this->fields)) {
            $embed["fields"] = array_map(function (Field $field) {
                return $field->toArray();
            }, $this->fields);
        }
        return $embed;
    }
}

?>