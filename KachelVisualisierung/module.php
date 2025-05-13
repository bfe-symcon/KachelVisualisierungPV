<?php

declare(strict_types=1);

class KachelVisualisierungPV extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Konfigurations-Properties
        $this->RegisterPropertyInteger('PVVariableID', 0);
        $this->RegisterPropertyInteger('HausVariableID', 0);
        $this->RegisterPropertyInteger('AkkuVariableID', 0);
        $this->RegisterPropertyInteger('NetzVariableID', 0);

        // HTML-Ausgabe-Variable
        $this->RegisterVariableString('HTMLBox', 'Visualisierung', '~HTMLBox', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Einmaliges Rendern des HTML-Gerüsts
        $this->RenderTemplate();

        // Hook-Skript für JSON erzeugen/aktualisieren
        $this->RegisterHook();

        // Bei Änderung nur den Hook-JSON updaten lassen
        foreach ([
            $this->ReadPropertyInteger('PVVariableID'),
            $this->ReadPropertyInteger('HausVariableID'),
            $this->ReadPropertyInteger('AkkuVariableID'),
            $this->ReadPropertyInteger('NetzVariableID')
        ] as $varID) {
            if ($varID > 0) {
                $this->RegisterMessage($varID, VM_UPDATE);
            }
        }
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type'=>'SelectVariable','name'=>'PVVariableID',   'caption'=>'PV-Leistung'],
                ['type'=>'SelectVariable','name'=>'HausVariableID', 'caption'=>'Hausverbrauch'],
                ['type'=>'SelectVariable','name'=>'AkkuVariableID', 'caption'=>'Batterie-Stand'],
                ['type'=>'SelectVariable','name'=>'NetzVariableID', 'caption'=>'Netzbezug']
            ]
        ]);
    }

    private function RenderTemplate()
    {
        $html = "<style>
            .kachel-wrapper { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:#f9f9fb; border-radius:16px; padding:16px; max-width:600px; margin:auto; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
            .kachel-header { font-weight:600; font-size:20px; margin-bottom:12px; }
            .kachel-data { display:flex; justify-content:space-around; text-align:center; }
            .kachel-item { flex:1; }
            .kachel-icon { width:64px; height:64px; margin:auto; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:8px; color:white; font-size:24px; }
            .sun { background:#fdd835; color:#fff176; }
            .home { background:#64b5f6; }
            .battery { background:#4caf50; }
            .grid { background:#546e7a; }
            .kachel-value { font-size:18px; font-weight:600; }
        </style>
        <div class='kachel-wrapper'>
            <div class='kachel-header'>Live Daten</div>
            <div class='kachel-data'>
                <div class='kachel-item'><div class='kachel-icon sun'>☀️</div><div class='kachel-value'><span id='kv-pv'>–</span> kW</div></div>
                <div class='kachel-item'><div class='kachel-icon home'>🏠</div><div class='kachel-value'><span id='kv-haus'>–</span> W</div></div>
                <div class='kachel-item'><div class='kachel-icon battery'>🔋</div><div class='kachel-value'><span id='kv-akku'>–%</span></div></div>
                <div class='kachel-item'><div class='kachel-icon grid'>⚡</div><div class='kachel-value'><span id='kv-netz'>–</span> W</div></div>
            </div>
        </div>
        <script>
            const hookUrl = '/hook/KachelVisualisierungPV/' + {$this->InstanceID};
            async function updateKachel(){
                let res = await fetch(hookUrl);
                let d   = await res.json();
                document.getElementById('kv-pv').innerText   = d.pv;
                document.getElementById('kv-haus').innerText = d.haus;
                document.getElementById('kv-akku').innerText = d.akku + '%';
                document.getElementById('kv-netz').innerText = d.netz;
            }
            updateKachel();
            setInterval(updateKachel, 3000);
        </script>";

        $this->SetValue('HTMLBox', $html);
    }

    public function GetLiveJSON()
    {
        header('Content-Type: application/json');
        echo json_encode($this->GetLiveData());
    }

    private function GetLiveData(): array
    {
        $get = function(int $id, int $prec=0) {
            return $id>0 && @IPS_VariableExists($id)
                ? round(@GetValue($id), $prec)
                : '–';
        };
        return [
            'pv'   => $get($this->ReadPropertyInteger('PVVariableID'), 2),
            'haus' => $get($this->ReadPropertyInteger('HausVariableID')),
            'akku' => $get($this->ReadPropertyInteger('AkkuVariableID')),
            'netz' => $get($this->ReadPropertyInteger('NetzVariableID'))
        ];
    }
    
    private function RegisterHook()
    {
        // 1) Hook-Skript anlegen/finden
        $ident    = 'HookScript_' . $this->InstanceID;
        $scriptID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($scriptID === false) {
            $scriptID = IPS_CreateScript(0);
            IPS_SetParent($scriptID, $this->InstanceID);
            IPS_SetIdent($scriptID, $ident);
            IPS_SetName($scriptID, 'KachelHook_' . $this->InstanceID);
        }
    
        // 2) Code für das Hook-Skript mit require_once
        //    Passe hier den Pfad auf Deinen Modul-Ordner an:
        $modulePath = IPS_GetKernelDir() . '/var/lib/symcon/modules/KachelVisualisierungPV/KachelVisualisierung/module.php';
        $code = <<<PHP
    <?php
    require_once '$modulePath';
    // Modul-Klasse nachladen und JSON ausgeben
    (new KachelVisualisierungPV({$this->InstanceID}))->GetLiveJSON();
    ?>
    PHP;
        IPS_SetScriptContent($scriptID, $code);
    
        // 3) WebHook Control registrieren
        $webhookID = @IPS_GetInstanceIDByName('WebHook Control', 0);
        if (!$webhookID || !IPS_InstanceExists($webhookID)) {
            // Fallback: WebHook Control automatisch anlegen
            $webhookID = IPS_CreateInstance('{FE9D2264-81C6-4CE5-AF01-09CD9B0212E3}');
            IPS_SetName($webhookID, 'WebHook Control');
        }
        $hooks    = json_decode(IPS_GetProperty($webhookID, 'Hooks'), true);
        $hookPath = '/hook/KachelVisualisierungPV/' . $this->InstanceID;
        $exists   = false;
        foreach ($hooks as &$h) {
            if ($h['Hook'] === $hookPath) {
                $h['TargetID'] = $scriptID;
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $hooks[] = [
                'Hook'     => $hookPath,
                'TargetID' => $scriptID
            ];
        }
        IPS_SetProperty($webhookID, 'Hooks', json_encode($hooks));
        IPS_ApplyChanges($webhookID);
    }


    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        // wir brauchen hier nichts zu tun – die JS-Funktion holt sich die Daten selbst
    }
}
