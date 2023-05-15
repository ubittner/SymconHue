<?php

/**
 * @project       SymconHue/Light
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

require_once __DIR__ . '/../libs/constants.php';
require_once __DIR__ . '/../libs/ColorHelper.php';

class HueLight extends IPSModule
{
    //Helper
    use ColorHelper;
    //Const
    private const RESOURCE_LIGHT = 'ResourceLight';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('ResourceID', '');

        ##### Variables
        //State
        $id = @$this->GetIDForIdent('State');
        $this->RegisterVariableBoolean('State', $this->Translate('State'), '~Switch', 10);
        $this->EnableAction('State');
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('State'), 'Bulb');
        }

        //Brightness
        $this->RegisterVariableInteger('Brightness', $this->Translate('Brightness'), '~Intensity.100', 20);
        $this->EnableAction('Brightness');

        //Color
        $id = @$this->GetIDForIdent('Color');
        $this->RegisterVariableInteger('Color', $this->Translate('Color'), '~HexColor', 30);
        $this->EnableAction('Color');
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('Color'), 'Paintbrush');
        }

        //Color Temperature
        $profile = MODULE_PREFIX . '.' . $this->InstanceID . '.ColorTemperature';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');
        IPS_SetVariableProfileText($profile, '', ' mired');
        IPS_SetVariableProfileValues($profile, 153, 500, 1);
        IPS_SetVariableProfileDigits($profile, 0);
        $this->RegisterVariableInteger('ColorTemperature', $this->Translate('Color Temperature'), $profile, 40);
        $this->EnableAction('ColorTemperature');

        //Transition
        $profile = MODULE_PREFIX . '.' . $this->InstanceID . '.Transition';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');
        IPS_SetVariableProfileText($profile, '', ' ms');
        IPS_SetVariableProfileValues($profile, 0, 0, 1);
        IPS_SetVariableProfileDigits($profile, 0);
        $this->RegisterVariableInteger('Transition', $this->Translate('Transition'), $profile, 50);
        $this->EnableAction('Transition');

        ##### Connect to parent bridge
        $this->ConnectParent('{A8577857-5AC9-A684-C4CC-671A921E8BFE}');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['ColorTemperature', 'Transition'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                $this->UnregisterProfile($profileName);
            }
        }
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Setze Filter für ReceiveData
        $resourceID = $this->ReadPropertyString('ResourceID');
        $this->SetReceiveDataFilter('.*' . $resourceID . '.*');

        //Update state
        $this->UpdateLightValues();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version
        $form['elements'][2]['caption'] = 'Version: ' . MODULE_VERSION;
        return json_encode($form);
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug(__FUNCTION__, $JSONString, 0);
        $this->MapResultsToValues($JSONString);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'State':
                $this->SwitchLight($Value);
                break;

            case 'Brightness':
                $this->SetBrightness($Value);
                break;

            case 'Color':
                $this->SetColor($Value);
                break;

            case 'ColorTemperature':
                $this->SetColorTemperature($Value);
                break;

            case 'Transition':
                $this->SetValue('Transition', $Value);

        }
    }

    /**
     * Sets the light.
     *
     * @param string $Color
     * @param string $OptionalParameters
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    public function SetLight(string $Color, string $OptionalParameters): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $RGB = $this->Hex2RGB(hexdec($Color));
        $XY = $this->RGB2CIE($RGB[0], $RGB[1], $RGB[2]);
        $value = ['color' => ['xy' => ['x' => $XY['x'], 'y' => $XY['y']]]];
        $value = array_merge($value, json_decode($OptionalParameters, true));
        $value = json_encode($value);
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value, 0);
        $result = $this->SendData('PUT', self::RESOURCE_LIGHT, $resourceID, $value);
        return !$this->CheckForErrors($result);
    }

    /**
     * Updates the values of the light.
     *
     * @return bool
     * @throws Exception
     */
    public function UpdateLightValues(): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $result = $this->SendData('GET', self::RESOURCE_LIGHT, $resourceID, '');
        $this->MapResultsToValues($result);
        return !$this->CheckForErrors($result);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    /**
     * Unregisters a variable profile.
     *
     * @param string $Name
     * @return void
     */
    private function UnregisterProfile(string $Name): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        foreach (IPS_GetMediaListByType(MEDIATYPE_CHART) as $mediaID) {
            $content = json_decode(base64_decode(IPS_GetMediaContent($mediaID)), true);
            foreach ($content['axes'] as $axis) {
                if ($axis['profile' === $Name]) {
                    return;
                }
            }
        }
        IPS_DeleteVariableProfile($Name);
    }

    /**
     * Switches the light off or on.
     *
     * @param bool $State
     * false =  off,
     * true =   on
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    private function SwitchLight(bool $State): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $this->SetValue('State', $State);
        $value = json_encode(['on' => ['on' => $State]]);
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value, 0);
        $result = $this->SendData('PUT', self::RESOURCE_LIGHT, $resourceID, $value);
        return !$this->CheckForErrors($result);
    }

    /**
     * Sets the brightness of the light.
     *
     * @param int $Brightness
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    private function SetBrightness(int $Brightness): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $this->SetValue('Brightness', $Brightness);
        $duration = $this->GetValue('Transition') ? $this->GetValue('Transition') : 0;
        if ($Brightness > 0) {
            $value = json_encode(['on' => ['on' => true], 'dimming' => ['brightness' => $Brightness], 'dynamics' => ['duration' => $duration]]);
        } else {
            $value = json_encode(['on' => ['on' => false], 'dynamics' => ['duration' => $duration]]);
        }
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value, 0);
        $result = $this->SendData('PUT', self::RESOURCE_LIGHT, $resourceID, $value);
        return !$this->CheckForErrors($result);
    }

    /**
     * Sets the color of the light.
     *
     * @param int $Color
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    private function SetColor(int $Color): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $this->SetValue('Color', $Color);
        $duration = $this->GetValue('Transition') ? $this->GetValue('Transition') : 0;
        $rgb = $this->Hex2RGB($Color);
        $this->SendDebug('RGB: ', json_encode($rgb), 0);
        $xy = $this->RGB2CIE($rgb[0], $rgb[1], $rgb[2]);
        $this->SendDebug('CIE: ', json_encode($xy), 0);
        //If the light is switched off, light will be switched on
        $value = json_encode(['on' => ['on' => true], 'color' => ['xy' => ['x' => $xy['x'], 'y' => $xy['y']]], 'dynamics' => ['duration' => $duration]]);
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value, 0);
        $result = $this->SendData('PUT', self::RESOURCE_LIGHT, $resourceID, $value);
        return !$this->CheckForErrors($result);
    }

    /**
     * Sets the color temperature of the light.
     *
     * @param int $Temperature
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    private function SetColorTemperature(int $Temperature): bool
    {
        $resourceID = $this->ReadPropertyString('ResourceID');
        if ($resourceID == '') {
            return false;
        }
        $this->SetValue('ColorTemperature', $Temperature);
        $duration = $this->GetValue('Transition') ? $this->GetValue('Transition') : 0;
        //If the light is switched off, light will be switched on
        $value = json_encode(['on' => ['on' => true], 'color_temperature' => ['mirek' => $Temperature], 'dynamics' => ['duration' => $duration]]);
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value, 0);
        $result = $this->SendData('PUT', self::RESOURCE_LIGHT, $resourceID, $value);
        return !$this->CheckForErrors($result);
    }

    /**
     * Sends data to the parent instance, the Philips Hue Bridge.
     *
     * @param string $Request
     * @param string $Resource
     * @param string $ResourceID
     * @param string $Value
     * @return string
     */
    private function SendData(string $Request, string $Resource, string $ResourceID, string $Value): string
    {
        if (!$this->HasActiveParent()) {
            $this->SendDebug(__FUNCTION__, 'Abort, parent instance is not active!', 0);
            return json_encode(new stdClass());
        }
        $data = [];
        $buffer = [];
        $data['DataID'] = '{3C89E2B5-A25F-8D3B-DB5D-D99AC9416D8A}';
        $buffer['Command'] = $Resource;
        $buffer['Params']['request'] = $Request;
        $buffer['Params']['resourceID'] = $ResourceID;
        $buffer['Params']['value'] = $Value;
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $result = $this->SendDataToParent($data);
        $this->SendDebug(__FUNCTION__, 'Result: ' . $result, 0);
        return $result;
    }

    /**
     * Checks for errors.
     *
     * @param string $Result
     * @return bool
     * false =  no errors,
     * true =   an error occurred
     */
    private function CheckForErrors(string $Result): bool
    {
        $error = true;
        $data = json_decode($Result, true);
        if (is_array($data) && !empty($data)) {
            if (array_key_exists('errors', $data)) {
                if (empty($data['errors'])) {
                    $error = false;
                }
            }
        }
        return $error;
    }

    /**
     * Maps the result from the bridge to this instance values.
     *
     * @param string $Data
     * @return void
     */
    private function MapResultsToValues(string $Data): void
    {
        $Data = json_decode($Data, true);
        if (array_key_exists('data', $Data)) {
            if (array_key_exists(0, $Data['data'])) {
                $lightData = $Data['data']['0'];
                //State
                if (array_key_exists('on', $lightData)) {
                    if (array_key_exists('on', $lightData['on'])) {
                        $this->SetValue('State', $lightData['on']['on']);
                    }
                }
                //Brightness
                if (array_key_exists('dimming', $lightData)) {
                    if (array_key_exists('brightness', $lightData['dimming'])) {
                        $this->SetValue('Brightness', $lightData['dimming']['brightness']);
                    }
                }
                //Color
                if (array_key_exists('color', $lightData)) {
                    if (array_key_exists('xy', $lightData['color'])) {
                        $rgb = $this->CIE2RGB($lightData['color']['xy']['x'], $lightData['color']['xy']['y'], $this->GetValue('Brightness'));
                        if (preg_match('/^#[a-f0-9]{6}$/i', $rgb)) {
                            $color = hexdec(ltrim($rgb, '#'));
                            $this->SetValue('Color', $color);
                        }
                    }
                }
                //Color temperature
                if (array_key_exists('color_temperature', $lightData)) {
                    if (array_key_exists('mirek', $lightData['color_temperature'])) {
                        if ($lightData['color_temperature']['mirek'] != null) {
                            $this->SetValue('ColorTemperature', $lightData['color_temperature']['mirek']);
                        }
                    }
                }
                //Transition
                if (array_key_exists('dynamics', $lightData)) {
                    if (array_key_exists('transition', $lightData['dynamics'])) {
                        $this->SetValue('Transition', $lightData['dynamics']['transition']);
                    }
                }
            }
        }
    }
}