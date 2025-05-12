<?php

declare(strict_types=1);

class KachelVisualisierung extends IPSModule
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
            <div class='kachel-header'>
                <span>Live Daten</span>
                <span>☀️ 16 °C</span>
            </div>
            <div class='kachel-data'>
                <div class='kachel-item'>
                    <div class='kachel-icon sun'>☀️</div>
                    <div class='kachel-value'><span id='kv-pv'>{$data['pv']}</span></div>
                    <div class='kachel-unit'>kW</div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon home'>🏠</div>
                    <div class='kachel-value'><span id='kv-haus'>{$data['haus']}</span></div>
                    <div class='kachel-unit'>W</div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon battery'>🔋</div>
                    <div class='kachel-value'><span id='kv-akku'>{$data['akku']}%</span></div>
                    <div class='kachel-unit'>Batterie</div>
                </div>
                <div class='kachel-item'>
                    <div class='kachel-icon grid'>⚡</div>
                    <div class='kachel-value'><span id='kv-netz'>{$data['netz']}</span></div>
                    <div class='kachel-unit'>W</div>
                </div>
            </div>
        </div>
        <script>
        function updateKachel() {
            fetch('/hook/KachelVisualisierung/{$this->InstanceID}')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('kv-pv').innerText = data.pv;
                    document.getElementById('kv-haus').innerText = data.haus;
                    document.getElementById('kv-akku').innerText = data.akku + '%';
                    document.getElementById('kv-netz').innerText = data.netz;
                });
        }
        setInterval(updateKachel, 3000);
        </script>";

        $this->SetBuffer("HTML", $html);

        $varID = @$this->GetIDForIdent("HTMLBox");
        if ($varID !== false) {
            SetValue($varID, $html);
        }
    }

    public function GetHTML()
    {
        return $this->GetBuffer("HTML");
    }

    private function RegisterHook()
    {
        if (!IPS_InstanceExists(@IPS_GetObjectIDByIdent("HookScript", $this->InstanceID))) {
            $scriptID = IPS_CreateScript(0);
            IPS_SetName($scriptID, "KachelHook_{$this->InstanceID}");
            IPS_SetIdent($scriptID, "HookScript");
            IPS_SetParent($scriptID, $this->InstanceID);
            IPS_SetScriptContent($scriptID, '<?php KachelVisualisierung_GetLiveJSON(' . $this->InstanceID . '); ?>');
        } else {
            $scriptID = IPS_GetObjectIDByIdent("HookScript", $this->InstanceID);
        }

        $webhookID = @IPS_GetInstanceIDByName("WebHook Control", 0);
        if ($webhookID && IPS_InstanceExists($webhookID)) {
            $hooks = json_decode(IPS_GetProperty($webhookID, 'Hooks'), true);
            $newHook = "/hook/KachelVisualisierung/{$this->InstanceID}";
            $exists = false;

            foreach ($hooks as &$hook) {
                if ($hook['Hook'] == $newHook) {
                    $hook['TargetID'] = $scriptID;
                    $exists = true;
                }
            }

            if (!$exists) {
                $hooks[] = [
                    'Hook' => $newHook,
                    'TargetID' => $scriptID
                ];
            }

            IPS_SetProperty($webhookID, 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($webhookID);
        }
    }
}
