<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }


if(!defined('FILTER_INITIALIZED')) {
    define('FILTER_INITIALIZED', true);
} else {
    die('<h1>ACCESS Exception :: filter re-execution blocked</h1>');
}


################################################
################################################
################################################


$methods = (string)$_SERVER['REQUEST_METHOD'];


$_REQUEST = [];
$_ENV = [];


# $vars_dl = [];

if(in_array($methods, ['POST', 'GET'], true)) {

    switch( $methods ) {

        case 'POST':

            $safePost = filter_input_array( INPUT_POST, [
                #"pagedel"   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

                "textedit"  => FILTER_DEFAULT,
                # "pageaddr"  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "h"         => FILTER_VALIDATE_INT,
                "title"     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

                "pgcommnum" => FILTER_VALIDATE_INT,
                "commpost"  => FILTER_DEFAULT,
                "commaddr"  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "repcommid" => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                # "email"     => FILTER_SANITIZE_EMAIL,
                "visitor"   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "captcha"   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "commpage"  => FILTER_VALIDATE_INT,

                "password"  => FILTER_DEFAULT,
                "username"  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

                "imgup" => FILTER_VALIDATE_INT,

                "fuptrigger" => FILTER_VALIDATE_INT,

                /* "fpgnum" => FILTER_VALIDATE_INT, */

                "registerp" => FILTER_VALIDATE_INT,
                "rusername" => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "rpassword1" => FILTER_DEFAULT,
                "rpassword2" => FILTER_DEFAULT,

                "selected_template"     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

                "permalink" => FILTER_VALIDATE_INT,

            ] ) ?? [];

            break;

        case 'GET':

            $safeGet = filter_input_array(INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? [];

            break;
    }

} else {

    die('<h1>ACCESS Exception :: method '. $methods .' blocked!</h1>');

}

