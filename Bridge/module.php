<?php

/**
 * @project       SymconHue/Bridge
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpRedundantMethodOverrideInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/../libs/constants.php';
include_once __DIR__ . '/helper/autoload.php';

class HueBridge extends IPSModule
{
    //Helper
    use hueAPI;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties
        $this->RegisterPropertyString('Host', '');

        ##### Attributes
        $this->RegisterAttributeString('User', '');

        ##### Messages
        $this->RegisterMessage(IPS_GetInstance($this->InstanceID)['ConnectionID'], IM_CHANGESTATUS);

        ##### Connect to SSE client I/O
        $this->ConnectParent('{2FADB4B7-FDAB-3C64-3E2C-068A4809849A}');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        if ($this->ReadAttributeString('User') == '') {
            $this->SetStatus(200);
            $this->LogMessage('ID ' . $this->InstanceID . ', Error: Registration is incomplete, please pair IP-Symcon with the Philips Hue Bridge.', KL_ERROR);
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version
        $form['elements'][2]['caption'] = 'Version: ' . MODULE_VERSION;
        //Application key
        $form['actions'][2]['items'][1]['caption'] = $this->ReadAttributeString('User') ? substr($this->ReadAttributeString('User'), 0, 16) . '...' : $this->Translate('Application Key: Not registered yet!');
        return json_encode($form);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case IM_CHANGESTATUS:
                $this->SendDebug(__FUNCTION__, 'Status has changed', 0);
                break;

        }
    }

    public function UpdateApplicationKey(string $Key): void
    {
        if ($Key != '') {
            $this->WriteAttributeString('User', $Key);
            $this->RegisterServerEvents();
            $this->SetStatus(102);
            $this->ReloadForm();
        }
    }

    public function ForwardData($JSONString): string
    {
        $this->SendDebug(__FUNCTION__, $JSONString, 0);
        $data = json_decode($JSONString);

        switch ($data->Buffer->Command) {
            case 'ResourceLight':
                $params = (array) $data->Buffer->Params;
                $response = $this->ResourceLight($params['request'], $params['resourceID'], $params['value']);
                break;

            case 'ResourceScene':
                $params = (array) $data->Buffer->Params;
                $response = $this->ResourceScene($params['request'], $params['resourceID'], $params['value']);
                break;

            case 'ResourceGroupedLight':
                $params = (array) $data->Buffer->Params;
                $response = $this->ResourceGroupedLight($params['request'], $params['resourceID'], $params['value']);
                break;

            case 'ResourceZone':
                $params = (array) $data->Buffer->Params;
                $response = $this->ResourceZone($params['request'], $params['resourceID'], $params['value']);
                break;

            case 'ResourceDevice':
                $params = (array) $data->Buffer->Params;
                $response = $this->ResourceDevice($params['request'], $params['resourceID'], $params['value']);
                break;

            default:
                $this->SendDebug(__FUNCTION__, 'Invalid Command: ' . $data->Buffer->Command, 0);
                $response = '';
        }
        $this->SendDebug(__FUNCTION__, $response, 0);
        return $response;
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $hueData = json_decode($data['Data'], true);
        foreach ($hueData as $data) {
            $data['DataID'] = '{72F9EAB8-BEED-2918-7295-804C50D91D65}';
            $data['Data'] = $data['data'];
            $this->SendDataToChildren(json_encode($data));
        }
    }

    public function RegisterUser()
    {
        $endpointURL = 'https://' . $this->ReadPropertyString('Host') . '/api';
        $postFields['devicetype'] = 'Symcon';
        $postFields['generateclientkey'] = true;
        $result = json_decode($this->SendDataToBridge($endpointURL, 'POST', json_encode($postFields)), true);
        if (@isset($result[0]['success']['username'])) {
            $this->SendDebug(__FUNCTION__, 'OK: ' . $result[0]['success']['username'], 0);
            $this->WriteAttributeString('User', $result[0]['success']['username']);
            $this->RegisterServerEvents();
            $this->SetStatus(102);
        } else {
            $this->SendDebug(__FUNCTION__ . 'Pairing failed', json_encode($result), 0);
            $this->SetStatus(200);
            $this->LogMessage('ID ' . $this->InstanceID . ', Error: ' . $result[0]['error']['type'] . ': ' . $result[0]['error']['description'], KL_ERROR);
        }
    }

    ########## Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterServerEvents(): void
    {
        $url = 'https://' . $this->ReadPropertyString('Host') . '/eventstream/clip/v2';
        $this->SendDebug(__FUNCTION__, 'URL: ' . $url, 0);
        $parent = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        IPS_SetProperty($parent, 'URL', $url);
        IPS_SetProperty($parent, 'VerifyPeer', false);
        IPS_SetProperty($parent, 'VerifyHost', false);
        IPS_SetProperty($parent, 'Active', true);
        IPS_SetProperty($parent, 'Headers', json_encode([['Name' => 'Accept', 'Value' => 'text/event-stream'], ['Name' => 'hue-application-key', 'Value' => $this->ReadAttributeString('User')]]));
        IPS_ApplyChanges($parent);
    }
}