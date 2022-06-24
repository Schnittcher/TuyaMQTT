<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/functions.php';
eval('declare(strict_types=1);namespace TuyaMQTT {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace TuyaMQTT {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace TuyaMQTT {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/ColorHelper.php') . '}');

    class RGBTWLight extends IPSModule
    {
        use \TuyaMQTT\DebugHelper;
        use \TuyaMQTT\VariableProfileHelper;
        use \TuyaMQTT\ColorHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
            $this->RegisterPropertyString('MQTTBaseTopic', 'tuya');
            $this->RegisterPropertyString('MQTTTopic', '');

            $this->RegisterPropertyBoolean('WhiteBrightness', true);
            $this->RegisterPropertyBoolean('ColorBrightness', true);
            $this->RegisterPropertyBoolean('ColorTemperature', true);
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

            $Filter = preg_quote($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic'));
            $this->SendDebug('Filter ', '.*' . $Filter . '.*', 0);
            $this->SetReceiveDataFilter('.*' . $Filter . '.*');

            if (!IPS_VariableProfileExists('TuyaMQTT.RGBTWLightMode')) {
                $this->RegisterProfileStringEx('TuyaMQTT.RGBTWLightMode', 'Menu', '', '', [
                    ['white', $this->Translate('White'), '', 0xFFA500],
                    ['colour', $this->Translate('Color'), '', 0xFF0000],
                    ['scene', $this->Translate('Scenes'), '', 0x000000],
                ]);
            }
            $this->RegisterProfileInteger('TuyaMQTT.RGBTWLightColorTemperature', 'Intensity', '', '', 154, 400, 1);

            $this->RegisterVariableBoolean('State', $this->Translate('State'), '~Switch', 1);
            $this->EnableAction('State');
            $this->MaintainVariable('WhiteBrightness', $this->Translate('White Brightness'), 1, '~Intensity.100', 2, $this->ReadPropertyBoolean('WhiteBrightness'));
            if ($this->ReadPropertyBoolean('WhiteBrightness')) {
                $this->EnableAction('WhiteBrightness');
            }

            $this->MaintainVariable('ColorBrightness', $this->Translate('Color Brightness'), 1, '~Intensity.100', 3, $this->ReadPropertyBoolean('ColorBrightness'));
            if ($this->ReadPropertyBoolean('ColorBrightness')) {
                $this->EnableAction('ColorBrightness');
            }

            $this->RegisterVariableInteger('Color', $this->Translate('Color'), '~HexColor', 4);
            $this->EnableAction('Color');
            $this->RegisterVariableString('Mode', $this->Translate('Mode'), 'TuyaMQTT.RGBTWLightMode', 4);
            $this->EnableAction('Mode');

            $this->MaintainVariable('ColorTemperature', $this->Translate('Color Temperature'), 1, 'TuyaMQTT.RGBTWLightColorTemperature', 5, $this->ReadPropertyBoolean('ColorTemperature'));
            if ($this->ReadPropertyBoolean('ColorTemperature')) {
                $this->EnableAction('ColorTemperature');
            }
        }

        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'State':
                    $this->SendPayload('command', $Value ? 'true' : 'false');
                    break;
                case 'WhiteBrightness':
                    $this->SendPayload('white_brightness_command', strval($Value));
                    break;
                case 'ColorBrightness':
                    $this->SendPayload('color_brightness_command', strval($Value));
                    break;
                case 'Color':
                    $RGB = $this->HexToRGB($Value);
                    $this->SendDebug('RequestAction :: Color', $RGB, 0);
                    $HSV = $this->RGBtoHSV($RGB[0], $RGB[1], $RGB[2]);
                    $this->SendDebug('RequestAction :: HSV', $HSV, 0);
                    $HSV = implode(',', $HSV);
                    $this->SendDebug('RequestAction :: HSV implode', $HSV, 0);
                    $this->SendPayload('hsb_command', strval($HSV));
                    break;
                case 'Mode':
                    $this->SendPayload('mode_command', strval($Value));
                    break;
                case 'ColorTemperature':
                    $this->SendPayload('color_temp_command', strval($Value));
                    break;
                default:
                    $this->SendDebug('RequestAction :: Invalid Ident', $Ident);
                    break;
            }
        }

        public function ReceiveData($JSONString)
        {
            if (!empty($this->ReadPropertyString('MQTTTopic'))) {
                $Buffer = json_decode($JSONString, true);

                $this->SendDebug('ReceiveData :: Buffer', $Buffer, 0);

                if (array_key_exists('Topic', $Buffer)) {
                    $Payload = $Buffer['Payload'];
                    if (fnmatch('*/state', $Buffer['Topic'])) {
                        switch ($Payload) {
                            case 'ON':
                                $this->SetValue('State', true);
                                break;
                            case 'OFF':
                                $this->SetValue('State', false);
                                break;
                            default:
                                # code...
                                break;
                        }
                    }
                    if (fnmatch('*/white_brightness_state', $Buffer['Topic'])) {
                        $this->SetValue('WhiteBrightness', $Payload);
                    }
                    if (fnmatch('*/color_brightness_state', $Buffer['Topic'])) {
                        $this->SetValue('ColorBrightness', $Payload);
                    }
                    if (fnmatch('*/hsb_state', $Buffer['Topic'])) {
                        $this->SendDebug('ReceiveData :: Color', $Payload, 0);
                        $HSL = explode(',', $Payload);
                        $this->SendDebug('ReceiveData :: HSL', $HSL, 0);
                        $RGBColor = ltrim($this->hsv2rgb($HSL[0], $HSL[1], $HSL[2])['hex'], '#');
                        $this->SendDebug('ReceiveData :: RGBColor', $RGBColor, 0);
                        $this->SetValue('Color', hexdec($RGBColor));
                    }
                    if (fnmatch('*/mode_state', $Buffer['Topic'])) {
                        $this->SetValue('Mode', $Payload);
                    }
                    if (fnmatch('*/color_temp_state', $Buffer['Topic'])) {
                        $this->SetValue('ColorTemp', $Payload);
                    }
                }
            }
        }

        private function SendPayload($topic, $payload)
        {
            $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
            $Data['PacketType'] = 3;
            $Data['QualityOfService'] = 0;
            $Data['Retain'] = false;
            $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
            $Data['Payload'] = $payload;
            $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
            $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
            $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
            $this->SendDataToParent($DataJSON);
        }
    }