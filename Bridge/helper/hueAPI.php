<?php

/**
 * @project       SymconHue/Bridge/helper
 * @file          hueAPI.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

declare(strict_types=1);

trait hueAPI
{
    ########## Public

    /**
     * Sends the data to the bridge.
     *
     * @param string $Endpoint
     * @param string $Request
     * @param string $PostFields
     * @return string
     * @throws Exception
     */
    public function SendDataToBridge(string $Endpoint, string $Request, string $PostFields): string
    {
        $host = $this->ReadPropertyString('Host');
        if ($host == '') {
            return json_encode(new stdClass());
        }
        $user = $this->ReadAttributeString('User');
        $this->SendDebug(__FUNCTION__, 'User: ' . $user, 0);
        if ($Endpoint == '') {
            return json_encode(new stdClass());
        }
        $this->SendDebug(__FUNCTION__, 'Endpoint: ' . $Endpoint, 0);
        $this->SendDebug(__FUNCTION__, 'Request: ' . $Request, 0);
        $this->SendDebug(__FUNCTION__, 'Postfields: ' . $PostFields, 0);
        $data = json_encode(new stdClass());
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST   => $Request,
            CURLOPT_URL             => $Endpoint,
            CURLOPT_USERAGENT       => 'Symcon',
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => 15,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_POSTFIELDS      => $PostFields,
            CURLOPT_HTTPHEADER      => [
                'hue-application-key: ' . $user]]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!curl_errno($ch)) {
            $this->SendDebug(__FUNCTION__, 'Status: ' . $httpCode, 0);
            switch ($httpCode) {
                case 200:  # OK
                    $data = $response;
                    $this->SendDebug(__FUNCTION__, 'Data: ' . $data, 0);
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'Status: ' . $httpCode, 0);
            }
        } else {
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
        }
        curl_close($ch);
        return $data;
    }

    ########## Private

    /**
     * Uses the resource light.
     *
     * @param string $Request
     * @param string $ResourceID
     * @param string $Value
     * @return string
     * @throws Exception
     */
    private function ResourceLight(string $Request, string $ResourceID, string $Value): string
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/clip/v2/resource/light';
        if ($ResourceID != '') {
            $url .= '/' . $ResourceID;
        }
        return $this->SendDataToBridge($url, $Request, $Value);
    }

    /**
     * Uses the resource scene.
     *
     * @param string $Request
     * @param string $ResourceID
     * @param string $Value
     * @return string
     * @throws Exception
     */
    private function ResourceScene(string $Request, string $ResourceID, string $Value): string
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/clip/v2/resource/scene';
        if ($ResourceID != '') {
            $url .= '/' . $ResourceID;
        }
        return $this->SendDataToBridge($url, $Request, $Value);
    }

    /**
     * Uses the resource grouped_light.
     *
     * @param string $Request
     * @param string $ResourceID
     * @param string $Value
     * @return string
     * @throws Exception
     */
    private function ResourceGroupedLight(string $Request, string $ResourceID, string $Value): string
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/clip/v2/resource/grouped_light';
        if ($ResourceID != '') {
            $url .= '/' . $ResourceID;
        }
        return $this->SendDataToBridge($url, $Request, $Value);
    }

    /**
     * Uses the resource zone.
     *
     * @param string $Request
     * @param string $ResourceID
     * @param string $Value
     * @return string
     * @throws Exception
     */
    private function ResourceZone(string $Request, string $ResourceID, string $Value): string
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/clip/v2/resource/zone';
        if ($ResourceID != '') {
            $url .= '/' . $ResourceID;
        }
        return $this->SendDataToBridge($url, $Request, $Value);
    }

    /**
     * Uses the resource device.
     *
     * @param string $Request
     * @param string $ResourceID
     * @param string $Value
     * @return string
     * @throws Exception
     */
    private function ResourceDevice(string $Request, string $ResourceID, string $Value): string
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/clip/v2/resource/device';
        if ($ResourceID != '') {
            $url .= '/' . $ResourceID;
        }
        return $this->SendDataToBridge($url, $Request, $Value);
    }
}