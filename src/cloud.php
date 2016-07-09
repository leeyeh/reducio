<?php

use \LeanCloud\Engine\Cloud;

/*
 * Define cloud functions and hooks on LeanCloud
 */

Cloud::beforeSave("Url", function($obj) {
    if (is_null($obj->get("short"))) {
        $obj->set("short", base64_encode(random_bytes(6)));
    }
    return $obj;
});
