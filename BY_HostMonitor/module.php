<?
class HostMonitor extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("HostName", "");
        $this->RegisterPropertyString("HostAdresse", "");
        $this->RegisterPropertyInteger("Intervall", 60);
        $this->RegisterPropertyInteger("AlarmZeit", 0);
        $this->RegisterPropertyString("BenachrichtigungsText", "Der Host -§HOST- mit Adresse -§ADRESSE- ist seit §ZEITMIN Minuten nicht mehr erreichbar!");
        $this->RegisterPropertyInteger("WebFrontInstanceID", "");
        $this->RegisterPropertyInteger("SmtpInstanceID", "");
        $this->RegisterPropertyInteger("EigenesSkriptID", "");
        $this->RegisterPropertyBoolean("PushMsgAktiv", false);
        $this->RegisterPropertyBoolean("EMailMsgAktiv", false);
        $this->RegisterPropertyBoolean("EigenesSkriptAktiv", false);
        $this->RegisterTimer("Update", 0, 'HMON_Update($_IPS[\'TARGET\']);');
        $this->RegisterTimer("OfflineBenachrichtigung", 0, 'HMON_Benachrichtigung($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        $this->UnregisterTimer("Update");
        $this->UnregisterTimer("OfflineBenachrichtigung");
        
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        
        //Variablenprofil erstellen
        $this->RegisterProfileBooleanEx("HMON.OfflineOnline", "Network", "", "", Array(
                                             Array(false, "Offline",  "", 0xFF0000),
                                             Array(true, "Online",  "", 0x00FF00)
        ));
        
        if (($this->ReadPropertyBoolean("PushMsgAktiv") === true) AND ($this->ReadPropertyInteger("WebFrontInstanceID") == ""))
        {
        		echo "FEHLER - Damit die Push-Benachrichtigung verwendet werdet kann, muss eine WebFront-Instanz ausgewählt werden!";
      	}
      	if (($this->ReadPropertyBoolean("EMailMsgAktiv") === true) AND ($this->ReadPropertyInteger("SmtpInstanceID") == ""))
        {
        		echo "FEHLER - Damit die EMail-Benachrichtigung verwendet werdet kann, muss eine SMTP-Instanz ausgewählt werden!";
      	}
      	if (($this->ReadPropertyBoolean("EigenesSkriptAktiv") === true) AND ($this->ReadPropertyInteger("EigenesSkriptID") == ""))
        {
        		echo "FEHLER - Damit die Skript-Benachrichtigung verwendet werdet kann, muss ein Skript ausgewählt werden!";
      	}
      	if (($this->ReadPropertyBoolean("PushMsgAktiv") === true) AND ($this->ReadPropertyInteger("BenachrichtigungsText") == ""))
      	{
      			echo "FEHLER - Damit die Push-Benachrichtigung verwendet werdet kann, muss ein Text eingetragen werden!";
      	}
      	if (($this->ReadPropertyBoolean("EMailMsgAktiv") === true) AND ($this->ReadPropertyInteger("BenachrichtigungsText") == ""))
      	{
      			echo "FEHLER - Damit die EMail-Benachrichtigung verwendet werdet kann, muss ein Text eingetragen werden!";
      	}

        if (($this->ReadPropertyInteger("HostAdresse") != "") AND ($this->ReadPropertyInteger("HostName") != ""))
        {
		        //Variablen erstellen
		        $this->RegisterVariableBoolean("HostStatus", "Host - Status", "HMON.OfflineOnline");
		        $this->RegisterVariableInteger("HostLastOnline", "Host - Zuletzt online", "~UnixTimestamp");
		        
		        //Timer erstellen
        		$this->SetTimerInterval("Update", $this->ReadPropertyInteger("Intervall"));
        		$this->SetTimerInterval("OfflineBenachrichtigung", 0);
        		
        		//Update
        		$this->Update();
      	}
    }

    public function Update()
    {
      	$Hostname = $this->ReadPropertyString("HostName");
				$Hostadresse = $this->ReadPropertyString("HostAdresse");
      	if (($this->ReadPropertyInteger("HostAdresse") != "") AND ($this->ReadPropertyInteger("HostName") != ""))
        {      	
						$result = @Sys_Ping($Hostadresse, 1000);
						$this->SetValueBoolean("HostStatus", $result);
						if ($result === true)
						{
								$HostLastOnlineTime = time();
								$this->SetValueInteger("HostLastOnline", $HostLastOnlineTime);
						}
						else
						{
								$this->SetTimerInterval("OfflineBenachrichtigung", $this->ReadPropertyInteger("AlarmZeit"));
						}
				}
    }
    
    public function Benachrichtigung()
    {
				$this->SetTimerInterval("OfflineBenachrichtigung", 0);
				$BenachrichtigungsText = $this->ReadPropertyString("BenachrichtigungsText");
				$Hostname = $this->ReadPropertyString("HostName");
				$Hostadresse = $this->ReadPropertyString("HostAdresse");
				$LastOnlineTimeDiffSEK = (int)(time() - GetValueInteger($this->GetIDForIdent("HostLastOnline")));
				$LastOnlineTimeDiffMIN = (int)($LastOnlineTimeDiffSEK / 60);
				$LastOnlineTimeDiffSTD = round($LastOnlineTimeDiffMIN / 60, 2);
				$LastOnlineTimeDiffTAGE = round($LastOnlineTimeDiffSTD / 24, 2);
				
				//Code-Wörter austauschen gegen gewünschte Werte
				$search = array("§HOST", "§ADRESSE", "§ZEITSEK", "§ZEITMIN", "§ZEITSTD", "§ZEITTAGE");
				$replace = array($Hostname, $Hostadresse, $LastOnlineTimeDiffSEK, $LastOnlineTimeDiffMIN, $LastOnlineTimeDiffSTD, $LastOnlineTimeDiffTAGE);
				$Text = str_replace($search, $replace, $BenachrichtigungsText);
				
				//PUSH-NACHRICHT
				if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
        {
        		$WFinstanzID = $this->ReadPropertyInteger("WebFrontInstanceID");
        		if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
        		{
        				WFC_PushNotification($WFinstanzID, "HostMonitor", $Text, "", 0);
        		}
        }
        
        //EMAIL-NACHRICHT
        if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
        {
        		$SMTPinstanzID = $this->ReadPropertyInteger("SmtpInstanceID");
        		if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
        		{
        				SMTP_SendMail($SMTPinstanzID, "HostMonitor", $Text);
        		}		
        }
        
        //EIGENE-AKTION
        if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
        {
        		$SkriptID = $this->ReadPropertyInteger("EigenesSkriptID");
        		if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
        		{
        				IPS_RunScriptEx($SkriptID, array("HMON_Hostname" => $Hostname, "HMON_Adresse" => $Hostadresse, "HMON_Text" => $BenachrichtigungsText, "HMON_Zeit" => $Hostname));
        		}		
        }
    }
    
    private function SetValueBoolean($Ident, $Value)
    {
        $ID = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($ID) <> $Value)
        {
            SetValueBoolean($ID, boolval($Value));
            return true;
        }
        return false;
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }

    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            $id = 0;


        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception("Ident with name " . $Name . " is used for wrong object type", E_USER_WARNING);

            if (IPS_GetEvent($id)['EventType'] <> 1)
            {
                IPS_DeleteEvent($id);
                $id = 0;
            }
        }

        if ($id == 0)
        {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $Name);
        }
        IPS_SetName($id, $Name);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, $Script);
        if ($Interval > 0)
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);

            IPS_SetEventActive($id, true);
        } else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);

            IPS_SetEventActive($id, false);
        }
    }

    protected function UnregisterTimer($Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception('Timer not present', E_USER_NOTICE);
            IPS_DeleteEvent($id);
        }
    }

    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present', E_USER_WARNING);
        if (!IPS_EventExists($id))
            throw new Exception('Timer not present', E_USER_WARNING);

        $Event = IPS_GetEvent($id);

        if ($Interval < 1)
        {
            if ($Event['EventActive'])
                IPS_SetEventActive($id, false);
        }
        else
        {
            if ($Event['CyclicTimeValue'] <> $Interval)
                IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            if (!$Event['EventActive'])
                IPS_SetEventActive($id, true);
        }
    }
}
?>