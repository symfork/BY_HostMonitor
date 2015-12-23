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
        $this->RegisterPropertyInteger("AlarmZeitDiff", 0);
        $this->RegisterPropertyString("BenachrichtigungsText", "Der Host -§HOST- mit Adresse -§ADRESSE- ist seit §ZEITMIN Minuten nicht mehr erreichbar!");
        $this->RegisterPropertyString("WebFrontInstanceID", "");
        $this->RegisterPropertyString("SmtpInstanceID", "");
        $this->RegisterPropertyString("EigenesSkriptID", "");
        $this->RegisterPropertyBoolean("LoggingAktiv", false);
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

        if (($this->ReadPropertyString("HostName") != "") AND ($this->ReadPropertyString("HostAdresse") != ""))
        {
		        //Variablen erstellen
		        $this->RegisterVariableBoolean("HostStatus", "Host - Status", "HMON.OfflineOnline");
		        $this->RegisterVariableBoolean("HostBenachrichtigungsFlag", "Tmp");
		        IPS_SetHidden($this->GetIDForIdent("HostBenachrichtigungsFlag"), true);
		        $this->RegisterVariableInteger("HostLastOnline", "Host - Zuletzt online", "~UnixTimestamp");
		        IPS_SetIcon($this->GetIDForIdent("HostLastOnline"), "Calendar");
		        
		        //Logging aktivieren
		        if ($this->ReadPropertyBoolean("LoggingAktiv") === true)
		        {
		        		$ArchiveHandlerID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
		        		AC_SetLoggingStatus($ArchiveHandlerID, $this->GetIDForIdent("HostStatus"), true);
		        		IPS_ApplyChanges($ArchiveHandlerID);
		        }
		        
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
      	if (($Hostname != "") AND ($Hostadresse != ""))
        {      	
						$result = @Sys_Ping($Hostadresse, 1000);
						$this->SetValueBoolean("HostStatus", $result);
						if ($result === true)
						{
								$HostLastOnlineTime = time();
								$this->SetValueInteger("HostLastOnline", $HostLastOnlineTime);
								$this->SetValueBoolean("HostBenachrichtigungsFlag", false);
						}
						else
						{
								if (GetValueBoolean($this->GetIDForIdent("HostBenachrichtigungsFlag")) === false)
								{
										$this->SetValueBoolean("HostBenachrichtigungsFlag", true);
										$BenachrichtigungsTimer = $this->ReadPropertyInteger("AlarmZeitDiff");
										if ($BenachrichtigungsTimer == 0)
										{
												$this->Benachrichtigung();
												$this->SetTimerInterval("OfflineBenachrichtigung", 0);
										}
										$this->SetTimerInterval("OfflineBenachrichtigung", $BenachrichtigungsTimer);
								}
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
				$Text = str_replace('Â', '', $Text);
				
				//PUSH-NACHRICHT
				if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
        {
        		$WFinstanzID = $this->ReadPropertyString("WebFrontInstanceID");
        		if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
        		{
        				WFC_PushNotification($WFinstanzID, "HostMonitor", $Text, "", 0);
        		}
        }
        
        //EMAIL-NACHRICHT
        if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
        {
        		$SMTPinstanzID = $this->ReadPropertyString("SmtpInstanceID");
        		if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
        		{
        				SMTP_SendMail($SMTPinstanzID, "HostMonitor", $Text);
        		}		
        }
        
        //EIGENE-AKTION
        if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
        {
        		$SkriptID = $this->ReadPropertyString("EigenesSkriptID");
        		if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
        		{
        				IPS_RunScriptEx($SkriptID, array("HMON_Hostname" => $Hostname, "HMON_Adresse" => $Hostadresse, "HMON_Text" => $Text, "HMON_Zeit" => $Hostname));
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
    
    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 0)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
    
    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
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
}
?>