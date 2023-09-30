<?php

/**
 * @project       SymconHue/ZoneConfigurator
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

class HueZoneConfigurator extends IPSModule
{
    //Constants
    private const RESOURCES =
        [
            'grouped_light' => '{17F36BF2-DAF5-27CB-D815-4609C523ED14}'
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
        //Zones
        $data = [];
        $buffer = [];
        $data['DataID'] = '{3C89E2B5-A25F-8D3B-DB5D-D99AC9416D8A}';
        $buffer['Command'] = 'ResourceZone';
        $buffer['Params']['request'] = 'GET';
        $buffer['Params']['resourceID'] = '';
        $buffer['Params']['value'] = '';
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $result = $this->SendDataToParent($data);
        $this->SendDebug(__FUNCTION__, 'Result: ' . $result, 0);
        $zones = json_decode($result, true);
        if (!array_key_exists('data', $zones)) {
            $zones = [];
        } else {
            $zones = $zones['data'];
        }
        $servicesID = 3000;
        $values = [];
        foreach ($zones as $key => $zone) {
            $values[] = [
                'id'                    => $key + 1,
                'ZoneID'                => $zone['id'],
                'DisplayName'           => $zone['metadata']['name'],
                'name'                  => $zone['metadata']['name'],
                'Type'                  => $zone['type']
            ];
            foreach ($zone['services'] as $service) {
                if ($service['rtype'] == 'entertainment') {
                    continue;
                }
                $servicesID++;
                $values[] = [
                    'parent'                => $key + 1,
                    'id'                    => $servicesID,
                    'ZoneID'                => $service['rid'],
                    'DisplayName'           => '',
                    'name'                  => '',
                    'Type'                  => $service['rtype'],
                    'instanceID'            => $this->GetInstanceID($zone['id'], $service['rid']),
                    'create'                => [
                        'moduleID'      => $this->GetModuleGUID($service['rtype']),
                        'configuration' => [
                            'RoomZoneID'        => strval($zone['id']),
                            'ResourceID'        => strval($service['rid']),
                        ],
                        'name'     => $zone['metadata']['name'] . ' ' . ucfirst($service['rtype'])
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
                    if ((strtolower(IPS_GetProperty($id, 'RoomZoneID')) == strtolower($DeviceID)) && (strtolower(IPS_GetProperty($id, 'ResourceID')) == strtolower($ResourceID))) {
                        return $id;
                    }
                }
            }
        }
        return 0;
    }
}