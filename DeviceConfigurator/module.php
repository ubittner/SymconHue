<?php

/**
 * @project       SymconHue/DeviceConfigurator
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpRedundantMethodOverrideInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/../libs/constants.php';

class HueDeviceConfigurator extends IPSModule
{
    //Constants
    private const RESOURCES =
        [
            'light' => '{C3C746F2-583B-1889-4321-E4DE8CB05846}'
        ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties
        $this->RegisterPropertyString('SerialNumber', '');

        ##### Connect to parent bridge
        $this->ConnectParent('{A8577857-5AC9-A684-C4CC-671A921E8BFE}');
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
        //Devices
        $data = [];
        $buffer = [];
        $data['DataID'] = '{3C89E2B5-A25F-8D3B-DB5D-D99AC9416D8A}';
        $buffer['Command'] = 'ResourceDevice';
        $buffer['Params']['request'] = 'GET';
        $buffer['Params']['resourceID'] = '';
        $buffer['Params']['value'] = '';
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $result = $this->SendDataToParent($data);
        $this->SendDebug(__FUNCTION__, 'Result: ' . $result, 0);
        $devices = json_decode($result, true);
        if (!array_key_exists('data', $devices)) {
            $devices = [];
        } else {
            $devices = $devices['data'];
        }
        $servicesID = 3000;
        $values = [];
        foreach ($devices as $key => $device) {
            $values[] = [
                'id'                    => $key + 1,
                'expanded'              => true,
                'DeviceID'              => $device['id'],
                'DisplayName'           => $device['metadata']['name'],
                'name'                  => $device['metadata']['name'],
                'Type'                  => $device['type'],
                'ModelID'               => $device['product_data']['model_id'],
                'ManufacturerName'      => $device['product_data']['manufacturer_name'],
                'ProductName'           => $device['product_data']['product_name']
            ];
            foreach ($device['services'] as $service) {
                if ($service['rtype'] == 'entertainment') {
                    continue;
                }
                if ($service['rtype'] == 'taurus_7455') {
                    continue;
                }
                $servicesID++;
                $values[] = [
                    'parent'                => $key + 1,
                    'id'                    => $servicesID,
                    'DeviceID'              => $service['rid'],
                    'DisplayName'           => '',
                    'name'                  => '',
                    'Type'                  => $service['rtype'],
                    'ModelID'               => '',
                    'ManufacturerName'      => '',
                    'ProductName'           => '',
                    'instanceID'            => $this->GetInstanceID($device['id'], $service['rid']),
                    'create'                => [
                        'moduleID'      => $this->GetModuleGUID($service['rtype']),
                        'configuration' => [
                            'DeviceID'      => strval($device['id']),
                            'ResourceID'    => strval($service['rid']),
                        ],
                        'name'     => $device['metadata']['name'] . ' ' . ucfirst($service['rtype'])
                    ]
                ];
            }
        }
        $form['actions'][0]['values'] = $values;
        return json_encode($form);
    }

    ########## Private

    private function GetModuleGUID(string $Type): string
    {
        return self::RESOURCES[$Type] ?? '';
    }

    private function GetInstanceID(string $DeviceID, string $ResourceID): int
    {
        foreach (self::RESOURCES as $guid) {
            if ($guid != '') {
                $ids = IPS_GetInstanceListByModuleID($guid);
                foreach ($ids as $id) {
                    if ((strtolower(IPS_GetProperty($id, 'DeviceID')) == strtolower($DeviceID)) && (strtolower(IPS_GetProperty($id, 'ResourceID')) == strtolower($ResourceID))) {
                        return $id;
                    }
                }
            }
        }
        return 0;
    }
}