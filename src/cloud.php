<?php

use \LeanCloud\Engine\Cloud;

function getRandomId() {
    return substr(preg_replace('/[^0-9a-zA-Z]/', '', base64_encode(random_bytes(12))), 0, 7);
};

/*
 * Define cloud functions and hooks on LeanCloud
 */

Cloud::beforeSave("Url", function($obj) {
    if (is_null($obj->get("short"))) {
        $obj->set("short", getRandomId());
    }
    return $obj;
});
