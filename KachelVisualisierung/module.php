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

        $this->RegisterVariableString('HTMLBox', 'Visualisierung', '~HTMLBox', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // einmaliges Rendern des HTML-Gerüsts
        $this->RenderTemplate();

        // Hook für JSON-Daten immer aktuell halten
        $this->RegisterHook();

        // Bei Änderung nur den Hook JSON aktualisieren
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

    // --------------------------------------------------------------
    // Dieses Template schreiben wir nur ein einziges Mal in ApplyChanges
    private function RenderTemplate()
    {
        $html = "<style>/* ... dein CSS ... */</style>
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
        const hookUrl = '/hook/KachelVisualisierung/' + {$this->InstanceID};
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
    // --------------------------------------------------------------

    // liefert nur noch JSON, kein SetValue mehr!
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
                : 0;
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
        $ident    = 'HookScript_' . $this->InstanceID;
        $scriptID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($scriptID===false) {
            $scriptID = IPS_CreateScript(0);
            IPS_SetParent($scriptID, $this->InstanceID);
            IPS_SetIdent($scriptID, $ident);
            IPS_SetName($scriptID, 'KachelHook_' . $this->InstanceID);
        }
        $code = '<?php (new KachelVisualisierungPV(' . $this->InstanceID . '))->GetLiveJSON(); ?>';
        IPS_SetScriptContent($scriptID, $code);
    }

    // nur JSON-Updates triggern, kein RenderTemplate()
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message === VM_UPDATE) {
            // nichts weiter tun – die JS holt sich die neuen Werte selbst
        }
    }
}
