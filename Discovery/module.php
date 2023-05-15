<?php

/**
 * @project       SymconHue/Discovery
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpRedundantMethodOverrideInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection HttpUrlsUsage */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/../libs/constants.php';

class HueDiscovery extends IPSModule
{
    //Constants
    private const HUE_CONFIGURATORS =
        [
            'Device Configurator' => '{DF28D2D5-28A6-DEEB-F290-973FB467525C}',
            'Zone Configurator'   => '{ECC063A3-D99C-4C93-9A32-B0BAE2A851C4}'
        ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties
        $this->RegisterPropertyBoolean('UseWebDiscovery', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version
        $form['elements'][2]['caption'] = 'Version: ' . MODULE_VERSION;
        //Bridges
        $values = [];
        $configuratorID = 9000;
        $bridges = $this->DiscoverBridges();
        foreach ($bridges as $bridgeKey => $bridge) {
            $values[] = [
                'id'           => $bridgeKey + 1,
                'expanded'     => true,
                'IPAddress'    => $bridge['ipv4'],
                'name'         => $bridge['deviceName'],
                'ModelName'    => $bridge['modelName'],
                'ModelNumber'  => $bridge['modelNumber'],
                'SerialNumber' => $bridge['serialNumber']
            ];
            foreach (self::HUE_CONFIGURATORS as $configuratorKey => $Configurator) {
                $configuratorID++;
                $values[] = [
                    'parent'       => $bridgeKey + 1,
                    'id'           => $configuratorID,
                    'IPAddress'    => '',
                    'name'         => $this->Translate($configuratorKey),
                    'ModelName'    => '',
                    'ModelNumber'  => '',
                    'SerialNumber' => '',
                    'instanceID'   => $this->GetInstanceID($bridge['serialNumber'], $this->GetModuleGUID($configuratorKey)),
                    'create'       => [
                        [
                            'moduleID'      => $this->GetModuleGUID($configuratorKey),
                            'name'          => 'Philips Hue ' . $this->Translate($configuratorKey),
                            'configuration' => [
                                'SerialNumber' => $bridge['serialNumber']
                            ]
                        ],
                        [
                            'moduleID'      => '{A8577857-5AC9-A684-C4CC-671A921E8BFE}', //Philips Hue Bridge GUID
                            'name'          => 'Philips Hue Bridge',
                            'configuration' => [
                                'Host' => $bridge['ipv4']
                            ]
                        ]
                    ]
                ];
            }
        }
        $form['actions'][0]['values'] = $values;
        return json_encode($form);
    }

    ########## Private

    private function DiscoverBridges(): array
    {
        if ($this->ReadPropertyBoolean('UseWebDiscovery')) {
            $bridges = $this->DiscoverBridgesByWeb();
        } else {
            $bridges = $this->DiscoverBridgesByMulticastDNS();
        }
        $this->SendDebug(__FUNCTION__, json_encode($bridges), 0);
        return $bridges;
    }

    private function DiscoverBridgesByMulticastDNS(): array
    {
        $mDNSInstanceIDs = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
        $resultServiceTypes = ZC_QueryServiceType($mDNSInstanceIDs[0], '_hue._tcp', '');
        $this->SendDebug('mDNS resultServiceTypes', print_r($resultServiceTypes, true), 0);
        $bridges = [];
        foreach ($resultServiceTypes as $device) {
            $bridge = [];
            $deviceInfo = ZC_QueryService($mDNSInstanceIDs[0], $device['Name'], '_hue._tcp', 'local.');
            $this->SendDebug('mDNS QueryService', $device['Name'] . ' ' . $device['Type'] . ' ' . $device['Domain'] . '.', 0);
            $this->SendDebug('mDNS QueryService Result', print_r($deviceInfo, true), 0);
            if (!empty($deviceInfo)) {
                $bridge['Hostname'] = $deviceInfo[0]['Host'];
                //IPv4 and IPv6 are swapped
                if (empty($deviceInfo[0]['IPv4'])) {
                    $bridge['ipv4'] = $deviceInfo[0]['IPv6'][0];
                } else {
                    $bridge['ipv4'] = $deviceInfo[0]['IPv4'][0];
                }
                $bridgeData = json_decode($this->ReadBridgeData($bridge['IPv4']), true);
                if (array_key_exists('device', $bridgeData)) {
                    $bridge['deviceName'] = (string) $bridgeData['device']['friendlyName'];
                    $bridge['modelName'] = (string) $bridgeData['device']['modelName'];
                    $bridge['modelNumber'] = (string) $bridgeData['device']['modelNumber'];
                    $bridge['serialNumber'] = (string) $bridgeData['device']['serialNumber'];
                    $bridges[] = $bridge;
                }
            }
        }
        return $bridges;
    }

    private function DiscoverBridgesByWeb(): array
    {
        $bridges = [];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://discovery.meethue.com',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (!curl_errno($curl)) {
            $this->SendDebug(__FUNCTION__, 'Response http code: ' . $httpCode, 0);
            switch ($httpCode) {
                case 200:  # OK
                    $this->SendDebug(__FUNCTION__, $response, 0);
                    if (is_string($response) && is_array(json_decode($response, true)) && (json_last_error() == JSON_ERROR_NONE)) {
                        $discoveredBridges = json_decode($response, true);
                        foreach ($discoveredBridges as $discoveredBridge) {
                            if (array_key_exists('internalipaddress', $discoveredBridge)) {
                                $bridge['ipv4'] = $discoveredBridge['internalipaddress'];
                                $bridgeData = json_decode($this->ReadBridgeData($bridge['ipv4']), true);
                                if (array_key_exists('device', $bridgeData)) {
                                    $bridge['deviceName'] = (string) $bridgeData['device']['friendlyName'];
                                    $bridge['modelName'] = (string) $bridgeData['device']['modelName'];
                                    $bridge['modelNumber'] = (string) $bridgeData['device']['modelNumber'];
                                    $bridge['serialNumber'] = (string) $bridgeData['device']['serialNumber'];
                                    $bridges[] = $bridge;
                                }
                            }
                        }
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            }
        } else {
            $error_msg = curl_error($curl);
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
        }
        curl_close($curl);
        return $bridges;
    }

    private function ReadBridgeData(string $IPv4): string
    {
        $result = json_encode(new stdClass());
        $xmlData = file_get_contents('http://' . $IPv4 . ':80/description.xml');
        if ($xmlData === false) {
            return $result;
        }
        $xml = new SimpleXMLElement($xmlData);
        $modelName = (string) $xml->device->modelName;
        if (strpos($modelName, 'Philips hue bridge') === false) {
            return $result;
        }
        return json_encode($xml);
    }

    private function GetModuleGUID(string $Type): string
    {
        return self::HUE_CONFIGURATORS[$Type] ?? '';
    }

    private function GetInstanceID(string $SerialNumber, string $ModuleGUID): int
    {
        if ($ModuleGUID != '') {
            $ids = IPS_GetInstanceListByModuleID($ModuleGUID);
            foreach ($ids as $id) {
                if ((strtolower(IPS_GetProperty($id, 'SerialNumber')) == strtolower($SerialNumber))) {
                    return $id;
                }
            }
        }
        return 0;
    }
}