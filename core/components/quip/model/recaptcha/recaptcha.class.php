<?php
/**
 * reCaptcha Integration
 *
 * Copyright 2009-2010 by Shaun McCormick <shaun@modx.com>
 *
 * reCaptcha Integration is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * reCaptcha Integration is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * reCaptcha Integration; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package recaptcha
 */
/**
 * reCaptcha modX service class.
 *
 * Based off of recaptchalib.php by Mike Crawford and Ben Maurer. Changes include converting to OOP and making a class.
 *
 * @package recaptcha
 */
class reCaptcha {
    const OPT_API_VERIFY_SERVER = 'api_verify_server';
    const OPT_PRIVATE_KEY = 'privateKey';
    const OPT_PUBLIC_KEY = 'publicKey';
    const OPT_USE_SSL = 'use_ssl';

    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $this->modx->lexicon->load('quip:recaptcha');
        $this->config = array_merge(array(
            reCaptcha::OPT_PRIVATE_KEY => $this->modx->getOption('recaptcha.private_key',$config,''),
            reCaptcha::OPT_PUBLIC_KEY => $this->modx->getOption('recaptcha.public_key',$config,''),
            reCaptcha::OPT_USE_SSL => $this->modx->getOption('recaptcha.use_ssl',$config,false),
            reCaptcha::OPT_API_VERIFY_SERVER => 'https://www.google.com/recaptcha/api/siteverify?',
        ),$config);
    }

    /**
     * Encodes the given data into a query string format
     * @param $data - array of string elements to be encoded
     * @return string - encoded request
     */
    protected function qsencode($data) {
        $req = '';
        foreach ($data as $key => $value) {
            $req .= $key . '=' . urlencode( stripslashes($value) ) . '&';
        }

        // Cut the last '&'
        $req=substr($req,0,strlen($req)-1);
        return $req;
    }

    /**
     * Submits an HTTP GET to a reCAPTCHA server.
     *
     * @param string $path - url path to recaptcha server.
     * @param array $data - array of parameters to be sent.
     *
     * @return array - response
     */
    function httpGet($path, $data) {
        $req = $this->qsencode($data);
        $response = file_get_contents($path . $req);
        return $response;
    }

    /**
     * Gets the challenge HTML (javascript and non-javascript version).
     * This is called from the browser, and the resulting reCAPTCHA HTML widget
     * is embedded within the HTML form it was called from.
     * @param string $theme The theme to use
     * @param string $error The error given by reCAPTCHA (optional, default is null)
     * @param boolean $use_ssl Should the request be made over ssl? (optional, default is false)

     * @return string - The HTML to be embedded in the user's form.
     */
    public function getHtml($theme = 'light') {
        if (empty($this->config[reCaptcha::OPT_PUBLIC_KEY])) {
            return $this->error($this->modx->lexicon('recaptcha.no_api_key'));
        }

        $opts = array(
            'data-theme' => $theme,
            'data-sitekey' => $this->config[reCaptcha::OPT_PUBLIC_KEY],
            'hl' => $this->modx->getOption('cultureKey',null,'en'),
        );

        $optstrings = array();

        foreach ($opts as $attr => $val) {
            $optstrings[] = "{$attr}=\"{$val}\"";
        }

        $attributes = implode(' ', $optstrings);

        $markup = '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
        $markup .= '<div class="g-recaptcha" ' . $attributes . '></div>';

        return $markup;
    }

    protected function error($message = '') {
        $response = new reCaptchaResponse();
        $response->is_valid = false;
        $response->error = $message;
        return $message;
    }

    /**
      * Calls an HTTP POST function to verify if the user's guess was correct
      * @param string $remoteip
      * @param string $challenge
      * @param string $response
      * @param array $extra_params an array of extra variables to post to the server
      * @return ReCaptchaResponse
      */
    public function checkAnswer($remoteIp, $response) {
        // Discard empty solution submissions
        if ($response == null || strlen($response) == 0) {
            $reCaptchaResponse = new reCaptchaResponse();
            $reCaptchaResponse->is_valid = false;
            $reCaptchaResponse->error = 'missing-input';
            return $reCaptchaResponse;
        }

        $verifyServer = $this->config[reCaptcha::OPT_API_VERIFY_SERVER];

        $getResponse = $this->httpGet($verifyServer,array (
            'remoteip' => $remoteIp,
            'secret' => $this->config[reCaptcha::OPT_PRIVATE_KEY],
            'response' => $response,
        ));

        $answers = json_decode($getResponse, true);
        $reCaptchaResponse = new reCaptchaResponse();

        if ($answers['success'] === true) {
            $reCaptchaResponse->is_valid = true;
        } else {
            $reCaptchaResponse->is_valid = false;
            $reCaptchaResponse->error = $answers['error-codes'];
        }

        return $reCaptchaResponse;
    }

}

/**
 * A reCaptchaResponse is returned from reCaptcha::check_answer()
 */
class reCaptchaResponse {
    public $is_valid;
    public $error;
}
