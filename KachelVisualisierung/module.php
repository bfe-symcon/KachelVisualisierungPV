<?php

declare(strict_types=1);

class KachelVisualisierungPV extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('PVVariableID', 0);
        $this->RegisterPropertyInteger('HausVariableID', 0);
        $this->RegisterPropertyInteger('AkkuVariableID', 0);
        $this->RegisterPropertyInteger('NetzVariableID', 0);

        // HTML Ausgabevariable registrieren
        $this->RegisterVariableString("HTMLBox", "Visualisierung", "~HTMLBox", 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterHook();
        $this->UpdateDisplay();

        // Register update events for variables
        $this->RegisterVariableUpdate($this->ReadPropertyInteger('PVVariableID'));
        $this->RegisterVariableUpdate($this->ReadPropertyInteger('HausVariableID'));
        $this->RegisterVariableUpdate($this->ReadPropertyInteger('AkkuVariableID'));
        $this->RegisterVariableUpdate($this->ReadPropertyInteger('NetzVariableID'));
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'SelectVariable', 'name' => 'PVVariableID', 'caption' => 'PV-Leistung'],
                ['type' => 'SelectVariable', 'name' => 'HausVariableID', 'caption' => 'Hausverbrauch'],
                ['type' => 'SelectVariable', 'name' => 'AkkuVariableID', 'caption' => 'Batterie-Stand'],
                ['type' => 'SelectVariable', 'name' => 'NetzVariableID', 'caption' => 'Netzbezug']
            ]
        ]);
    }

    private function GetLiveData(): array
    {
        $getVal = function(int $varID, int $precision = 0) {
            if ($varID > 0 && @IPS_VariableExists($varID)) {
                return round(@GetValue($varID), $precision);
            }
            return '-';
        };

        return [
            'pv' => $getVal($this->ReadPropertyInteger('PVVariableID'), 2),
            'haus' => $getVal($this->ReadPropertyInteger('HausVariableID')),
            'akku' => $getVal($this->ReadPropertyInteger('AkkuVariableID')),
            'netz' => $getVal($this->ReadPropertyInteger('NetzVariableID'))
        ];
    }

    public function GetLiveJSON()
    {
        header('Content-Type: application/json');
        echo json_encode($this->GetLiveData());
    }

    public function UpdateDisplay()
    {
        $data = $this->GetLiveData();
        $html = "<style>
        .kachel-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f9f9fb;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            max-width: 600px;
            margin: auto;
        }
        .kachel-header {
            font-weight: 600;
            font-size: 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kachel-data {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .kachel-item {
            flex: 1;
        }
        .kachel-icon {
            width: 64px;
            height: 64px;
            margin: auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            color: white;
            font-size: 24px;
        }
        .sun { background: #fdd835; color: #fff176; }
        .home { background: #64b5f6; }
        .battery { background: #4caf50; }
        .grid { background: #546e7a; }
        .kachel-value {
            font-size: 18px;
            font-weight: 600;
        }
        .kachel-unit {
            font-size: 14px;
            color: #666;
        }
        </style>";

        $html .= "<div class='kachel-wrapper'>
            <div class='kachel-header'>Live Daten</div>
            <div class='kachel-data'>
                <div class='kachel-item'>
                    <div class='kachel-icon sun'>☀️</div>
                    <div class='kachel-value'><span id='kv-pv'>{$data['pv']}</span> kW</div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon home'>🏠</div>
                    <div class='kachel-value'><span id='kv-haus'>{$data['haus']}</span> W</div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon battery'>🔋</div>
                    <div class='kachel-value'><span id='kv-akku'>{$data['akku']}%</span></div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon grid'>⚡</div>
                    <div class='kachel-value'><span id='kv-netz'>{$data['netz']}</span> W</div>
                </div>
            </div>
        </div>";

        $this->SetBuffer("HTML", $html);
        $this->SetValue("HTMLBox", $html);
    }

    private function RegisterVariableUpdate(int $variableID)
    {
        if ($variableID > 0 && @IPS_VariableExists($variableID)) {
            $eid = @IPS_GetObjectIDByIdent("UpdateEvent_" . $variableID, $this->InstanceID);
            if ($eid === false) {
                $eid = IPS_CreateEvent(0);
                IPS_SetEventTrigger($eid, 0, $variableID);
                IPS_SetParent($eid, $this->InstanceID);
                IPS_SetIdent($eid, "UpdateEvent_" . $variableID);
                IPS_SetName($eid, "UpdateEvent für Variable " . $variableID);
                IPS_SetEventScript($eid, 'KachelVisualisierungPV_UpdateDisplay(' . $this->InstanceID . ');');
                IPS_SetEventActive($eid, true);
            }
        }
    }

    private function RegisterHook()
    {
        $scriptID = IPS_CreateScript(0);
        IPS_SetName($scriptID, "KachelHook_" . $this->InstanceID);
        IPS_SetScriptContent($scriptID, '<?php KachelVisualisierungPV_GetLiveJSON(' . $this->InstanceID . '); ?>');
        IPS_SetParent($scriptID, $this->InstanceID);
    }
}
