<?php

declare(strict_types=1);

class KachelVisualisierungPV extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Konfigurations­properties
        $this->RegisterPropertyInteger('PVVariableID', 0);
        $this->RegisterPropertyInteger('HausVariableID', 0);
        $this->RegisterPropertyInteger('AkkuVariableID', 0);
        $this->RegisterPropertyInteger('NetzVariableID', 0);

        // HTML-Ausgabe­variable
        $this->RegisterVariableString('HTMLBox', 'Visualisierung', '~HTMLBox', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Hook für JSON-Daten
        $this->RegisterHook();

        // Beim Laden sofort ausgeben
        $this->UpdateDisplay();

        // Alte Message-Registrierungen zurücksetzen
        foreach ([
            $this->ReadPropertyInteger('PVVariableID'),
            $this->ReadPropertyInteger('HausVariableID'),
            $this->ReadPropertyInteger('AkkuVariableID'),
            $this->ReadPropertyInteger('NetzVariableID')
        ] as $varID) {
            if ($varID > 0) {
                // Wert-Update-Nachrichten abonnieren
                $this->RegisterMessage($varID, VM_UPDATE);
            }
        }
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'SelectVariable', 'name' => 'PVVariableID',   'caption' => 'PV-Leistung'],
                ['type' => 'SelectVariable', 'name' => 'HausVariableID', 'caption' => 'Hausverbrauch'],
                ['type' => 'SelectVariable', 'name' => 'AkkuVariableID', 'caption' => 'Batterie-Stand'],
                ['type' => 'SelectVariable', 'name' => 'NetzVariableID', 'caption' => 'Netzbezug']
            ]
        ]);
    }

    // Fängt alle VM_UPDATE-Nachrichten ab
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message === VM_UPDATE) {
            // Irgendeine der ausgewählten Variablen hat sich geändert
            $this->UpdateDisplay();
        }
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
            'pv'   => $getVal($this->ReadPropertyInteger('PVVariableID'), 2),
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
            .kachel-wrapper { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; background: #f9f9fb; border-radius:16px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,0.08); max-width:600px; margin:auto; }
            .kachel-header  { font-weight:600; font-size:20px; margin-bottom:12px; }
            .kachel-data    { display:flex; justify-content:space-around; text-align:center; }
            .kachel-item    { flex:1; }
            .kachel-icon    { width:64px; height:64px; margin:auto; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:8px; color:white; font-size:24px; }
            .sun { background:#fdd835; color:#fff176; }
            .home { background:#64b5f6; }
            .battery { background:#4caf50; }
            .grid { background:#546e7a; }
            .kachel-value   { font-size:18px; font-weight:600; }
        </style>
        <div class='kachel-wrapper'>
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
        </div>
        <script>
            function updateKachel() {
                fetch('/hook/KachelVisualisierung/' + {$this->InstanceID})
                    .then(res => res.json())
                    .then(d => {
                        document.getElementById('kv-pv').innerText    = d.pv;
                        document.getElementById('kv-haus').innerText  = d.haus;
                        document.getElementById('kv-akku').innerText  = d.akku + '%';
                        document.getElementById('kv-netz').innerText  = d.netz;
                    });
            }
            setInterval(updateKachel, 3000);
        </script>";

        // In die String-Variable schreiben
        $this->SetValue('HTMLBox', $html);
    }

    private function RegisterHook()
    {
        $ident = 'HookScript_' . $this->InstanceID;
        $scriptID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($scriptID === false) {
            $scriptID = IPS_CreateScript(0);
            IPS_SetParent($scriptID, $this->InstanceID);
            IPS_SetIdent($scriptID, $ident);
            IPS_SetName($scriptID, 'KachelHook_' . $this->InstanceID);
        }
        $code = '<?php (new KachelVisualisierungPV(' . $this->InstanceID . '))->GetLiveJSON(); ?>';
        IPS_SetScriptContent($scriptID, $code);
    }
}
